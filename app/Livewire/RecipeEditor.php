<?php

namespace App\Livewire;

use App\Enums\FoodReferenceType;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Nutrition\CustomFoodService;
use App\Services\Nutrition\FoodSearchService;
use App\Services\Nutrition\NutritionResolver;
use App\Services\Nutrition\RecipeCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RecipeEditor extends Component
{
    public ?Recipe $recipe = null;

    public string $name = '';

    public bool $is_macro_preset = false;

    public float $preset_energy_kcal = 0;

    public float $preset_protein_g = 0;

    public float $preset_carbs_g = 0;

    public float $preset_fat_g = 0;

    public int $servings = 1;

    public array $ingredients = [];

    public string $search = '';

    public array $searchResults = [];

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

    public function mount(?Recipe $recipe = null): void
    {
        if ($recipe) {
            $this->authorizeRecipe($recipe);
            $this->recipe = $recipe;
            $this->name = $recipe->name;
            $this->is_macro_preset = $recipe->is_macro_preset;
            $this->preset_energy_kcal = (float) $recipe->preset_energy_kcal;
            $this->preset_protein_g = (float) $recipe->preset_protein_g;
            $this->preset_carbs_g = (float) $recipe->preset_carbs_g;
            $this->preset_fat_g = (float) $recipe->preset_fat_g;
            $this->servings = $recipe->servings;
            $this->ingredients = $recipe->ingredients->map(fn ($i) => [
                'reference_type' => $i->reference_type->value,
                'reference_id' => $i->reference_id,
                'label' => $i->label,
                'quantity_g' => (float) $i->quantity_g,
            ])->toArray();
        }
    }

    public function updatedSearch(FoodSearchService $search): void
    {
        $result = $search->search($this->search, Auth::user());
        $this->searchResults = $result['results'];
        $this->canCreateCustomFood = $result['can_create_custom'];
        $this->barcodeNotFound = $result['barcode_not_found'] ?? false;
    }

    public function openCustomFoodPanel(): void
    {
        $this->showCustomFoodPanel = true;
        if ($this->customFoodName === '' && $this->search !== '') {
            $this->customFoodName = $this->search;
        }
        if ($this->barcodeNotFound && preg_match('/^\d{8,14}$/', trim($this->search))) {
            $this->customFoodBarcode = $this->search;
        }
    }

    public function createCustomFood(CustomFoodService $customFoods): void
    {
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

        $this->addIngredient(FoodReferenceType::Custom->value, $item->id, $item->name);
        $this->showCustomFoodPanel = false;
        $this->canCreateCustomFood = false;
    }

    public function addIngredient(string $type, int $id, string $label): void
    {
        $this->ingredients[] = [
            'reference_type' => $type,
            'reference_id' => $id,
            'label' => $label,
            'quantity_g' => 100,
        ];
        $this->search = '';
        $this->searchResults = [];
        $this->showCustomFoodPanel = false;
    }

    public function removeIngredient(int $index): void
    {
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'servings' => 'required|integer|min:1',
        ]);

        $recipe = $this->recipe ?? new Recipe(['user_id' => Auth::id()]);
        $recipe->fill([
            'name' => $this->name,
            'is_macro_preset' => $this->is_macro_preset,
            'preset_energy_kcal' => $this->preset_energy_kcal,
            'preset_protein_g' => $this->preset_protein_g,
            'preset_carbs_g' => $this->preset_carbs_g,
            'preset_fat_g' => $this->preset_fat_g,
            'servings' => $this->servings,
        ]);
        $recipe->user_id = Auth::id();
        $recipe->save();

        $recipe->ingredients()->delete();

        if (! $this->is_macro_preset) {
            foreach ($this->ingredients as $i => $row) {
                RecipeIngredient::create([
                    'recipe_id' => $recipe->id,
                    'reference_type' => $row['reference_type'],
                    'reference_id' => $row['reference_id'],
                    'label' => $row['label'],
                    'quantity_g' => $row['quantity_g'],
                    'sort_order' => $i,
                ]);
            }
        }

        $this->redirect(route('recipes.show', $recipe), navigate: true);
    }

    public function nutrients(RecipeCalculator $calculator): array
    {
        $temp = new Recipe([
            'is_macro_preset' => $this->is_macro_preset,
            'preset_energy_kcal' => $this->preset_energy_kcal,
            'preset_protein_g' => $this->preset_protein_g,
            'preset_carbs_g' => $this->preset_carbs_g,
            'preset_fat_g' => $this->preset_fat_g,
            'servings' => $this->servings,
        ]);

        if ($this->is_macro_preset) {
            return $calculator->calculate($temp)->toArray();
        }

        $temp->setRelation('ingredients', collect($this->ingredients)->map(fn ($row, $i) => new RecipeIngredient([
            'reference_type' => FoodReferenceType::from($row['reference_type']),
            'reference_id' => $row['reference_id'],
            'label' => $row['label'],
            'quantity_g' => $row['quantity_g'],
            'sort_order' => $i,
        ])));

        return $calculator->calculate($temp)->toArray();
    }

    private function authorizeRecipe(Recipe $recipe): void
    {
        abort_unless($recipe->user_id === Auth::id(), 403);
    }

    public function render(RecipeCalculator $calculator)
    {
        return view('livewire.recipe-editor', [
            'nutrients' => $this->nutrients($calculator),
        ]);
    }
}
