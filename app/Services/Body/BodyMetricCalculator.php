<?php

namespace App\Services\Body;

use App\Enums\BodyMetricSource;
use App\Enums\Gender;

class BodyMetricCalculator
{
    public function bmi(float $weightKg, float $heightCm): float
    {
        $heightM = $heightCm / 100;

        if ($heightM <= 0) {
            return 0;
        }

        return round($weightKg / ($heightM ** 2), 2);
    }

    public function navyBodyFatPercent(
        Gender $gender,
        float $heightCm,
        float $neckCm,
        float $waistCm,
        ?float $hipCm = null,
    ): float {
        if ($gender === Gender::Female) {
            if ($hipCm === null || ($waistCm + $hipCm - $neckCm) <= 0) {
                return 0;
            }

            return round(
                163.205 * log10($waistCm + $hipCm - $neckCm)
                - 97.684 * log10($heightCm)
                - 78.387,
                2
            );
        }

        if (($waistCm - $neckCm) <= 0) {
            return 0;
        }

        return round(
            86.010 * log10($waistCm - $neckCm)
            - 70.041 * log10($heightCm)
            + 36.76,
            2
        );
    }

    public function leanMassKg(float $weightKg, float $bodyFatPercent): float
    {
        return round($weightKg * (1 - ($bodyFatPercent / 100)), 2);
    }

    /** Métabolisme de base (Mifflin-St Jeor) : kcal brûlées au repos complet. */
    public function bmr(
        Gender $gender,
        float $weightKg,
        float $heightCm,
        int $ageYears,
    ): int {
        $bmr = match ($gender) {
            Gender::Male => (10 * $weightKg) + (6.25 * $heightCm) - (5 * $ageYears) + 5,
            Gender::Female => (10 * $weightKg) + (6.25 * $heightCm) - (5 * $ageYears) - 161,
            Gender::Other => (10 * $weightKg) + (6.25 * $heightCm) - (5 * $ageYears) - 78,
        };

        return (int) round($bmr);
    }

    public function tdeeMifflinStJeor(
        Gender $gender,
        float $weightKg,
        float $heightCm,
        int $ageYears,
        float $activityMultiplier,
        int $calorieAdjustment = 0,
    ): int {
        $bmr = $this->bmr($gender, $weightKg, $heightCm, $ageYears);

        return (int) round(($bmr * $activityMultiplier) + $calorieAdjustment);
    }

    public function weightLossProjectionKg(float $calorieDeficitTotal): float
    {
        return round($calorieDeficitTotal / 7700, 2);
    }

    public function resolveBodyFat(
        BodyMetricSource $source,
        Gender $gender,
        float $heightCm,
        ?float $manualPercent,
        ?float $neckCm,
        ?float $waistCm,
        ?float $hipCm,
    ): ?float {
        return match ($source) {
            BodyMetricSource::Manual, BodyMetricSource::Scale => $manualPercent,
            BodyMetricSource::Navy => $this->navyBodyFatPercent(
                $gender,
                $heightCm,
                (float) $neckCm,
                (float) $waistCm,
                $hipCm,
            ),
        };
    }
}
