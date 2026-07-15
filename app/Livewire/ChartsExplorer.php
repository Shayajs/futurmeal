<?php

namespace App\Livewire;

use App\Services\Charts\ChartSeriesService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ChartsExplorer extends Component
{
    private const PALETTE = ['#00FF88', '#FF6D00', '#4A90D9', '#E8C547', '#B57EDC', '#FF5C8A', '#6EE7B7'];

    public array $selected = ['weight_kg'];

    public string $period = '30d';

    public function updated(): void
    {
        $this->dispatch('refresh-charts');
    }

    public function render(ChartSeriesService $charts)
    {
        $user = Auth::user();
        $available = $charts->available();

        $selected = array_values(array_intersect($this->selected, array_keys($available)));
        [$from, $to] = $charts->periodRange($user, $this->period);

        $rawSeries = [];
        foreach ($selected as $key) {
            $rawSeries[$key] = $charts->series($user, $key, $from, $to);
        }

        $labels = collect($rawSeries)
            ->flatMap(fn ($serie) => array_keys($serie))
            ->unique()
            ->sort()
            ->values();

        $displayLabels = $labels->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m/y'))->all();

        // Attribution des axes : première unité → axe gauche, unités différentes → axe droit
        $leftUnit = null;
        $datasets = [];
        $usesRightAxis = false;

        foreach ($selected as $i => $key) {
            $meta = $available[$key];
            $unit = $meta['unit'];

            if ($leftUnit === null) {
                $leftUnit = $unit;
            }

            $axis = $unit === $leftUnit ? 'y' : 'y1';
            if ($axis === 'y1') {
                $usesRightAxis = true;
            }

            $color = self::PALETTE[$i % count(self::PALETTE)];

            $datasets[] = [
                'label' => $meta['label'].($unit ? " ({$unit})" : ''),
                'data' => $labels->map(fn ($d) => $rawSeries[$key][$d] ?? null)->all(),
                'borderColor' => $color,
                'backgroundColor' => $color.'22',
                'tension' => 0.3,
                'spanGaps' => true,
                'pointRadius' => count($labels) > 60 ? 0 : 2,
                'yAxisID' => $axis,
            ];
        }

        $groups = collect($available)
            ->map(fn ($meta, $key) => $meta + ['key' => $key])
            ->groupBy('group');

        return view('livewire.charts-explorer', [
            'groups' => $groups,
            'periods' => ChartSeriesService::PERIODS,
            'chartLabels' => $displayLabels,
            'chartDatasets' => $datasets,
            'usesRightAxis' => $usesRightAxis,
            'hasData' => $labels->isNotEmpty() && ! empty($datasets),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
