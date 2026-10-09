(function () {
    'use strict';

    const dashboard = document.querySelector('.guidance-dashboard-page');
    const dashboardStyles = getComputedStyle(dashboard || document.documentElement);
    const rootStyles = getComputedStyle(document.documentElement);
    const colorToken = (name, fallback) => dashboardStyles.getPropertyValue(name).trim() || fallback;
    const colors = {
        blue: rootStyles.getPropertyValue('--sti-blue').trim() || '#005596',
        purple: colorToken('--guidance-purple', '#7c3aed'),
        teal: colorToken('--guidance-dashboard-teal', '#16a6a3'),
        amber: colorToken('--guidance-amber', '#d97706'),
        coral: colorToken('--guidance-danger', '#e94560'),
        slate: colorToken('--guidance-slate', '#64748b')
    };
    const semanticColors = {
        submitted: colors.blue,
        returned: colors.amber,
        accepted: colors.teal,
        closed: colors.slate,
        open: colors.blue,
        active: colors.purple,
        scheduled: colors.amber,
        completed: colors.teal,
        cancelled: colors.slate
    };
    const fallbackColors = [colors.blue, colors.teal, colors.purple, colors.amber, colors.coral, colors.slate];

    function withAlpha(color, alpha) {
        const hex = color.match(/^#([\da-f]{3}|[\da-f]{6})$/i);
        if (!hex) return `rgba(0, 85, 150, ${alpha})`;
        const digits = hex[1].length === 3 ? [...hex[1]].map((digit) => digit + digit).join('') : hex[1];
        const [red, green, blue] = [0, 2, 4].map((offset) => parseInt(digits.slice(offset, offset + 2), 16));
        return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
    }

    function readJson(canvas, name) {
        try {
            return JSON.parse(canvas.dataset[name] || '[]');
        } catch (error) {
            console.error('Guidance dashboard chart data error:', error);
            return [];
        }
    }

    function chartColors(labels) {
        return labels.map((label, index) => semanticColors[String(label).toLowerCase()] || fallbackColors[index % fallbackColors.length]);
    }

    function createCenterLabelPlugin(total) {
        return {
            id: 'guidanceDashboardCenterLabel',
            afterDraw(chart) {
                if (!total || !chart.chartArea) return;
                const area = chart.chartArea;
                const x = (area.left + area.right) / 2;
                const y = (area.top + area.bottom) / 2;
                const context = chart.ctx;

                context.save();
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.fillStyle = '#7890ad';
                context.font = '700 10px Inter, sans-serif';
                context.fillText('TOTAL', x, y - 10);
                context.fillStyle = '#06396b';
                context.font = '800 22px Inter, sans-serif';
                context.fillText(String(total), x, y + 12);
                context.restore();
            }
        };
    }

    function initializeChart(canvas) {
        if (canvas.dataset.guidanceChartReady === 'true') return;
        if (!window.Chart) {
            console.error('Chart.js is unavailable for the Guidance dashboard charts.');
            return;
        }

        const labels = readJson(canvas, 'guidanceChartLabels');
        const values = readJson(canvas, 'guidanceChartValues').map(Number);
        const type = canvas.dataset.guidanceChartType || 'doughnut';
        const total = values.reduce((sum, value) => sum + value, 0);
        const isDoughnut = type === 'doughnut';
        const seriesColors = chartColors(labels);
        const dataset = isDoughnut ? {
            data: values,
            backgroundColor: seriesColors,
            borderColor: '#ffffff',
            borderWidth: 2,
            hoverOffset: 3
        } : {
            label: 'Referrals',
            data: values,
            borderColor: colors.blue,
            backgroundColor: withAlpha(colors.blue, .12),
            pointBackgroundColor: colors.blue,
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 2,
            tension: .3,
            fill: true
        };

        const instance = new window.Chart(canvas, {
            type,
            data: { labels, datasets: [dataset] },
            plugins: isDoughnut ? [createCenterLabelPlugin(total)] : [],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 350 },
                layout: { padding: isDoughnut ? 2 : { top: 7, right: 8, bottom: 3, left: 3 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const value = Number(context.raw || 0);
                                if (!isDoughnut) return ` Referrals: ${value}`;
                                const share = total ? Math.round((value / total) * 100) : 0;
                                return ` ${context.label}: ${value} (${share}%)`;
                            }
                        }
                    }
                },
                ...(isDoughnut ? {
                    cutout: '68%',
                    radius: '80%'
                } : {
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: '#7890ad',
                                font: { family: 'Inter, sans-serif', size: 11 },
                                maxRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#edf2f7' },
                            ticks: {
                                color: '#7890ad',
                                precision: 0,
                                font: { family: 'Inter, sans-serif', size: 11 }
                            }
                        }
                    }
                })
            }
        });

        canvas.dataset.guidanceChartReady = 'true';
        canvas.dataset.guidanceRenderedType = type;
        if (isDoughnut) {
            const arc = instance.getDatasetMeta(0).data[0];
            if (arc && Number.isFinite(arc.outerRadius)) {
                canvas.dataset.guidanceDoughnutDiameter = String(Math.round(arc.outerRadius * 2));
            }
        }
    }

    function initialize() {
        document.querySelectorAll('[data-guidance-dashboard-chart]').forEach(initializeChart);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
