<?php

namespace App\Services\Charts;

use App\Models\BodyMetric;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Services\Nutrition\MealPlanEntryCalculator;
use Carbon\Carbon;

class ChartSeriesService
{
    public const SERIES = [
        'weight_kg' => ['label' => 'Poids', 'unit' => 'kg', 'group' => 'Corps'],
        'body_fat_percent' => ['label' => 'Graisse corporelle', 'unit' => '%', 'group' => 'Corps'],
        'lean_mass_kg' => ['label' => 'Masse maigre', 'unit' => 'kg', 'group' => 'Corps'],
        'bmi' => ['label' => 'IMC', 'unit' => '', 'group' => 'Corps'],
        'planned_kcal' => ['label' => 'Kcal planifiées', 'unit' => 'kcal', 'group' => 'Nutrition'],
        'deficit_kcal' => ['label' => 'Déficit réalisé', 'unit' => 'kcal', 'group' => 'Nutrition'],
        'budget_eur' => ['label' => 'Budget', 'unit' => '€', 'group' => 'Budget'],
    ];

    public const PERIODS = [
        '7d' => 'Semaine',
        '30d' => 'Mois',
        '12m' => 'Année',
        'all' => 'Depuis le début',
    ];

    public function __construct(private MealPlanEntryCalculator $entryCalculator) {}

    public function available(): array
    {
        return self::SERIES;
    }

    /** @return array<string, float> map date (Y-m-d) => valeur */
    public function series(User $user, string $key, Carbon $from, Carbon $to): array
    {
        return match ($key) {
            'weight_kg', 'body_fat_percent', 'lean_mass_kg', 'bmi' => $this->bodySeries($user, $key, $from, $to),
            'planned_kcal' => $this->plannedKcalSeries($user, $from, $to),
            'deficit_kcal' => $this->deficitSeries($user, $from, $to),
            'budget_eur' => $this->budgetSeries($user, $from, $to),
            default => [],
        };
    }

    public function periodRange(User $user, string $period): array
    {
        $to = now()->endOfDay();

        $from = match ($period) {
            '7d' => now()->subDays(6)->startOfDay(),
            '30d' => now()->subDays(29)->startOfDay(),
            '12m' => now()->subMonths(12)->startOfDay(),
            default => $this->earliestDataPoint($user),
        };

        return [$from, $to];
    }

    private function earliestDataPoint(User $user): Carbon
    {
        $firstMetric = BodyMetric::where('user_id', $user->id)->min('recorded_at');
        $firstEntry = MealPlanEntry::whereHas('mealPlan', fn ($q) => $q->where('user_id', $user->id))
            ->min('planned_on');

        $candidates = array_filter([$firstMetric, $firstEntry]);

        if (empty($candidates)) {
            return now()->subDays(29)->startOfDay();
        }

        return Carbon::parse(min($candidates))->startOfDay();
    }

    private function bodySeries(User $user, string $column, Carbon $from, Carbon $to): array
    {
        return BodyMetric::where('user_id', $user->id)
            ->whereBetween('recorded_at', [$from->toDateString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->whereNotNull($column)
            ->orderBy('recorded_at')
            ->get()
            ->mapWithKeys(fn (BodyMetric $m) => [
                $m->recorded_at->toDateString() => (float) $m->{$column},
            ])
            ->all();
    }

    private function plannedKcalSeries(User $user, Carbon $from, Carbon $to): array
    {
        $entries = MealPlanEntry::whereHas('mealPlan', fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('planned_on', [$from->toDateString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->with(['recipe.ingredients', 'foodItem'])
            ->get()
            ->groupBy(fn ($e) => $e->planned_on->toDateString());

        $series = [];
        foreach ($entries as $date => $dayEntries) {
            $kcal = 0.0;
            foreach ($dayEntries as $entry) {
                $kcal += $this->entryCalculator->calculate($entry)->energyKcal;
            }
            $series[$date] = round($kcal);
        }

        ksort($series);

        return $series;
    }

    private function deficitSeries(User $user, Carbon $from, Carbon $to): array
    {
        $target = $user->profile?->daily_calorie_target;
        if (! $target) {
            return [];
        }

        $planned = $this->plannedKcalSeries($user, $from, $to);

        return array_map(fn ($kcal) => round($target - $kcal), $planned);
    }

    private function budgetSeries(User $user, Carbon $from, Carbon $to): array
    {
        $series = MealPlanEntry::whereHas('mealPlan', fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('planned_on', [$from->toDateString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->whereNotNull('estimated_cost')
            ->selectRaw('DATE(planned_on) as day, SUM(estimated_cost) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();

        return $series;
    }
}
