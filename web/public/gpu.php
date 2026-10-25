<?php
$pageTitle = 'GPU Monitor';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .gpu-chart-card--fullscreen {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        flex-direction: column;
        width: 100vw;
        height: 100vh;
        padding: 1.5rem;
        overflow: auto;
        border-radius: 0;
        background: #fff;
    }
    @supports (height: 100dvh) {
        .gpu-chart-card--fullscreen { height: 100dvh; }
    }
    .dark .gpu-chart-card--fullscreen { background: #1f2937; }
    .gpu-chart-card--fullscreen .gpu-chart-area {
        flex: 1 1 auto;
        min-height: 0;
        height: auto !important;
    }
</style>

<div x-data="gpuMonitor()" x-init="init()" @keydown.escape.window="exitFullscreen()" class="space-y-4">
    <div class="mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">GPU Monitor</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Live readings refresh every 10 seconds; history charts refresh every minute.
            </p>
        </div>
        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="updatedAt ? 'Updated ' + updatedAt : ''"></span>
    </div>

    <div x-show="error" x-cloak role="alert"
         class="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200"
         x-text="error"></div>

    <div x-show="loading && devices.length === 0" class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow dark:bg-gray-800 dark:text-gray-400">
        Loading GPU telemetry...
    </div>

    <div x-show="!loading && devices.length === 0" x-cloak
         class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center shadow-sm dark:border-gray-600 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">No GPU samples yet</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            The collector has not recorded a GPU reading. Check that the collector is active and that NVIDIA telemetry is enabled.
        </p>
    </div>

    <template x-for="gpu in devices" :key="gpu.id">
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-1">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white" x-text="'GPU ' + gpu.id + ' — ' + gpu.name"></h2>
                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="'Last sample: ' + gpu.timestamp"></p>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">GPU Utilization</h3>
                    <div class="mb-3 text-3xl font-bold text-gray-900 dark:text-white" x-text="formatMetric(gpu.utilization_percent, '%')"></div>
                    <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-walmart-blue transition-all" :style="'width: ' + barWidth(gpu.utilization_percent)"></div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">GPU Memory</h3>
                    <div class="text-xl font-bold text-gray-900 dark:text-white"
                         x-text="formatMetric(gpu.memory_used_mb, 'MiB', 0) + ' / ' + formatMetric(gpu.memory_total_mb, 'MiB', 0)"></div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="formatMetric(gpu.memory_percent, '%') + ' used'"></p>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-walmart-blue transition-all" :style="'width: ' + barWidth(gpu.memory_percent)"></div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">GPU Temperature</h3>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white" x-text="formatTemperature(gpu.temperature_celsius)"></div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Reported by the NVIDIA driver</p>
                </div>

                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">GPU Power</h3>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white" x-text="formatMetric(gpu.power_watts, 'W')"></div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Current draw</p>
                </div>
            </div>
        </section>
    </template>

    <section class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">GPU History</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Utilization, VRAM, temperature, and power draw by GPU. Drag across a chart to zoom; use Reset zoom to restore. <span x-show="historyUpdatedAt" x-text="'Updated ' + historyUpdatedAt"></span></p>
            </div>
            <div class="flex items-center gap-3">
                <label for="gpu-history-range" class="text-sm text-gray-600 dark:text-gray-300">Range</label>
                <select id="gpu-history-range" x-model="range" @change="loadHistory()"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="1h">Last hour</option>
                    <option value="6h">Last 6 hours</option>
                    <option value="24h">Last 24 hours</option>
                    <option value="7d">Last 7 days</option>
                    <option value="30d">Last 30 days</option>
                    <option value="1year">Last year</option>
                </select>
            </div>
        </div>

        <div x-show="historyError" x-cloak role="alert"
             class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-gray-600 dark:bg-gray-700 dark:text-amber-200"
             x-text="historyError"></div>
        <div x-show="historyLoading" class="mb-3 text-xs text-gray-500 dark:text-gray-400">Loading history...</div>
        <div x-show="!historyLoading && !historyError && historyDevices.length === 0" x-cloak
             class="mb-4 rounded-md bg-gray-50 p-3 text-sm text-gray-600 dark:bg-gray-700 dark:text-gray-300">
            No GPU history is available for this range yet. Longer ranges fill as rollup data accumulates.
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="gpu-chart-card rounded-md border border-gray-200 p-4 dark:border-gray-700" :class="{ 'gpu-chart-card--fullscreen': fullscreenChart === 'gpuUtilizationChart' }">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">GPU Utilization (%)</h3>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" @click="resetZoom('utilization')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button>
                        <button type="button" @click="toggleFullscreen('gpuUtilizationChart')" :aria-pressed="fullscreenChart === 'gpuUtilizationChart'" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" x-text="fullscreenChart === 'gpuUtilizationChart' ? 'Exit full screen' : 'Full screen'"></button>
                    </div>
                </div>
                <div class="gpu-chart-area h-64"><canvas id="gpuUtilizationChart"></canvas></div>
            </div>
            <div class="gpu-chart-card rounded-md border border-gray-200 p-4 dark:border-gray-700" :class="{ 'gpu-chart-card--fullscreen': fullscreenChart === 'gpuMemoryChart' }">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">VRAM Used (MiB)</h3>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" @click="resetZoom('memory')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button>
                        <button type="button" @click="toggleFullscreen('gpuMemoryChart')" :aria-pressed="fullscreenChart === 'gpuMemoryChart'" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" x-text="fullscreenChart === 'gpuMemoryChart' ? 'Exit full screen' : 'Full screen'"></button>
                    </div>
                </div>
                <div class="gpu-chart-area h-64"><canvas id="gpuMemoryChart"></canvas></div>
            </div>
            <div class="gpu-chart-card rounded-md border border-gray-200 p-4 dark:border-gray-700" :class="{ 'gpu-chart-card--fullscreen': fullscreenChart === 'gpuTemperatureChart' }">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">GPU Temperature (°F)</h3>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" @click="resetZoom('temperature')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button>
                        <button type="button" @click="toggleFullscreen('gpuTemperatureChart')" :aria-pressed="fullscreenChart === 'gpuTemperatureChart'" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" x-text="fullscreenChart === 'gpuTemperatureChart' ? 'Exit full screen' : 'Full screen'"></button>
                    </div>
                </div>
                <div class="gpu-chart-area h-64"><canvas id="gpuTemperatureChart"></canvas></div>
            </div>
            <div class="gpu-chart-card rounded-md border border-gray-200 p-4 dark:border-gray-700" :class="{ 'gpu-chart-card--fullscreen': fullscreenChart === 'gpuPowerChart' }">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">GPU Power Draw (W)</h3>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" @click="resetZoom('power')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button>
                        <button type="button" @click="toggleFullscreen('gpuPowerChart')" :aria-pressed="fullscreenChart === 'gpuPowerChart'" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" x-text="fullscreenChart === 'gpuPowerChart' ? 'Exit full screen' : 'Full screen'"></button>
                    </div>
                </div>
                <div class="gpu-chart-area h-64"><canvas id="gpuPowerChart"></canvas></div>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.2.0/dist/chartjs-plugin-zoom.min.js"></script>
<script>
if (window.Chart && window.ChartZoom) {
    window.Chart.register(window.ChartZoom);
}

function gpuMonitor() {
    return {
        devices: [],
        historyDevices: [],
        range: '24h',
        updatedAt: '',
        loading: true,
        historyLoading: false,
        error: '',
        historyError: '',
        historyUpdatedAt: '',
        refreshTimer: null,
        historyTimer: null,
        charts: {},
        fullscreenChart: null,

        init() {
            this.refreshLive();
            this.loadHistory();
            this.refreshTimer = setInterval(() => this.refreshLive(), 10000);
            this.historyTimer = setInterval(() => this.loadHistory(), 60000);
        },

        resetZoom(key) {
            const chart = this.charts[key];
            if (chart && typeof chart.resetZoom === 'function') chart.resetZoom();
        },

        toggleFullscreen(canvasId) {
            this.fullscreenChart = this.fullscreenChart === canvasId ? null : canvasId;
            this.$nextTick(() => {
                requestAnimationFrame(() => Object.values(this.charts).forEach((chart) => chart.resize()));
            });
        },

        exitFullscreen() {
            if (!this.fullscreenChart) return;
            this.fullscreenChart = null;
            this.$nextTick(() => {
                requestAnimationFrame(() => Object.values(this.charts).forEach((chart) => chart.resize()));
            });
        },

        async refreshLive() {
            this.loading = true;
            this.error = '';
            try {
                const response = await fetch('../api/gpu_api.php', { cache: 'no-store', headers: { 'Accept': 'application/json' } });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Could not load GPU telemetry.');
                }
                this.devices = result.devices || [];
                this.updatedAt = result.updated_at || '';
            } catch (error) {
                this.error = error.message || 'Could not load GPU telemetry.';
            } finally {
                this.loading = false;
            }
        },

        async loadHistory() {
            if (this.historyLoading) return;
            this.historyLoading = true;
            this.historyError = '';
            try {
                const url = '../api/history_api.php?range=' + encodeURIComponent(this.range);
                const response = await fetch(url, { cache: 'no-store', headers: { 'Accept': 'application/json' } });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Could not load GPU history.');
                }
                this.historyDevices = (result.data && result.data.gpu) ? result.data.gpu : [];
                this.historyUpdatedAt = new Date().toLocaleTimeString();
                this.renderCharts();
            } catch (error) {
                this.historyError = error.message || 'Could not load GPU history.';
            } finally {
                this.historyLoading = false;
            }
        },

        formatMetric(value, unit, decimals = 1) {
            if (value === null || value === undefined || value === '') return 'N/A';
            const number = Number(value);
            return Number.isFinite(number) ? number.toFixed(decimals) + ' ' + unit : 'N/A';
        },

        formatTemperature(value) {
            if (value === null || value === undefined || value === '') return 'N/A';
            const celsius = Number(value);
            return Number.isFinite(celsius) ? ((celsius * 9 / 5) + 32).toFixed(1) + ' °F' : 'N/A';
        },

        barWidth(value) {
            const number = Number(value);
            if (!Number.isFinite(number)) return '0%';
            return Math.min(100, Math.max(0, number)) + '%';
        },

        renderCharts() {
            const devices = this.historyDevices;
            const labels = devices.length ? (devices[0].timestamps || []) : [];
            const dark = document.documentElement.classList.contains('dark');
            const textColor = dark ? '#d1d5db' : '#4b5563';
            const gridColor = dark ? 'rgba(156, 163, 175, 0.18)' : 'rgba(156, 163, 175, 0.24)';
            const palette = ['#2563eb', '#7c3aed', '#059669', '#ea580c'];
            let maxMemoryUsed = 0;
            let maxUtilization = 0;
            devices.forEach((gpu) => {
                (gpu.memory_used_mb || []).forEach((value) => {
                    const number = Number(value);
                    if (Number.isFinite(number)) maxMemoryUsed = Math.max(maxMemoryUsed, number);
                });
                (gpu.utilization || []).forEach((value) => {
                    const number = Number(value);
                    if (Number.isFinite(number)) maxUtilization = Math.max(maxUtilization, number);
                });
            });
            const memoryMax = maxMemoryUsed > 0 ? Math.max(10, Math.ceil(maxMemoryUsed * 1.2 / 10) * 10) : 10;
            const utilizationMax = maxUtilization > 0 ? Math.min(100, Math.max(10, Math.ceil(maxUtilization * 1.2 / 10) * 10)) : 10;

            const specs = [
                { key: 'utilization', canvas: 'gpuUtilizationChart', field: 'utilization', label: 'Utilization', unit: '%', max: utilizationMax },
                { key: 'memory', canvas: 'gpuMemoryChart', field: 'memory_used_mb', label: 'VRAM used', unit: 'MiB', max: memoryMax },
                { key: 'temperature', canvas: 'gpuTemperatureChart', field: 'temperature_celsius', label: 'Temperature', unit: '°F', max: undefined },
                { key: 'power', canvas: 'gpuPowerChart', field: 'power_watts', label: 'Power draw', unit: 'W', max: undefined },
            ];

            specs.forEach((spec) => {
                const canvas = document.getElementById(spec.canvas);
                if (!canvas || !window.Chart) return;
                const previousChart = this.charts[spec.key];
                if (previousChart && previousChart.canvas !== canvas) {
                    previousChart.destroy();
                    delete this.charts[spec.key];
                }

                const datasets = devices.map((gpu, index) => ({
                    label: 'GPU ' + gpu.id + ' — ' + gpu.name,
                    data: spec.field === 'temperature_celsius'
                        ? (gpu[spec.field] || []).map((value) => value === null ? null : (Number(value) * 9 / 5) + 32)
                        : (gpu[spec.field] || []),
                    borderColor: palette[index % palette.length],
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    tension: 0.3,
                    spanGaps: true,
                }));

                const config = {
                    type: 'line',
                    data: { labels, datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { labels: { color: textColor } },
                            zoom: {
                                zoom: {
                                    drag: {
                                        enabled: true,
                                        backgroundColor: 'rgba(37, 99, 235, 0.16)',
                                        borderColor: '#2563eb',
                                        borderWidth: 1,
                                    },
                                    mode: 'x',
                                },
                            },
                        },
                        scales: {
                            x: {
                                ticks: { color: textColor, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
                                grid: { color: gridColor },
                            },
                            y: {
                                beginAtZero: true,
                                ...(spec.max !== undefined ? { max: spec.max } : {}),
                                ticks: { color: textColor },
                                grid: { color: gridColor },
                                title: { display: true, text: spec.unit, color: textColor },
                            },
                        },
                    },
                };

                const chart = this.charts[spec.key];
                if (chart) {
                    chart.data = config.data;
                    chart.options = config.options;
                    chart.update('none');
                } else {
                    this.charts[spec.key] = new Chart(canvas, config);
                }
            });
        },

        destroy() {
            if (this.refreshTimer) clearInterval(this.refreshTimer);
            if (this.historyTimer) clearInterval(this.historyTimer);
            Object.values(this.charts).forEach((chart) => chart.destroy());
        },
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
