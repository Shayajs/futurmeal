<?php

namespace App\Livewire;

use App\Models\BodyMetric;
use App\Services\Body\BodyMetricCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BodyMetricsChart extends Component
{
    public string $recorded_at = '';

    public ?float $weight_kg = null;

    public ?float $body_fat_percent = null;

    public function mount(): void
    {
        $this->recorded_at = today()->toDateString();
    }

    public function save(): void
    {
        $this->validate([
            'recorded_at' => 'required|date|before_or_equal:today',
            'weight_kg' => 'required|numeric|min:30|max:300',
            'body_fat_percent' => 'nullable|numeric|min:3|max:70',
        ], [
            'recorded_at.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ]);

        $calculator = app(BodyMetricCalculator::class);
        $user = Auth::user();
        $height = (float) ($user->profile?->height_cm ?? 170);

        BodyMetric::updateOrCreate(
            [
                'user_id' => $user->id,
                'recorded_at' => \Carbon\Carbon::parse($this->recorded_at)->startOfDay(),
            ],
            [
                'weight_kg' => $this->weight_kg,
                'body_fat_percent' => $this->body_fat_percent,
                'lean_mass_kg' => $this->body_fat_percent !== null
                    ? $calculator->leanMassKg($this->weight_kg, $this->body_fat_percent)
                    : null,
                'bmi' => $calculator->bmi($this->weight_kg, $height),
                'source' => 'manual',
            ]
        );

        session()->flash('metrics-status', 'Mesure du '.\Carbon\Carbon::parse($this->recorded_at)->format('d/m/Y').' enregistrée.');

        $this->weight_kg = null;
        $this->body_fat_percent = null;
        $this->recorded_at = today()->toDateString();
        $this->dispatch('refresh-charts');
    }

    public function delete(int $metricId): void
    {
        BodyMetric::where('user_id', Auth::id())->where('id', $metricId)->delete();
        $this->dispatch('refresh-charts');
    }

    public function render()
    {
        $metrics = Auth::user()->bodyMetrics()->limit(60)->get();
        $chartMetrics = $metrics->sortBy('recorded_at');

        return view('livewire.body-metrics-chart', [
            'recentMetrics' => $metrics->take(10),
            'labels' => $chartMetrics->map(fn ($m) => $m->recorded_at->format('d/m'))->values(),
            'chartDatasets' => [
                [
                    'label' => 'Poids (kg)',
                    'data' => $chartMetrics->pluck('weight_kg')->values(),
                    'borderColor' => '#00FF88',
                    'borderWidth' => 2,
                    'pointRadius' => 2,
                    'tension' => 0.2,
                ],
                [
                    'label' => '% graisse',
                    'data' => $chartMetrics->pluck('body_fat_percent')->values(),
                    'borderColor' => '#8B95A5',
                    'borderWidth' => 2,
                    'pointRadius' => 2,
                    'tension' => 0.2,
                ],
            ],
        ]);
    }
}
