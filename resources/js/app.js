import './bootstrap';
import Chart from 'chart.js/auto';

window.Chart = Chart;

registerServiceWorker();
initPwaInstallPrompt();

document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('[data-chart]').forEach(initChart);
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-chart]').forEach(initChart);
    initPwaInstallPrompt();
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

function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .catch(() => {});
    });
}

function initPwaInstallPrompt() {
    const container = document.querySelector('[data-pwa-install]');

    if (!container || container.dataset.pwaReady === '1') {
        return;
    }

    if (isPwaInstalled()) {
        return;
    }

    container.dataset.pwaReady = '1';

    let deferredPrompt = null;
    const button = container.querySelector('button');

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        container.hidden = false;
    });

    button?.addEventListener('click', async () => {
        if (!deferredPrompt) {
            return;
        }

        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        container.hidden = true;
    });

    window.addEventListener('appinstalled', () => {
        container.hidden = true;
    });
}

function isPwaInstalled() {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true
    );
}
