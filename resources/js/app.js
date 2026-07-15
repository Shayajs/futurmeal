import './bootstrap';
import Chart from 'chart.js/auto';

window.Chart = Chart;

document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('[data-chart]').forEach(initChart);
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-chart]').forEach(initChart);
});

// Livewire dispatche cet événement quand les données d'un graphique changent
document.addEventListener('livewire:init', () => {
    window.Livewire.on('refresh-charts', () => {
        // Attendre la fin du morph DOM de Livewire
        setTimeout(() => {
            document.querySelectorAll('[data-chart]').forEach((canvas) => reinitChart(canvas));
        }, 50);
    });
});

function reinitChart(canvas) {
    if (canvas._chartInstance) {
        canvas._chartInstance.destroy();
        canvas._chartInstance = null;
    }
    delete canvas.dataset.chartInitialized;
    initChart(canvas);
}

function initChart(canvas) {
    if (canvas.dataset.chartInitialized) return;
    canvas.dataset.chartInitialized = '1';

    const type = canvas.dataset.chart;
    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const datasets = JSON.parse(canvas.dataset.datasets || '[]');
    const isMixed = canvas.dataset.mixed === '1';
    const dualAxis = canvas.dataset.dualAxis === '1';

    const options = {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { labels: { color: '#FFFFFF', boxWidth: 12 } },
            tooltip: {
                backgroundColor: 'rgba(11, 15, 25, 0.95)',
                borderColor: 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                titleColor: '#FFFFFF',
                bodyColor: '#8B95A5',
            },
        },
    };

    if (type !== 'doughnut') {
        options.scales = {
            x: { ticks: { color: '#8B95A5', maxTicksLimit: 12 }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: {
                ticks: { color: '#8B95A5' },
                grid: { color: 'rgba(255,255,255,0.05)' },
                position: 'left',
            },
        };

        if (dualAxis) {
            options.scales.y1 = {
                ticks: { color: '#FF6D00' },
                grid: { drawOnChartArea: false },
                position: 'right',
            };
        }
    }

    canvas._chartInstance = new Chart(canvas, {
        type: isMixed ? 'bar' : type,
        data: { labels, datasets },
        options,
    });
}
