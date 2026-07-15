<?php

namespace App\Data;

readonly class NutrientProfile
{
    public function __construct(
        public float $energyKcal = 0,
        public float $proteinG = 0,
        public float $carbsG = 0,
        public float $fatG = 0,
        public float $fiberG = 0,
        public float $saltG = 0,
    ) {}

    public function scale(float $grams): self
    {
        $factor = $grams / 100;

        return new self(
            energyKcal: round($this->energyKcal * $factor, 2),
            proteinG: round($this->proteinG * $factor, 2),
            carbsG: round($this->carbsG * $factor, 2),
            fatG: round($this->fatG * $factor, 2),
            fiberG: round($this->fiberG * $factor, 2),
            saltG: round($this->saltG * $factor, 2),
        );
    }

    public function add(self $other): self
    {
        return new self(
            energyKcal: round($this->energyKcal + $other->energyKcal, 2),
            proteinG: round($this->proteinG + $other->proteinG, 2),
            carbsG: round($this->carbsG + $other->carbsG, 2),
            fatG: round($this->fatG + $other->fatG, 2),
            fiberG: round($this->fiberG + $other->fiberG, 2),
            saltG: round($this->saltG + $other->saltG, 2),
        );
    }

    public function toArray(): array
    {
        return [
            'energy_kcal' => $this->energyKcal,
            'protein_g' => $this->proteinG,
            'carbs_g' => $this->carbsG,
            'fat_g' => $this->fatG,
            'fiber_g' => $this->fiberG,
            'salt_g' => $this->saltG,
        ];
    }
}
