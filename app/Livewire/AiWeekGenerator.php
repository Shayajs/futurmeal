<?php

namespace App\Livewire;

use App\Data\AiMealPreferences;
use App\Data\AiWeekPlanDraft;
use App\Enums\MealComplexity;
use App\Enums\WheyPreference;
use App\Services\Ai\AiPromptBuilder;
use App\Services\Ai\AiWeekPlanApplier;
use App\Services\Ai\AiWeekPlanParser;
use App\Services\Ai\AiWeekPlanResolver;
use App\Services\Ai\OpenAiCompatibleClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Throwable;

class AiWeekGenerator extends Component
{
    public const MAX_RANGE_DAYS = 31;

    #[Reactive]
    public ?int $mealPlanId = null;

    #[Reactive]
    public string $weekStart = '';

    #[Reactive]
    public int $horizonDays = 7;

    #[Reactive]
    public bool $canEdit = true;

    public bool $show = false;

    /** consignes | prompt | response | preview */
    public string $step = 'consignes';

    public string $rangeStart = '';

    public string $rangeEnd = '';

    public string $aiWhey = 'none';

    public string $aiMealComplexity = 'simple_budget';

    public string $forbiddenFoodsText = '';

    public string $preferredFoodsText = '';

    public int $tastyDays = 1;

    public bool $includeDesserts = false;

    public string $userInstructions = '';

    public bool $savePreferences = true;

    public string $promptFull = '';

    public string $promptSystem = '';

    public string $promptUser = '';

    public string $rawResponse = '';

    public ?array $draftPayload = null;

    public ?string $errorMessage = null;

    public bool $generating = false;

    #[On('open-ai-week-generator')]
    public function open(AiPromptBuilder $builder): void
    {
        if (! $this->canEdit || ! $this->mealPlanId) {
            return;
        }

        $this->resetFlow();
        $this->initRangeFromPlanner();
        $this->loadPreferencesFromProfile();
        $this->rebuildPrompt($builder);
        $this->show = true;
        $this->step = 'consignes';
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetFlow();
    }

    public function goToPrompt(AiPromptBuilder $builder): void
    {
        if (! $this->validateRange()) {
            return;
        }

        if ($this->savePreferences) {
            $this->persistPreferences();
        }

        $this->rebuildPrompt($builder);
        $this->step = 'prompt';
        $this->errorMessage = null;
    }

    public function goToResponse(): void
    {
        $this->step = 'response';
        $this->errorMessage = null;
    }

    public function goToConsignes(): void
    {
        $this->step = 'consignes';
        $this->errorMessage = null;
    }

    public function generateViaApi(
        AiPromptBuilder $builder,
        OpenAiCompatibleClient $client,
        AiWeekPlanParser $parser,
        AiWeekPlanResolver $resolver,
    ): void {
        if (! $this->canEdit || ! $this->mealPlanId) {
            return;
        }

        if (! $this->validateRange()) {
            $this->step = 'consignes';

            return;
        }

        $user = Auth::user();
        if (! $user->hasAiApiConfigured()) {
            $this->errorMessage = 'Configure ta clé API IA dans Paramètres → Intelligence artificielle.';

            return;
        }

        $key = 'ai-week-api:'.$user->id;
        $maxAttempts = (int) config('futurmeal.ai.api_rate_limit_per_minute', 5);
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->errorMessage = 'Trop de requêtes IA. Réessaie dans une minute.';

            return;
        }

        RateLimiter::hit($key, 60);
        $this->generating = true;
        $this->errorMessage = null;

