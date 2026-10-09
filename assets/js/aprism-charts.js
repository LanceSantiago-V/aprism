(function () {
    'use strict';

    const palette = ['#075aa2', '#16a3a3', '#8b5cf6', '#f59e0b', '#e94560', '#64748b'];
    const semanticColors = {
        submitted: '#075aa2', returned: '#f59e0b', accepted: '#16a3a3', closed: '#64748b',
        open: '#075aa2', active: '#8b5cf6', scheduled: '#f59e0b', completed: '#16a3a3',
        cancelled: '#64748b', overdue: '#e94560'
    };

    function parseValues(canvas, name) {
        try {
            return JSON.parse(canvas.dataset[name] || '[]');
        } catch (error) {
            console.error('APRISM chart data error:', error);
            return [];
        }
    }

    function percent(value, total) {
        return total ? `${Math.round((value / total) * 100)}%` : '0%';
    }

    function initChart(canvas) {
        if (!window.Chart || canvas.dataset.chartReady === 'true') return;
        const labels = parseValues(canvas, 'chartLabels');
        const values = parseValues(canvas, 'chartValues').map(Number);
        const type = canvas.dataset.chartType || 'doughnut';
        const showLegend = type !== 'bar' && !canvas.closest('.guidance-report-trend-panel');
        const total = values.reduce((sum, value) => sum + value, 0);

        canvas.dataset.chartReady = 'true';
        const centerLabelPlugin = {
            id: 'aprismCenterLabel',
            afterDraw(chart) {
                if (type !== 'doughnut' || !total) return;
                const area = chart.chartArea;
                const x = (area.left + area.right) / 2;
                const y = (area.top + area.bottom) / 2 - 5;
                const context = chart.ctx;
                context.save();
                context.textAlign = 'center';
                context.fillStyle = '#7890ad';
                context.font = '700 9px Inter, sans-serif';
                context.fillText('TOTAL', x, y - 3);
                context.fillStyle = '#06396b';
                context.font = '800 20px Inter, sans-serif';
                context.fillText(String(total), x, y + 19);
                context.restore();
            }
        };
        new Chart(canvas, {
            type,
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map((label, index) => semanticColors[String(label).toLowerCase()] || palette[index % palette.length]),
                    borderColor: '#ffffff',
                    label: type === 'line' ? 'Referrals' : undefined,
                    borderWidth: type === 'line' ? 0 : 3,
                    borderRadius: type === 'bar' ? 6 : 0,
                    tension: 0.35,
                    fill: type === 'line',
                    pointRadius: type === 'line' ? 3 : 0,
                    pointHoverRadius: type === 'line' ? 5 : 0
                }]
            },
            plugins: type === 'doughnut' ? [centerLabelPlugin] : [],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 350 },
                plugins: {
                    legend: {
                        display: showLegend,
                        position: 'bottom',
                        labels: { color: '#536b84', usePointStyle: true, boxWidth: 8, padding: 16, font: { size: 11, weight: '700' } }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = Number(context.raw || 0);
                                return ` ${context.label}: ${value} (${percent(value, total)})`;
                            }
                        }
                    }
                },
                cutout: type === 'doughnut' ? '68%' : undefined,
                scales: type === 'bar' ? {
                    x: { beginAtZero: true, grid: { color: '#edf2f7' }, ticks: { color: '#7890ad', precision: 0 } },
                    y: { grid: { display: false }, ticks: { color: '#536b84', font: { weight: '700' } } }
                } : type === 'line' ? {
                    x: { grid: { display: false }, ticks: { color: '#7890ad' } },
                    y: { beginAtZero: true, grid: { color: '#edf2f7' }, ticks: { color: '#7890ad', precision: 0 } }
                } : undefined
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-aprism-chart]').forEach(initChart);
    }

    document.addEventListener('DOMContentLoaded', init);
    window.AprismCharts = { init };
}());
