(() => {
    const Chart = window.Chart;
    if (!Chart) return;

    if (window.ChartZoom) {
        Chart.register(window.ChartZoom);
        Chart.defaults.set('plugins.zoom', {
            zoom: {
                drag: {
                    enabled: true,
                    backgroundColor: 'rgba(37, 99, 235, 0.16)',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                },
                mode: 'x',
                onZoomComplete: ({ chart }) => {
                    // History charts also navigate on click; don't treat a drag as a point click.
                    chart.$resourceMonitorIgnoreClickUntil = Date.now() + 350;
                },
            },
        });
    }

    // Keep Chart.js instances out of Alpine's reactive proxy graph.
    window.resourceMonitorCharts = window.resourceMonitorCharts || Object.create(null);

    function clonePoint(point) {
        if (Array.isArray(point)) return [...point];
        if (point && typeof point === 'object') return { ...point };
        return point;
    }

    function cloneChartData(data) {
        if (!data || typeof data !== 'object') return data;
        return {
            ...data,
            labels: Array.isArray(data.labels) ? data.labels.map(clonePoint) : data.labels,
            datasets: Array.isArray(data.datasets)
                ? data.datasets.map((dataset) => ({
                    ...dataset,
                    data: Array.isArray(dataset.data) ? dataset.data.map(clonePoint) : dataset.data,
                }))
                : data.datasets,
        };
    }

    // Update charts in place so periodic history refreshes preserve zoom state.
    window.resourceMonitorUpsertChart = (key, canvas, config) => {
        const existing = window.resourceMonitorCharts[key];
        if (!canvas) return existing || null;

        const safeConfig = { ...config, data: cloneChartData(config.data) };
        if (existing && existing.canvas === canvas) {
            existing.data = safeConfig.data;
            existing.options = safeConfig.options;
            existing.update('none');
            return existing;
        }

        if (existing) existing.destroy();
        const instance = new Chart(canvas, safeConfig);
        window.resourceMonitorCharts[key] = instance;
        return instance;
    };

    window.resourceMonitorDestroyChart = (key) => {
        const instance = window.resourceMonitorCharts[key];
        if (instance) instance.destroy();
        delete window.resourceMonitorCharts[key];
    };

    window.resetChartZoom = (canvasId) => {
        const instance = Chart.getChart(canvasId);
        if (instance && typeof instance.resetZoom === 'function') instance.resetZoom();
    };

    window.toggleChartFullscreen = (button, canvasId) => {
        const card = button.closest('.rm-chart-card');
        if (!card) return;

        const isFullscreen = card.classList.toggle('rm-chart-card--fullscreen');
        button.textContent = isFullscreen ? 'Exit full screen' : 'Full screen';
        button.setAttribute('aria-pressed', String(isFullscreen));
        requestAnimationFrame(() => Chart.getChart(canvasId)?.resize());
    };

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('.rm-chart-card--fullscreen').forEach((card) => {
            card.classList.remove('rm-chart-card--fullscreen');
            const button = card.querySelector('[data-chart-fullscreen]');
            if (button) {
                button.textContent = 'Full screen';
                button.setAttribute('aria-pressed', 'false');
            }
            const canvas = card.querySelector('canvas');
            if (canvas) requestAnimationFrame(() => Chart.getChart(canvas)?.resize());
        });
    });
})();
