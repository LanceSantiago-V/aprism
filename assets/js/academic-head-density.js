(function () {
    'use strict';

    const script = document.currentScript;
    const scope = script ? script.dataset.densityScope || 'standard' : 'standard';
    const height = window.innerHeight || document.documentElement.clientHeight || 0;

    const pageSize = scope === 'fixed-six'
        ? 6
        : scope === 'adviser'
        ? (height >= 1000 ? 6 : height >= 800 ? 5 : 4)
        : scope === 'reports'
            ? (height >= 1000 ? 7 : height >= 880 ? 6 : height >= 740 ? 5 : 4)
            : (height >= 1000 ? 8 : height >= 880 ? 7 : height >= 740 ? 6 : 5);

    const params = new URLSearchParams(window.location.search);
    const current = Number.parseInt(params.get('per_page') || '', 10);

    if (current === pageSize) {
        return;
    }

    params.set('per_page', String(pageSize));
    params.set('page', '1');
    window.location.replace(window.location.pathname + '?' + params.toString());
}());
