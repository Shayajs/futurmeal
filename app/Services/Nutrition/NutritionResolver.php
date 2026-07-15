<?php

namespace App\Services\Nutrition;

use App\Enums\FoodReferenceType;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\FoodItem;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Data\NutrientProfile;

class NutritionResolver
{
    public function profileForIngredient(RecipeIngredient $ingredient): NutrientProfile
    {
        $type = $ingredient->reference_type;

        if ($ingredient->food_item_id) {
            return $this->profileFromFoodItem($ingredient->foodItem);
        }

        return match ($type) {
            FoodReferenceType::Ciqual => $this->profileFromCiqual($ingredient->reference_id),
            FoodReferenceType::OpenFoodFacts, FoodReferenceType::Custom => $this->profileFromFoodItem(
                FoodItem::find($ingredient->reference_id)
            ),
        };
    }

    public function profileFromFoodItem(?FoodItem $item): NutrientProfile
    {
        if (! $item) {
            return new NutrientProfile;
        }

        return new NutrientProfile(
            energyKcal: (float) $item->energy_kcal,
            proteinG: (float) $item->protein_g,
            carbsG: (float) $item->carbs_g,
            fatG: (float) $item->fat_g,
            fiberG: (float) $item->fiber_g,
            saltG: (float) $item->salt_g,
        );
    }

    public function profileFromCiqual(?int $ciqualFoodId): NutrientProfile
    {
        if (! $ciqualFoodId) {
            return new NutrientProfile;
        }

        $values = CiqualComposition::query()
            ->where('ciqual_food_id', $ciqualFoodId)
            ->join('ciqual_nutrients', 'ciqual_nutrients.id', '=', 'ciqual_composition.ciqual_nutrient_id')
            ->pluck('ciqual_composition.value_per_100g', 'ciqual_nutrients.code');

        return new NutrientProfile(
            energyKcal: (float) ($values['ENERGY_KCAL'] ?? 0),
            proteinG: (float) ($values['PROTEIN'] ?? 0),
            carbsG: (float) ($values['CARBS'] ?? 0),
            fatG: (float) ($values['FAT'] ?? 0),
            fiberG: (float) ($values['FIBER'] ?? 0),
            saltG: (float) ($values['SALT'] ?? 0),
        );
    }

    public function searchFoods(string $query, ?User $user = null, int $limit = 10): array
    {
        if (strlen(trim($query)) < 2) {
            return [];
        }

        $ciqual = CiqualFood::query()
            ->where(function ($q) use ($query) {
                $q->where('name_fr', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn (CiqualFood $f) => [
                'type' => FoodReferenceType::Ciqual->value,
                'id' => $f->id,
                'label' => $f->name_fr,
                'barcode' => null,
            ]);

        $customQuery = FoodItem::query()
            ->where('name', 'like', "%{$query}%")
            ->where(function ($q) use ($user) {
                $q->where('is_community', true)
                    ->orWhere('reference_type', FoodReferenceType::OpenFoodFacts);
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            });

        $custom = $customQuery
            ->limit($limit)
            ->get()
            ->map(fn (FoodItem $f) => [
                'type' => $f->reference_type->value,
                'id' => $f->id,
                'label' => $f->name.($f->brand ? " ({$f->brand})" : '').($f->is_community ? '' : ' · perso'),
                'barcode' => $f->external_id,
            ]);

        return $ciqual->concat($custom)->take($limit)->values()->all();
    }
}
