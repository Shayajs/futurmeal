<?php

namespace App\Livewire;

use App\Enums\FoodReferenceType;
use App\Enums\PriceSource;
use App\Models\FoodItem;
use App\Models\MealPlanEntry;
use App\Models\Program;
use App\Services\Budget\BudgetService;
use App\Services\Nutrition\CustomFoodService;
use App\Services\Nutrition\FoodSearchService;
use App\Services\Nutrition\MealPlanEntryCalculator;
use App\Services\Nutrition\MealPlannerService;
use App\Services\Plan\PlanViewContextService;
use App\Services\Program\ProgramPlanService;
use App\Services\Social\PublishedMenuService;
use App\Support\MealSlots;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DayEditor extends Component
{
    public string $date;

    public ?int $programId = null;

    public ?int $viewUserId = null;

    public ?int $mealPlanId = null;

    public bool $canEdit = true;

    public ?string $activeSlot = null;

    public string $foodSearch = '';

    public array $foodSearchResults = [];

    public float $quantityG = 100;

    public ?float $pricePerKg = null;

    public ?string $selectedFoodType = null;

    public ?int $selectedFoodId = null;

    public ?string $selectedFoodLabel = null;

    public ?string $selectedFoodBarcode = null;

    public ?string $priceSource = null;

    public ?string $priceSourceLabel = null;

    public string $storeBrand = '';

    public string $priceObservedAt = '';

    public bool $sharePriceWithCommunity = true;

    public bool $canCreateCustomFood = false;

    public bool $barcodeNotFound = false;

    public bool $showCustomFoodPanel = false;

    public string $customFoodName = '';

    public string $customFoodBrand = '';

    public ?string $customFoodBarcode = null;

    public ?float $customFoodEnergy = null;

    public ?float $customFoodProtein = null;

    public ?float $customFoodCarbs = null;

    public ?float $customFoodFat = null;

    public bool $shareCustomFoodWithCommunity = true;

    public bool $showCopyPanel = false;

    public array $copyTargets = [];

    public int $copyWeekOffset = 0;

    public bool $showPublishPanel = false;

    public string $publishTitle = '';

    public string $publishDescription = '';

    public bool $publishPublic = true;

    public function mount(
        string $date,
        ProgramPlanService $programPlan,
        PlanViewContextService $planContext,
        MealPlannerService $planner,
    ): void {
        try {
            $this->date = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            abort(404);
        }

        $this->programId = request()->integer('program') ?: null;
        $this->viewUserId = request()->integer('view') ?: null;
        $this->resolvePlan($programPlan, $planContext, $planner);
    }

    public function goToDay(string $date): void
    {
        $this->redirect(
            route('planner.day', array_filter([
                'date' => $date,
                'program' => $this->programId,
                'view' => $this->viewUserId,
            ])),
            navigate: true,
        );
    }

    public function openSlot(string $slot): void
    {
        if (! $this->canEdit || ! MealSlots::isValid($slot)) {
            return;
        }

        $this->activeSlot = $slot;
        $this->foodSearch = '';
        $this->foodSearchResults = [];
        $this->quantityG = 100;
        $this->priceObservedAt = today()->toDateString();
        $this->storeBrand = Auth::user()->profile
            ? (app(BudgetService::class)->preferredStoreBrand(Auth::user()) ?? '')
            : '';
        $this->clearSelectedFood();
        $this->resetCustomFoodPanel();
    }

    public function closeSlot(): void
    {
        $this->activeSlot = null;
        $this->foodSearch = '';
        $this->foodSearchResults = [];
        $this->clearSelectedFood();
        $this->resetCustomFoodPanel();
    }

    public function clearSelectedFood(): void
    {
        $this->selectedFoodType = null;
        $this->selectedFoodId = null;
        $this->selectedFoodLabel = null;
        $this->selectedFoodBarcode = null;
        $this->pricePerKg = null;
        $this->priceSource = null;
        $this->priceSourceLabel = null;
    }

    private function resetCustomFoodPanel(): void
    {
        $this->canCreateCustomFood = false;
        $this->barcodeNotFound = false;
        $this->showCustomFoodPanel = false;
        $this->customFoodName = '';
        $this->customFoodBrand = '';
        $this->customFoodBarcode = null;
        $this->customFoodEnergy = null;
        $this->customFoodProtein = null;
        $this->customFoodCarbs = null;
        $this->customFoodFat = null;
        $this->shareCustomFoodWithCommunity = true;
    }

    public function openCustomFoodPanel(): void
    {
        $this->showCustomFoodPanel = true;
        if ($this->customFoodName === '' && $this->foodSearch !== '') {
            $this->customFoodName = $this->foodSearch;
        }
        if ($this->barcodeNotFound && $this->looksLikeBarcode($this->foodSearch)) {
            $this->customFoodBarcode = $this->foodSearch;
        }
    }

    public function createCustomFood(CustomFoodService $customFoods, BudgetService $budget): void
    {
        if (! $this->canEdit || ! $this->activeSlot) {
            return;
        }

        $item = $customFoods->create(
            Auth::user(),
            $this->customFoodName,
            [
                'energy_kcal' => $this->customFoodEnergy ?? 0,
                'protein_g' => $this->customFoodProtein ?? 0,
                'carbs_g' => $this->customFoodCarbs ?? 0,
                'fat_g' => $this->customFoodFat ?? 0,
            ],
            $this->customFoodBrand ?: null,
            $this->customFoodBarcode,
            $this->shareCustomFoodWithCommunity,
        );

        $this->selectFoodForAdd(
            FoodReferenceType::Custom->value,
            $item->id,
            $item->name,
            $item->external_id,
            $budget,
        );

        $this->showCustomFoodPanel = false;
        $this->canCreateCustomFood = false;
        $this->foodSearchResults = [];
    }

    private function looksLikeBarcode(string $query): bool
    {
        return (bool) preg_match('/^\d{8,14}$/', trim($query));
    }

    public function selectFoodForAdd(string $type, int $id, string $label, ?string $barcode, BudgetService $budget): void
    {
        if (! $this->canEdit || ! $this->activeSlot) {
            return;
        }

        $this->selectedFoodType = $type;
        $this->selectedFoodId = $id;
        $this->selectedFoodLabel = $label;
        $this->selectedFoodBarcode = $barcode;

        $this->applyResolvedPrice($budget, $type, $id, $label, $barcode);
    }

    private function applyResolvedPrice(
        BudgetService $budget,
        ?string $type,
        ?int $id,
        string $label,
        ?string $barcode,
    ): void {
        $user = Auth::user();
        $locationId = $user->profile?->open_prices_location_id;
        $brand = trim($this->storeBrand) !== '' ? trim($this->storeBrand) : null;

        $resolution = $budget->resolvePrice($user, $type, $id, $label, $barcode, $locationId, $brand);

        if ($resolution === null) {
            $this->pricePerKg = null;
            $this->priceSource = null;
            $this->priceSourceLabel = null;

            return;
        }

        $this->pricePerKg = $resolution->pricePerKg;
        $this->priceSource = $resolution->source->value;
        $this->priceSourceLabel = $resolution->sourceLabel();
    }

    public function updatedFoodSearch(FoodSearchService $search): void
    {
        if (! $this->activeSlot) {
            return;
        }

        $result = $search->search($this->foodSearch, Auth::user());
        $this->foodSearchResults = $result['results'];
        $this->canCreateCustomFood = $result['can_create_custom'];
        $this->barcodeNotFound = $result['barcode_not_found'] ?? false;

        if ($this->canCreateCustomFood && $this->showCustomFoodPanel) {
            $this->customFoodName = $result['query'];
        }
    }

    public function updatedStoreBrand(BudgetService $budget): void
    {
        if ($this->selectedFoodType && $this->selectedFoodId && $this->selectedFoodLabel) {
            $this->applyResolvedPrice(
                $budget,
                $this->selectedFoodType,
                $this->selectedFoodId,
                $this->selectedFoodLabel,
                $this->selectedFoodBarcode,
            );
        }
    }

    public function addFood(BudgetService $budget): void
    {
        if (! $this->canEdit || ! $this->activeSlot || ! $this->selectedFoodType || ! $this->selectedFoodId || ! $this->selectedFoodLabel) {
            return;
        }

        $price = $this->pricePerKg !== null && $this->pricePerKg > 0
            ? round($this->pricePerKg, 2)
            : null;

        $priceSource = PriceSource::tryFrom($this->priceSource ?? '') ?? PriceSource::User;

        $this->createFoodEntry(
            $this->activeSlot,
            $this->selectedFoodType,
            $this->selectedFoodId,
            $this->selectedFoodLabel,
            max(1, round($this->quantityG, 1)),
            $budget,
            $price,
            $priceSource,
        );

        $this->foodSearch = '';
        $this->foodSearchResults = [];
        $this->clearSelectedFood();
    }

    public function addSuggestion(string $slot, string $type, ?int $id, string $label, float $quantityG, BudgetService $budget): void
    {
        if (! $this->canEdit || ! MealSlots::isValid($slot)) {
            return;
        }

        $user = Auth::user();
        $barcode = null;
        if ($type === FoodReferenceType::OpenFoodFacts->value && $id) {
            $barcode = FoodItem::query()->whereKey($id)->value('external_id');
        }

        $resolution = $budget->resolvePrice(
            $user,
            $type,
            $id,
            $label,
            $barcode,
            $user->profile?->open_prices_location_id,
            trim($this->storeBrand) !== '' ? trim($this->storeBrand) : $budget->preferredStoreBrand($user),
        );

        $price = $resolution?->pricePerKg;
        $priceSource = $resolution?->source ?? PriceSource::User;

        $this->createFoodEntry($slot, $type, $id, $label, $quantityG, $budget, $price, $priceSource);
    }

    public function addRecipeBundle(string $slot, int $recipeId, BudgetService $budget): void
    {
        if (! $this->canEdit || ! MealSlots::isValid($slot)) {
            return;
        }

        $recipe = Auth::user()->recipes()->with('ingredients')->findOrFail($recipeId);

        if ($recipe->is_macro_preset) {
            $entry = MealPlanEntry::create([
                'meal_plan_id' => $this->mealPlanId,
                'planned_on' => $this->date,
                'meal_slot' => $slot,
                'recipe_id' => $recipe->id,
                'label' => $recipe->name,
                'portions' => 1,
            ]);
            $budget->syncEntryCost(Auth::user(), $entry);

            return;
        }

        $sort = 0;
        foreach ($recipe->ingredients as $ingredient) {
            $entry = MealPlanEntry::create([
                'meal_plan_id' => $this->mealPlanId,
                'planned_on' => $this->date,
                'meal_slot' => $slot,
                'recipe_id' => $recipe->id,
                'reference_type' => $ingredient->reference_type,
                'reference_id' => $ingredient->reference_id,
                'food_item_id' => $ingredient->food_item_id,
                'label' => $ingredient->label,
                'quantity_g' => (float) $ingredient->quantity_g,
                'sort_order' => $sort++,
            ]);
            $budget->syncEntryCost(Auth::user(), $entry);
        }
    }

    public function updateQuantity(int $entryId, float $quantityG, BudgetService $budget): void
    {
        if (! $this->canEdit) {
            return;
        }

        $entry = MealPlanEntry::where('meal_plan_id', $this->mealPlanId)->findOrFail($entryId);
        $entry->update(['quantity_g' => max(1, min(5000, round($quantityG, 1)))]);
        $budget->syncEntryCost(Auth::user(), $entry->fresh());
    }

    public function updatePortions(int $entryId, float $portions, BudgetService $budget): void
    {
        if (! $this->canEdit) {
            return;
        }

        $entry = MealPlanEntry::where('meal_plan_id', $this->mealPlanId)->findOrFail($entryId);
        $entry->update(['portions' => max(0.25, min(10, round($portions, 2)))]);
        $budget->syncEntryCost(Auth::user(), $entry->fresh());
    }

    public function removeEntry(int $entryId): void
    {
        if (! $this->canEdit) {
            return;
        }

        MealPlanEntry::where('meal_plan_id', $this->mealPlanId)->where('id', $entryId)->delete();
    }

    public function clearDay(): void
    {
        if (! $this->canEdit) {
            return;
        }

        MealPlanEntry::where('meal_plan_id', $this->mealPlanId)
            ->whereDate('planned_on', $this->date)
            ->delete();
    }

    public function toggleCopyPanel(): void
    {
        $this->showCopyPanel = ! $this->showCopyPanel;
        $this->copyTargets = [];
        $this->copyWeekOffset = 0;
    }

    public function selectWholeWeek(): void
    {
        $start = Carbon::parse($this->date)->startOfWeek()->addWeeks($this->copyWeekOffset);
        $this->copyTargets = collect(range(0, 6))
            ->map(fn ($i) => $start->copy()->addDays($i)->toDateString())
            ->reject(fn ($d) => $d === $this->date)
            ->values()
            ->all();
    }

    public function copyDay(ProgramPlanService $programPlan, BudgetService $budget): void
    {
        if (! $this->canEdit || empty($this->copyTargets)) {
            return;
        }

        $sourceEntries = MealPlanEntry::where('meal_plan_id', $this->mealPlanId)
            ->whereDate('planned_on', $this->date)
            ->get();

        if ($sourceEntries->isEmpty()) {
            return;
        }

        $copied = 0;

        foreach ($this->copyTargets as $target) {
            $targetDate = Carbon::parse($target)->toDateString();
            if ($targetDate === $this->date) {
                continue;
            }

            $targetPlan = $programPlan->resolvePlan(Auth::user(), $this->programId, Carbon::parse($targetDate));

            MealPlanEntry::where('meal_plan_id', $targetPlan->id)
                ->whereDate('planned_on', $targetDate)
                ->delete();

            foreach ($sourceEntries as $source) {
                $entry = MealPlanEntry::create([
                    'meal_plan_id' => $targetPlan->id,
                    'planned_on' => $targetDate,
                    'meal_slot' => $source->meal_slot,
                    'recipe_id' => $source->recipe_id,
                    'reference_type' => $source->reference_type,
                    'reference_id' => $source->reference_id,
                    'food_item_id' => $source->food_item_id,
                    'label' => $source->label,
                    'quantity_g' => $source->quantity_g,
                    'portions' => $source->portions,
                    'estimated_cost' => $source->estimated_cost,
                    'sort_order' => $source->sort_order,
                ]);
            }

            $copied++;
        }

        $this->showCopyPanel = false;
        $this->copyTargets = [];
        session()->flash('day-editor-status', "Journée copiée vers {$copied} jour(s).");
    }

    public function togglePublishPanel(): void
    {
        $this->showPublishPanel = ! $this->showPublishPanel;
        $this->publishTitle = 'Menu du '.Carbon::parse($this->date)->translatedFormat('l');
        $this->publishDescription = '';
        $this->publishPublic = true;
    }

    public function publishDay(PublishedMenuService $publishedMenus): void
    {
        $this->validate([
            'publishTitle' => 'required|string|min:3|max:100',
            'publishDescription' => 'nullable|string|max:500',
        ]);

        $menu = $publishedMenus->publishDay(
            Auth::user(),
            $this->mealPlanId,
            $this->date,
            $this->publishTitle,
            $this->publishDescription ?: null,
            $this->publishPublic,
        );

        $this->showPublishPanel = false;

        session()->flash('day-editor-status', $menu
            ? "Menu « {$menu->title} » publié."
            : 'Impossible de publier une journée vide.');
    }

    private function createFoodEntry(
        string $slot,
        string $type,
        ?int $id,
        string $label,
        float $quantityG,
        BudgetService $budget,
        ?float $pricePerKg = null,
        PriceSource $priceSource = PriceSource::User,
    ): void {
        $referenceType = FoodReferenceType::tryFrom($type);
        $foodItemId = $referenceType && in_array($referenceType, [FoodReferenceType::OpenFoodFacts, FoodReferenceType::Custom], true)
            ? $id
            : null;

        if ($pricePerKg !== null && $pricePerKg > 0) {
            $brand = trim($this->storeBrand) !== '' ? trim($this->storeBrand) : null;
            $budget->upsert(
                Auth::user(),
                $label,
                $pricePerKg,
                $referenceType?->value,
                $id,
                $foodItemId,
                $priceSource,
                $brand,
                $this->sharePriceWithCommunity && $brand !== null,
                Auth::user()->profile?->open_prices_location_id,
                Carbon::parse($this->priceObservedAt ?: today()),
            );
        }

        $entry = MealPlanEntry::create([
            'meal_plan_id' => $this->mealPlanId,
            'planned_on' => $this->date,
            'meal_slot' => $slot,
            'reference_type' => $referenceType,
            'reference_id' => $id,
            'food_item_id' => $foodItemId,
            'label' => $label,
            'quantity_g' => $quantityG,
        ]);

        $budget->syncEntryCost(Auth::user(), $entry);
    }

    private function resolvePlan(
        ProgramPlanService $programPlan,
        PlanViewContextService $planContext,
        MealPlannerService $planner,
    ): void {
        $user = Auth::user();
        $context = $planContext->resolve($user, $this->viewUserId, $this->programId);

        if ($context->isProgram()) {
            $plan = $programPlan->resolvePlan($user, $this->programId, Carbon::parse($this->date));
        } else {
            $plan = $planner->ensureDefaultPlan($context->planOwner);
        }

        $this->mealPlanId = $plan->id;
        $this->canEdit = $context->canEdit;
    }

    private function recentFoods(): array
    {
        return MealPlanEntry::query()
            ->whereHas('mealPlan', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereNotNull('quantity_g')
            ->whereNotNull('label')
            ->orderByDesc('id')
            ->limit(60)
            ->with('foodItem')
            ->get()
            ->unique('label')
            ->take(10)
            ->map(function (MealPlanEntry $e) {
                $barcode = null;
                if ($e->food_item_id) {
                    $barcode = $e->foodItem?->external_id;
                }

                return [
                    'type' => $e->reference_type?->value,
                    'id' => $e->reference_id ?? $e->food_item_id,
                    'label' => $e->label,
                    'quantity_g' => (float) $e->quantity_g,
                    'barcode' => $barcode,
                ];
            })
            ->values()
            ->all();
    }

    public function render(
        MealPlanEntryCalculator $entryCalculator,
        PlanViewContextService $planContext,
    ) {
        $user = Auth::user();
        $context = $planContext->resolve($user, $this->viewUserId, $this->programId);
        $day = Carbon::parse($this->date);

        $entries = MealPlanEntry::where('meal_plan_id', $this->mealPlanId)
            ->whereDate('planned_on', $this->date)
            ->with(['recipe', 'foodItem'])
            ->orderBy('sort_order')
            ->get();

        $entriesBySlot = $entries->groupBy(fn ($e) => MealSlots::normalize($e->meal_slot));

        $total = new \App\Data\NutrientProfile;
        $cost = 0.0;
        $hasCost = false;
        foreach ($entries as $entry) {
            $total = $total->add($entryCalculator->calculate($entry));
            if ($entry->estimated_cost !== null) {
                $hasCost = true;
                $cost += (float) $entry->estimated_cost;
            }
        }

        $copyWeekStart = $day->copy()->startOfWeek()->addWeeks($this->copyWeekOffset);
        $copyDays = collect(range(0, 6))->map(fn ($i) => $copyWeekStart->copy()->addDays($i));

        return view('livewire.day-editor', [
            'day' => $day,
            'slots' => MealSlots::ordered(),
            'entriesBySlot' => $entriesBySlot,
            'entryCalculator' => $entryCalculator,
            'dayTotal' => $total->toArray(),
            'dayCost' => $hasCost ? round($cost, 2) : null,
            'calorieTarget' => $user->profile?->daily_calorie_target ?? 2000,
            'planContext' => $context,
            'recipes' => $user->recipes()->orderBy('name')->get(),
            'recentFoods' => $this->recentFoods(),
            'activeProgram' => $this->programId ? Program::find($this->programId) : null,
            'copyDays' => $copyDays,
            'storeBrands' => config('futurmeal.store_brands', []),
        ]);
    }
}