        try {
            $this->rebuildPrompt($builder);
            $content = $client->chat($user, [
                'system' => $this->promptSystem,
                'user' => $this->promptUser,
            ]);
            $this->rawResponse = $content;
            $this->parseAndResolve($parser, $resolver);
            $this->step = 'preview';
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->step = 'prompt';
        } finally {
            $this->generating = false;
        }
    }

    public function parsePastedResponse(AiWeekPlanParser $parser, AiWeekPlanResolver $resolver): void
    {
        if (! $this->canEdit || ! $this->mealPlanId) {
            return;
        }

        if (! $this->validateRange()) {
            $this->step = 'consignes';

            return;
        }

        $this->errorMessage = null;

        try {
            $this->parseAndResolve($parser, $resolver);
            $this->step = 'preview';
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function apply(AiWeekPlanApplier $applier): void
    {
        if (! $this->canEdit || ! $this->mealPlanId || ! $this->draftPayload) {
            return;
        }

        if (! $this->validateRange()) {
            $this->step = 'consignes';

            return;
        }

        $draft = AiWeekPlanDraft::fromArray($this->draftPayload);
        if ($draft->resolvedCount() === 0) {
            $this->errorMessage = 'Aucun item résolu à appliquer.';

            return;
        }

        $result = $applier->apply(
            Auth::user(),
            $this->mealPlanId,
            $this->rangeStart,
            $this->selectedHorizonDays(),
            $draft,
        );

        $msg = "{$result['created']} entrée(s) ajoutée(s)";
        if ($result['skipped'] > 0) {
            $msg .= ", {$result['skipped']} ignorée(s)";
        }

        session()->flash('planner-status', "Plan IA appliqué : {$msg}.");
        $this->close();
        $this->dispatch('ai-week-applied');
    }

    private function parseAndResolve(AiWeekPlanParser $parser, AiWeekPlanResolver $resolver): void
    {
        $expectedDates = $this->expectedDates();
        $parsed = $parser->parse($this->rawResponse, $expectedDates);
        $draft = $resolver->resolve(Auth::user(), $parsed);
        $this->draftPayload = $draft->toArray();
    }

    private function rebuildPrompt(AiPromptBuilder $builder): void
    {
        $built = $builder->build(
            Auth::user(),
            $this->rangeStart !== '' ? $this->rangeStart : ($this->weekStart ?: now()->toDateString()),
            $this->rangeStart !== '' && $this->rangeEnd !== ''
                ? $this->selectedHorizonDays()
                : max(1, $this->horizonDays),
            $this->currentPreferences(),
        );
        $this->promptFull = $built['full'];
        $this->promptSystem = $built['system'];
        $this->promptUser = $built['user'];
    }

    private function currentPreferences(): AiMealPreferences
    {
        return new AiMealPreferences(
            whey: WheyPreference::tryFrom($this->aiWhey) ?? WheyPreference::None,
            mealComplexity: MealComplexity::tryFrom($this->aiMealComplexity) ?? MealComplexity::SimpleBudget,
            forbiddenFoods: $this->parseFoodList($this->forbiddenFoodsText),
            preferredFoods: $this->parseFoodList($this->preferredFoodsText),
            tastyDays: max(0, $this->tastyDays),
            includeDesserts: $this->includeDesserts,
            freeInstructions: $this->userInstructions,
        );
    }

    private function loadPreferencesFromProfile(): void
    {
        $prefs = AiMealPreferences::fromProfile(Auth::user()?->profile);
        $this->aiWhey = $prefs->whey->value;
        $this->aiMealComplexity = $prefs->mealComplexity->value;
        $this->forbiddenFoodsText = implode(', ', $prefs->forbiddenFoods);
        $this->preferredFoodsText = implode(', ', $prefs->preferredFoods);
        $this->tastyDays = $prefs->tastyDays;
        $this->includeDesserts = $prefs->includeDesserts;
        $this->savePreferences = true;
    }

    private function persistPreferences(): void
    {
        $profile = Auth::user()?->profile;
        if (! $profile) {
            return;
        }

        $prefs = $this->currentPreferences();
        $profile->update([
            'ai_whey' => $prefs->whey->value,
            'ai_meal_complexity' => $prefs->mealComplexity->value,
            'ai_forbidden_foods' => $prefs->forbiddenFoods,
            'ai_preferred_foods' => $prefs->preferredFoods,
            'ai_tasty_days_per_week' => min(7, max(0, $prefs->tastyDays)),
            'ai_include_desserts' => $prefs->includeDesserts,
        ]);
    }

    /** @return list<string> */
    private function parseFoodList(string $text): array
    {
        $parts = preg_split('/[\n,;]+/u', $text) ?: [];

        return collect($parts)
            ->map(fn ($p) => trim($p))
            ->filter(fn ($p) => $p !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function expectedDates(): array
    {
        $start = Carbon::parse($this->rangeStart)->startOfDay();
        $days = $this->selectedHorizonDays();

        return collect(range(0, $days - 1))
            ->map(fn (int $i) => $start->copy()->addDays($i)->toDateString())
            ->all();
    }

    private function initRangeFromPlanner(): void
    {
        $start = $this->weekStart !== ''
            ? Carbon::parse($this->weekStart)->toDateString()
            : now()->startOfWeek()->toDateString();
        $horizon = max(1, $this->horizonDays);

        $this->rangeStart = $start;
        $this->rangeEnd = Carbon::parse($start)->addDays($horizon - 1)->toDateString();
    }

    private function selectedHorizonDays(): int
    {
        $start = Carbon::parse($this->rangeStart)->startOfDay();
        $end = Carbon::parse($this->rangeEnd)->startOfDay();

        return max(1, $start->diffInDays($end) + 1);
    }

    private function validateRange(): bool
    {
        try {
            $this->validate([
                'rangeStart' => ['required', 'date'],
                'rangeEnd' => ['required', 'date', 'after_or_equal:rangeStart'],
                'aiWhey' => ['required', 'in:none,concentrate,isolate'],
                'aiMealComplexity' => ['required', 'in:simple_budget,simple_tight,fast_tasty,complex,gourmet'],
                'tastyDays' => ['required', 'integer', 'min:0', 'max:31'],
            ], [
                'rangeStart.required' => 'Choisis une date de début.',
                'rangeEnd.required' => 'Choisis une date de fin.',
                'rangeEnd.after_or_equal' => 'La date de fin doit être ≥ à la date de début.',
            ]);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->validator->errors()->all())->first();

            return false;
        }

        $days = $this->selectedHorizonDays();
        if ($days > self::MAX_RANGE_DAYS) {
            $this->errorMessage = 'Plage limitée à '.self::MAX_RANGE_DAYS.' jours.';

            return false;
        }

        if ($this->tastyDays > $days) {
            $this->tastyDays = $days;
        }

        return true;
    }

    private function resetFlow(): void
    {
        $this->step = 'consignes';
        $this->userInstructions = '';
        $this->promptFull = '';
        $this->promptSystem = '';
        $this->promptUser = '';
        $this->rawResponse = '';
        $this->draftPayload = null;
        $this->errorMessage = null;
        $this->generating = false;
        $this->rangeStart = '';
        $this->rangeEnd = '';
        $this->aiWhey = 'none';
        $this->aiMealComplexity = 'simple_budget';
        $this->forbiddenFoodsText = '';
        $this->preferredFoodsText = '';
        $this->tastyDays = 1;
        $this->includeDesserts = false;
        $this->savePreferences = true;
    }

    public function render()
    {
        $draft = $this->draftPayload ? AiWeekPlanDraft::fromArray($this->draftPayload) : null;
        $hasApi = Auth::user()?->hasAiApiConfigured() ?? false;
        $selectedDays = ($this->rangeStart !== '' && $this->rangeEnd !== '')
            ? $this->selectedHorizonDays()
            : $this->horizonDays;

        return view('livewire.ai-week-generator', [
            'draft' => $draft,
            'hasApi' => $hasApi,
            'slots' => \App\Support\MealSlots::ordered(),
            'selectedDays' => $selectedDays,
            'maxRangeDays' => self::MAX_RANGE_DAYS,
            'wheyOptions' => WheyPreference::cases(),
            'complexityOptions' => MealComplexity::cases(),
        ]);
    }
}
