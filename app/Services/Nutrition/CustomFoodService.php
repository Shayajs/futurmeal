<?php

namespace App\Services\Nutrition;

use App\Data\NutrientProfile;
use App\Enums\FoodReferenceType;
use App\Models\FoodItem;
use App\Models\User;
use App\Support\MacroEnergy;
use Illuminate\Validation\ValidationException;

class CustomFoodService
{
    /**
     * @param  array{energy_kcal?: float, protein_g?: float, carbs_g?: float, fat_g?: float, fiber_g?: float, salt_g?: float}  $macros
     */
    public function create(
        User $user,
        string $name,
        array $macros,
        ?string $brand = null,
        ?string $barcode = null,
        bool $shareWithCommunity = true,
    ): FoodItem {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['customFoodName' => 'Le nom est requis.']);
        }

        $energy = (float) ($macros['energy_kcal'] ?? 0);
        $protein = (float) ($macros['protein_g'] ?? 0);
        $carbs = (float) ($macros['carbs_g'] ?? 0);
        $fat = (float) ($macros['fat_g'] ?? 0);

        if ($energy <= 0 && ($protein + $carbs + $fat) <= 0) {
            throw ValidationException::withMessages([
                'customFoodEnergy' => 'Indique au moins les kcal ou les macros P/G/L pour 100 g.',
            ]);
        }

        if ($energy <= 0 && ($protein + $carbs + $fat) > 0) {
            $energy = MacroEnergy::kcalFromMacros($protein, $carbs, $fat);
        }

        $barcode = $barcode !== null ? trim($barcode) : null;
        if ($barcode === '') {
            $barcode = null;
        }

        if ($barcode !== null) {
            $existing = FoodItem::query()
                ->where('reference_type', FoodReferenceType::OpenFoodFacts)
                ->where('external_id', $barcode)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return FoodItem::create([
            'user_id' => $user->id,
            'reference_type' => FoodReferenceType::Custom,
            'external_id' => $barcode,
            'name' => $name,
            'brand' => $brand !== null && trim($brand) !== '' ? trim($brand) : null,
            'energy_kcal' => round($energy, 2),
            'protein_g' => round($protein, 2),
            'carbs_g' => round($carbs, 2),
            'fat_g' => round($fat, 2),
            'fiber_g' => round((float) ($macros['fiber_g'] ?? 0), 2),
            'salt_g' => round((float) ($macros['salt_g'] ?? 0), 2),
            'is_community' => $shareWithCommunity,
        ]);
    }

    public function profileFromMacros(array $macros): NutrientProfile
    {
        return new NutrientProfile(
            energyKcal: (float) ($macros['energy_kcal'] ?? 0),
            proteinG: (float) ($macros['protein_g'] ?? 0),
            carbsG: (float) ($macros['carbs_g'] ?? 0),
            fatG: (float) ($macros['fat_g'] ?? 0),
            fiberG: (float) ($macros['fiber_g'] ?? 0),
            saltG: (float) ($macros['salt_g'] ?? 0),
        );
    }
}
