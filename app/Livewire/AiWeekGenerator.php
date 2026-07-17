<?php

namespace App\Livewire;

use App\Data\AiWeekPlanDraft;
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

    public string $userInstructions = '';

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
            $this->rangeStart,
            $this->selectedHorizonDays(),
            $this->userInstructions,
        );
        $this->promptFull = $built['full'];
        $this->promptSystem = $built['system'];
        $this->promptUser = $built['user'];
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
        ]);
    }
}
