(function () {
    'use strict';

    const canvas = document.getElementById('academic-head-attendance-chart');
    if (!canvas || !window.Chart) return;

    let data;
    try {
        data = JSON.parse(canvas.dataset.attendanceChart || '{}');
    } catch (error) {
        console.error('Academic Head attendance chart data could not be read.', error);
        return;
    }

    if (!Array.isArray(data.labels) || data.labels.length === 0) return;

    const chart = new window.Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Present',
                    data: data.present || [],
                    backgroundColor: '#20a574',
                    borderColor: '#16845f',
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 28
                },
                {
                    label: 'Late',
                    data: data.late || [],
                    backgroundColor: '#e9a22b',
                    borderColor: '#bd7b11',
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 28
                },
                {
                    label: 'Absent',
                    data: data.absent || [],
                    backgroundColor: '#d65361',
                    borderColor: '#b43c4b',
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 28
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 220 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    displayColors: true,
                    callbacks: {
                        title(items) {
                            return items.length ? items[0].label : '';
                        },
                        label(context) {
                            return ` ${context.dataset.label}: ${Number(context.raw || 0)} records`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    title: {
                        display: true,
                        text: 'Recorded session date',
                        color: '#637487',
                        font: { family: 'Inter, sans-serif', size: 10, weight: '700' },
                        padding: { top: 8 }
                    },
                    ticks: {
                        color: '#637487',
                        autoSkip: true,
                        maxRotation: 35,
                        minRotation: 0,
                        font: { family: 'Inter, sans-serif', size: 10, weight: '600' }
                    }
                },
                y: {
                    beginAtZero: true,
                    grace: '8%',
                    title: {
                        display: true,
                        text: 'Attendance records',
                        color: '#637487',
                        font: { family: 'Inter, sans-serif', size: 10, weight: '700' },
                        padding: { bottom: 6 }
                    },
                    grid: { color: 'rgba(148, 163, 184, .2)', drawBorder: false },
                    ticks: {
                        color: '#637487',
                        precision: 0,
                        maxTicksLimit: 6,
                        font: { family: 'Inter, sans-serif', size: 10 }
                    }
                }
            }
        }
    });

    let focusedIndex = 0;
    function showFocusedSession(index) {
        focusedIndex = Math.max(0, Math.min(data.labels.length - 1, index));
        const activeElements = chart.data.datasets.map((_, datasetIndex) => ({
            datasetIndex,
            index: focusedIndex
        }));
        const x = chart.scales.x.getPixelForValue(focusedIndex);
        const y = chart.chartArea.top + 12;
        chart.setActiveElements(activeElements);
        chart.tooltip.setActiveElements(activeElements, { x, y });
        chart.update('none');
    }

    function clearFocusedSession() {
        chart.setActiveElements([]);
        chart.tooltip.setActiveElements([], { x: 0, y: 0 });
        chart.update('none');
    }

    canvas.addEventListener('focus', () => showFocusedSession(0));
    canvas.addEventListener('blur', clearFocusedSession);
    canvas.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight' && focusedIndex < data.labels.length - 1) {
            event.preventDefault();
            showFocusedSession(focusedIndex + 1);
        } else if (event.key === 'ArrowLeft' && focusedIndex > 0) {
            event.preventDefault();
            showFocusedSession(focusedIndex - 1);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            clearFocusedSession();
        }
    });
}());
