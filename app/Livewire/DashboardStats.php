<?php

namespace App\Livewire;

use App\Services\Dashboard\DashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DashboardStats extends Component
{
    public function render(DashboardService $dashboard)
    {
        $data = $dashboard->build(Auth::user());
        $macros = $data['today']['macros'];
        $pctConsumed = $data['today']['target'] > 0
            ? min(100, round(($data['today']['consumed'] / $data['today']['target']) * 100))
            : 0;

        return view('livewire.dashboard-stats', [
            'data' => $data,
            'pctConsumed' => $pctConsumed,
            'weeklyChartDatasets' => [
                [
                    'label' => 'Kcal',
                    'data' => $data['weekly_calories']['data'],
                    'backgroundColor' => 'rgba(0, 255, 136, 0.7)',
                    'borderRadius' => 4,
                ],
                [
                    'type' => 'line',
                    'label' => 'Objectif',
                    'data' => array_fill(0, 7, $data['weekly_calories']['target']),
                    'borderColor' => 'rgba(255, 109, 0, 0.8)',
                    'borderDash' => [6, 4],
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'fill' => false,
                ],
            ],
            'macroChartDatasets' => [
                [
                    'data' => [
                        $macros['protein_g'],
                        $macros['carbs_g'],
                        $macros['fat_g'],
                    ],
                    'backgroundColor' => ['#00FF88', '#4A90D9', '#FF6D00'],
                    'borderWidth' => 0,
                ],
            ],
            'bodyChartDatasets' => [
                [
                    'label' => 'Poids (kg)',
                    'data' => $data['body']['chart']['weights'],
                    'borderColor' => '#00FF88',
                    'backgroundColor' => 'rgba(0, 255, 136, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Graisse (%)',
                    'data' => $data['body']['chart']['body_fat'],
                    'borderColor' => '#FF6D00',
                    'borderDash' => [4, 4],
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
        ]);
    }
}
