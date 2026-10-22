(function () {
    'use strict';

    function initGradePeriodFilter() {
        const select = document.getElementById('teacher-grade-period');
        const progressRows = Array.from(document.querySelectorAll('.teacher-dashboard-grade-class'));
        if (!select || progressRows.length === 0) return;

        const update = () => {
            progressRows.forEach((row) => {
                row.hidden = row.dataset.period !== select.value;
            });
        };

        select.addEventListener('change', update);
        update();
    }

    function initReferralReasonsChart() {
        const canvas = document.getElementById('teacher-referral-reasons-chart');
        if (!canvas || !window.Chart) return;

        let labels = [];
        let values = [];
        let colors = [];
        try {
            labels = JSON.parse(canvas.dataset.labels || '[]');
            values = JSON.parse(canvas.dataset.values || '[]').map(Number);
            colors = JSON.parse(canvas.dataset.colors || '[]');
        } catch (error) {
            return;
        }
        if (labels.length === 0 || values.length !== labels.length) return;

        new Chart(canvas, {
            type: 'polarArea',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        ticks: { display: false, stepSize: 1 },
                        grid: { color: '#edf2f7' },
                        angleLines: { color: '#edf2f7' }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: window.matchMedia('(max-width: 520px)').matches ? 'bottom' : 'right',
                        labels: {
                            color: '#536b84',
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: window.matchMedia('(max-width: 520px)').matches ? 7 : 11,
                            font: { size: window.matchMedia('(max-width: 520px)').matches ? 9 : 11, weight: '700' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => ` ${context.label}: ${Number(context.raw || 0)}`
                        }
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initGradePeriodFilter();
        initReferralReasonsChart();
    });
}());
