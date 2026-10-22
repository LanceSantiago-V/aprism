(function () {
    'use strict';

    const canvas = document.querySelector('[data-guidance-risk-breakdown]');
    if (!canvas || !window.Chart) return;

    const labels = JSON.parse(canvas.dataset.guidanceChartLabels || '[]');
    const values = JSON.parse(canvas.dataset.guidanceChartValues || '[]').map(Number);
    const total = Number(canvas.dataset.guidanceChartTotal || 0);
    if (labels.length !== values.length || total <= 0) return;

    const colors = {
        'Tier 3 High Risk': '#e94560',
        'Tier 2 Elevated': '#d97706',
        'Tier 1 Moderate': '#075aa2'
    };
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const centerLabel = {
        id: 'aprismRiskBreakdownCenterLabel',
        afterDraw(chart) {
            if (!chart.chartArea) return;
            const x = (chart.chartArea.left + chart.chartArea.right) / 2;
            const y = (chart.chartArea.top + chart.chartArea.bottom) / 2;
            const context = chart.ctx;

            context.save();
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillStyle = '#123b5d';
            context.font = '800 25px Inter, sans-serif';
            context.fillText(String(total), x, y - 6);
            context.fillStyle = '#64748b';
            context.font = '700 9px Inter, sans-serif';
            context.fillText('STUDENTS', x, y + 16);
            context.restore();
        }
    };

    new window.Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: labels.map((label) => colors[label] || '#075aa2'),
                borderColor: '#fff',
                borderWidth: 2,
                hoverOffset: 0,
                spacing: 1
            }]
        },
        plugins: [centerLabel],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: prefersReducedMotion ? 0 : 220 },
            cutout: '68%',
            radius: '95%',
            layout: { padding: 2 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const count = Number(context.raw || 0);
                            const share = total > 0 ? Math.round((count / total) * 100) : 0;
                            return ` ${context.label}: ${count} (${share}%)`;
                        }
                    }
                }
            }
        }
    });
}());

(function () {
    'use strict';

    const plot = document.querySelector('[data-guidance-risk-trend]');
    const canvas = plot?.querySelector('[data-guidance-risk-trend-chart]');
    if (!plot || !canvas || !window.Chart) return;

    const byLevel = JSON.parse(plot.dataset.guidanceTrendPeriods || '{}');
    const empty = plot.querySelector('[data-guidance-trend-empty]');
    const coverage = document.querySelector('[data-guidance-trend-coverage]');
    const controls = [...document.querySelectorAll('[data-guidance-trend-scope]')];
    const colors = { 3: '#e94560', 2: '#d97706', 1: '#075aa2' };
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let chart = null;

    function periodsFor(scope) {
        const college = byLevel.College || [];
        const shs = byLevel['Senior High School'] || [];
        if (scope === 'College') return college;
        if (scope === 'Senior High School') return shs;
        return [...college, ...shs].sort((a, b) =>
            a.cutoff.localeCompare(b.cutoff) || a.label.localeCompare(b.label));
    }

    function show(scope) {
        const periods = periodsFor(scope);
        const available = periods.filter((period) => period.completed && period.available);
        const hasBothLevels = scope === 'All'
            && (byLevel.College || []).length > 0
            && (byLevel['Senior High School'] || []).length > 0;
        const labelCounts = periods.reduce((counts, period) => {
            counts[period.label] = (counts[period.label] || 0) + 1;
            return counts;
        }, {});
        const labels = periods.map((period) => {
            const level = (byLevel.College || []).includes(period) ? 'College' : 'SHS';
            return hasBothLevels
                ? `${period.label} · ${level}`
                : labelCounts[period.label] > 1
                    ? `${period.label} · ${period.semester}` : period.label;
        });
        const datasets = [3, 2, 1].map((tier) => ({
            label: `Tier ${tier}`,
            data: periods.map((period) => period.completed && period.available
                ? Number(period.tiers?.[tier] || 0) : null),
            borderColor: colors[tier],
            backgroundColor: colors[tier],
            borderWidth: tier === 1 ? 2.5 : 2,
            pointRadius: 4,
            pointHoverRadius: 5,
            tension: 0.32,
            spanGaps: false,
            fill: false
        }));

        controls.forEach((control) => {
            control.setAttribute('aria-pressed', String(control.dataset.guidanceTrendScope === scope));
        });
        canvas.hidden = available.length === 0;
        empty.hidden = available.length !== 0;
        if (coverage) {
            const partial = available.filter((period) => period.partial);
            const pending = periods.filter((period) => !period.completed);
            const notes = [];
            if (partial.length) {
                notes.push(`Partial historical coverage: ${partial.map((period) =>
                    `${period.label} ${period.captured_attempts} of ${period.eligible_attempts}`
                ).join('; ')} class attempts captured. Counts include captured Students only.`);
            }
            if (available.some((period) => period.reconstructed)) {
                notes.push('Local QA history was reconstructed from dated attendance.');
            }
            if (pending.length) {
                notes.push(`${pending.map((period) => period.label).join(' and ')} pending period close.`);
            }
            if (!periods.length && scope === 'Senior High School') {
                notes.push('No SHS Academic Periods are configured for the active School Year.');
            }
            coverage.textContent = notes.join(' ');
        }
        if (chart) {
            chart.data.labels = labels;
            chart.data.datasets = datasets;
            chart.update();
            if (!canvas.hidden) chart.resize();
            return;
        }
        if (!available.length) return;
        chart = new window.Chart(canvas, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: prefersReducedMotion ? 0 : 240 },
                interaction: { intersect: false, mode: 'index' },
                layout: { padding: { top: 10, right: 12, bottom: 0, left: 4 } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#62788d', font: { size: 10, weight: '600' }, maxRotation: 0 }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#8a9aac', font: { size: 10 } },
                        grid: { color: '#ebf0f5' },
                        border: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            afterTitle(items) {
                                const period = periods[items[0]?.dataIndex];
                                return period ? `Cutoff ${period.cutoff}` : '';
                            },
                            afterBody(items) {
                                const period = periods[items[0]?.dataIndex];
                                if (!period) return '';
                                return period.partial
                                    ? `Partial coverage: ${period.captured_attempts} of ${period.eligible_attempts} class attempts`
                                    : `${period.student_count} Students captured`;
                            }
                        }
                    }
                }
            }
        });
    }

    controls.forEach((control) => control.addEventListener('click', () =>
        show(control.dataset.guidanceTrendScope)));
    show('All');
}());
