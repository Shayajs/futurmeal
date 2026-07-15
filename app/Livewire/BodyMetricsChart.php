<?php

namespace App\Livewire;

use App\Enums\BodyMetricSource;
use App\Enums\Gender;
use App\Models\BodyMetric;
use App\Services\Body\BodyMetricCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BodyMetricsChart extends Component
{
    public string $recorded_at = '';

    public ?float $weight_kg = null;

    public string $metric_source = BodyMetricSource::Manual->value;

    public ?float $body_fat_percent = null;

    public ?float $neck_cm = null;

    public ?float $waist_cm = null;

    public ?float $hip_cm = null;

    public function mount(): void
    {
        $this->recorded_at = today()->toDateString();
    }

    public function save(): void
    {
        $rules = [
            'recorded_at' => 'required|date|before_or_equal:today',
            'weight_kg' => 'required|numeric|min:30|max:300',
            'metric_source' => 'required|in:manual,navy,scale',
        ];

        if ($this->metric_source === BodyMetricSource::Manual->value || $this->metric_source === BodyMetricSource::Scale->value) {
            $rules['body_fat_percent'] = 'nullable|numeric|min:3|max:70';
        } else {
            $rules['neck_cm'] = 'required|numeric|min:20|max:80';
            $rules['waist_cm'] = 'required|numeric|min:40|max:200';
            if (Auth::user()->profile?->gender === Gender::Female) {
                $rules['hip_cm'] = 'required|numeric|min:50|max:200';
            }
        }

        $this->validate($rules, [
            'recorded_at.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ]);

        $calculator = app(BodyMetricCalculator::class);
        $user = Auth::user();
        $profile = $user->profile;
        $height = (float) ($profile?->height_cm ?? 170);
        $gender = $profile?->gender ?? Gender::Male;
        $source = BodyMetricSource::from($this->metric_source);

        $bodyFat = $calculator->resolveBodyFat(
            $source,
            $gender,
            $height,
            $this->body_fat_percent,
            $this->neck_cm,
            $this->waist_cm,
            $this->hip_cm,
        );

        BodyMetric::updateOrCreate(
            [
                'user_id' => $user->id,
                'recorded_at' => \Carbon\Carbon::parse($this->recorded_at)->startOfDay(),
            ],
            [
                'weight_kg' => $this->weight_kg,
                'body_fat_percent' => $bodyFat,
                'lean_mass_kg' => $bodyFat !== null
                    ? $calculator->leanMassKg($this->weight_kg, $bodyFat)
                    : null,
                'bmi' => $calculator->bmi($this->weight_kg, $height),
                'source' => $this->metric_source,
                'neck_cm' => $this->metric_source === BodyMetricSource::Navy->value ? $this->neck_cm : null,
                'waist_cm' => $this->metric_source === BodyMetricSource::Navy->value ? $this->waist_cm : null,
                'hip_cm' => $this->metric_source === BodyMetricSource::Navy->value ? $this->hip_cm : null,
            ]
        );

        session()->flash('metrics-status', 'Mesure du '.\Carbon\Carbon::parse($this->recorded_at)->format('d/m/Y').' enregistrée.');

        $this->reset(['weight_kg', 'body_fat_percent', 'neck_cm', 'waist_cm', 'hip_cm']);
        $this->metric_source = BodyMetricSource::Manual->value;
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
            'isFemale' => Auth::user()->profile?->gender === Gender::Female,
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
                [
                    'label' => 'Masse maigre (kg)',
                    'data' => $chartMetrics->pluck('lean_mass_kg')->values(),
                    'borderColor' => '#FF6D00',
                    'borderWidth' => 2,
                    'pointRadius' => 2,
                    'tension' => 0.2,
                    'hidden' => $chartMetrics->whereNotNull('lean_mass_kg')->isEmpty(),
                ],
            ],
        ]);
    }
}
