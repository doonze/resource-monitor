<?php
$pageTitle = 'Historical Data';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<div x-data="historyDashboard()" x-init="init()">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Historical Data</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    View performance trends over time
                </p>
            </div>
            
            <!-- Time Range Selector -->
            <div class="flex items-center space-x-6">
                <!-- Temperature Unit Toggle -->
                <div class="flex items-center space-x-2 pr-4 border-r border-gray-300 dark:border-gray-600">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Temp:</span>
                    <button @click="tempUnit = 'F'; localStorage.setItem('tempUnit', 'F'); loadData();" 
                            :class="tempUnit === 'F' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                            class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                        °F
                    </button>
                    <button @click="tempUnit = 'C'; localStorage.setItem('tempUnit', 'C'); loadData();" 
                            :class="tempUnit === 'C' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                            class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                        °C
                    </button>
                </div>
                
                <!-- Time Range Buttons -->
                <div class="flex items-center space-x-2">
                <button @click="changeRange('1h')" 
                        :class="range === '1h' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    1 Hour
                </button>
                <button @click="changeRange('6h')" 
                        :class="range === '6h' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    6 Hours
                </button>
                <button @click="changeRange('24h')" 
                        :class="range === '24h' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    24 Hours
                </button>
                <button @click="changeRange('7d')" 
                        :class="range === '7d' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    7 Days
                </button>
                <button @click="changeRange('30d')" 
                        :class="range === '30d' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    30 Days
                </button>
                <button @click="changeRange('1year')" 
                        :class="range === '1year' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    1 Year
                </button>

                <!-- Auto-refresh toggle -->
                <div class="pl-3 ml-1 border-l border-gray-300 dark:border-gray-600 flex items-center">
                    <button @click="toggleAutoRefresh()"
                            :title="autoRefresh ? 'Pause auto-refresh' : 'Auto-refresh every 1 min'"
                            :class="autoRefresh
                                ? 'bg-green-500 hover:bg-green-600 text-white'
                                : 'bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600'"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <!-- Play icon -->
                        <svg x-show="!autoRefresh" class="h-4 w-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                        </svg>
                        <!-- Pause icon -->
                        <svg x-show="autoRefresh" class="h-4 w-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span x-show="!autoRefresh" class="hidden sm:inline text-xs">Live</span>
                        <span x-show="autoRefresh" class="hidden sm:inline font-mono text-xs"
                              x-text="'0:' + String(refreshCountdown).padStart(2, '0')"></span>
                    </button>
                </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <svg class="animate-spin h-8 w-8 text-walmart-blue" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
    
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Drag across a chart to zoom the time axis. Reset zoom restores the selected range.</p>

    <!-- Charts Grid -->
    <div x-show="!loading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- CPU Usage Chart -->
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">CPU Usage</h3>
                <div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('cpuChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'cpuChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Full screen</button></div>
            </div>
            <div class="rm-chart-area h-64">
                <canvas id="cpuChart"></canvas>
            </div>
        </div>
        
        <!-- Memory Usage Chart -->
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">Memory Usage</h3>
                <div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('memoryChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'memoryChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Full screen</button></div>
            </div>
            <div class="rm-chart-area h-64">
                <canvas id="memoryChart"></canvas>
            </div>
        </div>
        
        <!-- Disk I/O Chart -->
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">Disk I/O</h3>
                <div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('diskChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'diskChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Full screen</button></div>
            </div>
            <div class="rm-chart-area h-64">
                <canvas id="diskChart"></canvas>
            </div>
        </div>
        
        <!-- Network I/O Chart -->
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">Network I/O</h3>
                <div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('networkChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'networkChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Full screen</button></div>
            </div>
            <div class="rm-chart-area h-64">
                <canvas id="networkChart"></canvas>
            </div>
        </div>
        
        <!-- Temperature Chart -->
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">CPU Temperature</h3>
                <div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('tempChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'tempChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Full screen</button></div>
            </div>
            <div class="rm-chart-area h-64">
                <canvas id="tempChart"></canvas>
            </div>
        </div>

        <!-- GPU Charts -->
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2"><h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">GPU Utilization (%)</h3><div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('gpuUtilizationChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'gpuUtilizationChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Full screen</button></div></div>
            <div class="rm-chart-area h-64"><canvas id="gpuUtilizationChart"></canvas></div>
        </div>
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2"><h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">GPU Memory (MiB)</h3><div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('gpuMemoryChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'gpuMemoryChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Full screen</button></div></div>
            <div class="rm-chart-area h-64"><canvas id="gpuMemoryChart"></canvas></div>
        </div>
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2"><h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">GPU Temperature (°C)</h3><div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('gpuTemperatureChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'gpuTemperatureChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Full screen</button></div></div>
            <div class="rm-chart-area h-64"><canvas id="gpuTemperatureChart"></canvas></div>
        </div>
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2"><h3 class="text-lg font-medium text-gray-900 dark:text-white mb-0">GPU Power (W)</h3><div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('gpuPowerChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'gpuPowerChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Full screen</button></div></div>
            <div class="rm-chart-area h-64"><canvas id="gpuPowerChart"></canvas></div>
        </div>

        <!-- TCP Connections Chart -->
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">TCP Connections</h3>
                <div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('connectionsChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'connectionsChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Full screen</button></div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Active established TCP connections system-wide. Compare with Network I/O to check correlation.</p>
            <div class="rm-chart-area h-64">
                <canvas id="connectionsChart"></canvas>
            </div>
        </div>

        <!-- Overview / Correlation Chart — full width -->
        <div class="rm-chart-card bg-white dark:bg-gray-800 shadow rounded-lg p-6 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Overview</h3>
                <div class="flex shrink-0 gap-2"><button type="button" onclick="resetChartZoom('overviewChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Reset zoom</button><button type="button" data-chart-fullscreen aria-pressed="false" onclick="toggleChartFullscreen(this, 'overviewChart')" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:text-gray-200">Full screen</button></div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">CPU &amp; Memory: actual&nbsp;%. &nbsp;Disk, Network &amp; TCP Conn: % of window peak. &nbsp;Temp: % of 30&ndash;100&deg;C range.</p>
            <div class="rm-chart-area h-64">
                <canvas id="overviewChart"></canvas>
            </div>
        </div>

    </div>

    <!-- Clickable hint -->
    <p x-show="!loading" class="mt-4 text-center text-xs text-gray-400 dark:text-gray-500">
        💡 Click any point on a chart to see the process snapshot at that moment
    </p>

</div>

<script>
function historyDashboard() {
    return {
        range: '<?php echo DEFAULT_TIME_RANGE; ?>',
        loading: true,
        tempUnit: localStorage.getItem('tempUnit') || 'F',
        autoRefresh:      false,
        refreshCountdown: 60,
        _refreshTimer:    null,

        init() {
            this.loadData();

            // Listen for temp unit changes from settings page
            window.addEventListener('storage', (e) => {
                if (e.key === 'tempUnit') {
                    this.tempUnit = e.newValue;
                    this.loadData();
                }
            });

            // Pause auto-refresh ticks while the tab is hidden; reset countdown
            // on return so the user gets a full fresh interval, not a stale one.
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this._clearTimer();
                } else if (this.autoRefresh) {
                    this.refreshCountdown = 60;
                    this._startTimer();
                }
            });
        },

        toggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;
            if (this.autoRefresh) {
                this.refreshCountdown = 60;
                this._startTimer();
            } else {
                this._clearTimer();
            }
        },

        _startTimer() {
            this._clearTimer();
            this._refreshTimer = setInterval(() => {
                // Skip ticks while tab is hidden (belt-and-suspenders alongside
                // the visibilitychange listener above).
                if (document.hidden) return;
                this.refreshCountdown--;
                if (this.refreshCountdown <= 0) {
                    this.refreshCountdown = 60;
                    this.loadData(true); // silent = true, no spinner
                }
            }, 1000);
        },

        _clearTimer() {
            if (this._refreshTimer) {
                clearInterval(this._refreshTimer);
                this._refreshTimer = null;
            }
        },
        
        // Convert Celsius to preferred unit
        convertTemp(celsius) {
            if (this.tempUnit === 'F') {
                return (celsius * 9/5) + 32;
            }
            return celsius;
        },
        
        async changeRange(newRange) {
            this.range = newRange;
            this.loading = true;
            // Reset auto-refresh countdown so the user gets a full interval
            // after manually switching ranges — avoids an immediate double-fetch.
            if (this.autoRefresh) this.refreshCountdown = 60;
            await this.loadData();
        },

        async loadData(silent = false) {
            try {
                const response = await fetch(`../api/history_api.php?range=${this.range}`);
                const result = await response.json();

                if (!silent) {
                    // Normal load: hide spinner and wait for layout before rendering.
                    this.loading = false;
                    await new Promise(resolve => {
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                setTimeout(resolve, 50);
                            });
                        });
                    });
                }

                if (result.success) {
                    this.renderCharts(result.data);
                }
                
            } catch (error) {
                console.error('Error loading historical data:', error);
                this.loading = false;
            }
        },
        
        renderCharts(data) {
            console.log('Rendering charts, checking canvas visibility...');
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(75, 85, 99, 0.3)' : 'rgba(0, 0, 0, 0.1)';
            const textColor = isDark ? 'rgba(229, 231, 235, 1)' : 'rgba(31, 41, 55, 1)';
            
            // Common chart options
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                animation: false, // Disable animations to prevent resize conflicts
                interaction: {
                    mode: 'index',
                    intersect: false,
                },

                // ── Click-through to process snapshot ─────────────────────
                // Clicking any point navigates to Process History > Time View
                // pre-loaded at that exact timestamp.
                onClick: (event, elements, chart) => {
                    if (Date.now() < (chart.$resourceMonitorIgnoreClickUntil || 0)) return;
                    if (!elements.length) return;
                    const idx = elements[0].index;

                    // Label-based charts (CPU, Memory, Disk, Network) store
                    // timestamps in chart.data.labels as "YYYY-MM-DD HH:MM:SS".
                    // {x,y} charts (Temp, Overview) store the timestamp in
                    // each data point's x property.
                    let ts = null;
                    if (chart.data.labels && chart.data.labels.length > 0) {
                        ts = chart.data.labels[idx];
                    } else if (chart.data.datasets.length > 0) {
                        const pt = chart.data.datasets[0].data[idx];
                        if (pt && pt.x) ts = pt.x;
                    }
                    if (!ts) return;

                    // Convert to datetime-local format: "YYYY-MM-DDTHH:MM"
                    const param = String(ts).slice(0, 16).replace(' ', 'T');
                    window.location.href = `processes.php?at=${encodeURIComponent(param)}`;
                },

                onHover: (event, elements) => {
                    event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
                plugins: {
                    legend: {
                        labels: {
                            color: textColor
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                hour: 'HH:mm',
                                day: 'MMM d',
                            }
                        },
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    },
                    y: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    }
                }
            };
            
            // CPU Chart
            const cpuCanvas = document.getElementById('cpuChart');
            console.log('CPU canvas:', cpuCanvas, 'width:', cpuCanvas?.offsetWidth);
            if (cpuCanvas && cpuCanvas.offsetWidth > 0) {
                window.resourceMonitorUpsertChart('history-cpu', cpuCanvas, {
                type: 'line',
                data: {
                    labels: data.timestamps,
                    datasets: [
                        {
                            label: 'Average',
                            data: data.cpu.avg,
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        },
                        {
                            label: 'Max',
                            data: data.cpu.max,
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'transparent',
                            borderDash: [5, 5],
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        ...commonOptions.scales,
                        y: {
                            ...commonOptions.scales.y,
                            min: 0,
                            max: 100,
                            ticks: {
                                ...commonOptions.scales.y.ticks,
                                callback: (value) => value + '%'
                            }
                        }
                    }
                }
            });
            }
            
            // Memory Chart
            const memoryCanvas = document.getElementById('memoryChart');
            if (memoryCanvas && memoryCanvas.offsetWidth > 0) {
                window.resourceMonitorUpsertChart('history-memory', memoryCanvas, {
                type: 'line',
                data: {
                    labels: data.timestamps,
                    datasets: [
                        {
                            label: 'Average',
                            data: data.memory.avg,
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        },
                        {
                            label: 'Max',
                            data: data.memory.max,
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'transparent',
                            borderDash: [5, 5],
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        ...commonOptions.scales,
                        y: {
                            ...commonOptions.scales.y,
                            min: 0,
                            max: 100,
                            ticks: {
                                ...commonOptions.scales.y.ticks,
                                callback: (value) => value + '%'
                            }
                        }
                    }
                }
            });
            }
            
            // Disk I/O Chart
            const diskCanvas = document.getElementById('diskChart');
            if (diskCanvas && diskCanvas.offsetWidth > 0) {
                window.resourceMonitorUpsertChart('history-disk', diskCanvas, {
                type: 'line',
                data: {
                    labels: data.timestamps,
                    datasets: [
                        {
                            label: 'Read',
                            data: data.disk_io.read.map(v => v / 1024 / 1024), // Convert to MB/s
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        },
                        {
                            label: 'Write',
                            data: data.disk_io.write.map(v => v / 1024 / 1024), // Convert to MB/s
                            borderColor: 'rgb(245, 158, 11)',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        ...commonOptions.scales,
                        y: {
                            ...commonOptions.scales.y,
                            min: 0,
                            ticks: {
                                ...commonOptions.scales.y.ticks,
                                callback: (value) => value.toFixed(1) + ' MB/s'
                            }
                        }
                    }
                }
            });
            }
            
            // Network I/O Chart
            const networkCanvas = document.getElementById('networkChart');
            if (networkCanvas && networkCanvas.offsetWidth > 0) {
                window.resourceMonitorUpsertChart('history-network', networkCanvas, {
                type: 'line',
                data: {
                    labels: data.timestamps,
                    datasets: [
                        {
                            label: 'Sent',
                            data: data.network.sent.map(v => v / 1024 / 1024), // Convert to MB/s
                            borderColor: 'rgb(139, 92, 246)',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        },
                        {
                            label: 'Received',
                            data: data.network.recv.map(v => v / 1024 / 1024), // Convert to MB/s
                            borderColor: 'rgb(236, 72, 153)',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    // Network I/O click-through sorts Time View by TCP connections —
                    // the most direct indicator of which process is driving the spike.
                    onClick: (event, elements, chart) => {
                        if (!elements.length) return;
                        const idx = elements[0].index;
                        let ts = null;
                        if (chart.data.labels && chart.data.labels.length > 0) {
                            ts = chart.data.labels[idx];
                        } else if (chart.data.datasets.length > 0) {
                            const pt = chart.data.datasets[0].data[idx];
                            if (pt && pt.x) ts = pt.x;
                        }
                        if (!ts) return;
                        const param = String(ts).slice(0, 16).replace(' ', 'T');
                        window.location.href = `processes.php?at=${encodeURIComponent(param)}&sort=tcp`;
                    },
                    scales: {
                        ...commonOptions.scales,
                        y: {
                            ...commonOptions.scales.y,
                            min: 0,
                            ticks: {
                                ...commonOptions.scales.y.ticks,
                                callback: (value) => value.toFixed(1) + ' MB/s'
                            }
                        }
                    }
                }
            });
            }
            
            // Temperature Chart — two lines: avg and max aggregated across ALL sensors.
            // "Average" = mean of all sensor avgs at each point (overall thermal level).
            // "Max"     = hottest any single sensor got at each point (worst-case core).
            // API returns per-sensor data; we aggregate client-side so the Overview
            // chart can still use the full per-sensor breakdown without a second request.
            if (data.temperature && data.temperature.sensors) {
                const tempCanvas = document.getElementById('tempChart');
                if (tempCanvas && tempCanvas.offsetWidth > 0) {
                    const sensors = data.temperature.sensors;
                    const keys    = Object.keys(sensors);

                    if (keys.length > 0) {
                        // All sensors share the same bucket timestamps.
                        const timestamps = sensors[keys[0]].timestamps;

                        // Average of all sensors' avg at each point.
                        const avgData = timestamps.map((ts, i) => {
                            const vals = keys
                                .map(k => sensors[k].avg[i])
                                .filter(v => v != null && !isNaN(v));
                            if (!vals.length) return null;
                            return this.convertTemp(
                                vals.reduce((a, b) => a + b, 0) / vals.length
                            );
                        });

                        // Max of all sensors' max at each point — hottest any core got.
                        const maxData = timestamps.map((ts, i) => {
                            const vals = keys
                                .map(k => sensors[k].max[i])
                                .filter(v => v != null && !isNaN(v));
                            if (!vals.length) return null;
                            return this.convertTemp(Math.max(...vals));
                        });

                        const unit = this.tempUnit;
                        window.resourceMonitorUpsertChart('history-temperature', tempCanvas, {
                            type: 'line',
                            data: {
                                datasets: [
                                    {
                                        label: 'Average',
                                        data: timestamps.map((ts, i) => ({ x: ts, y: avgData[i] })),
                                        borderColor: 'rgb(239, 68, 68)',
                                        backgroundColor: 'transparent',
                                        fill: false,
                                        tension: 0.4,
                                        borderWidth: 2,
                                        pointRadius: 0,
                                        pointHoverRadius: 4
                                    },
                                    {
                                        label: 'Max',
                                        data: timestamps.map((ts, i) => ({ x: ts, y: maxData[i] })),
                                        borderColor: 'rgb(220, 38, 38)',
                                        backgroundColor: 'transparent',
                                        borderDash: [5, 5],
                                        tension: 0.4,
                                        borderWidth: 2,
                                        pointRadius: 0,
                                        pointHoverRadius: 4
                                    }
                                ]
                            },
                            options: {
                                ...commonOptions,
                                scales: {
                                    ...commonOptions.scales,
                                    y: {
                                        ...commonOptions.scales.y,
                                        ticks: {
                                            ...commonOptions.scales.y.ticks,
                                            callback: (value) => value.toFixed(1) + '°' + unit
                                        }
                                    }
                                }
                            }
                        });
                    }
                }
            }

            // TCP Connections Chart
            const connectionsCanvas = document.getElementById('connectionsChart');
            if (connectionsCanvas && connectionsCanvas.offsetWidth > 0 && data.connections) {
                window.resourceMonitorUpsertChart('history-connections', connectionsCanvas, {
                    type: 'line',
                    data: {
                        labels: data.timestamps,
                        datasets: [{
                            label: 'Established',
                            data: data.connections,
                            borderColor: 'rgb(20, 184, 166)',
                            backgroundColor: 'rgba(20, 184, 166, 0.08)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            spanGaps: true,
                        }]
                    },
                    options: {
                        ...commonOptions,
                        // TCP Connections click-through also sorts by TCP — same intent
                        // as the Network I/O chart, both are network spike investigation tools.
                        onClick: (event, elements, chart) => {
                            if (!elements.length) return;
                            const idx = elements[0].index;
                            let ts = null;
                            if (chart.data.labels && chart.data.labels.length > 0) {
                                ts = chart.data.labels[idx];
                            } else if (chart.data.datasets.length > 0) {
                                const pt = chart.data.datasets[0].data[idx];
                                if (pt && pt.x) ts = pt.x;
                            }
                            if (!ts) return;
                            const param = String(ts).slice(0, 16).replace(' ', 'T');
                            window.location.href = `processes.php?at=${encodeURIComponent(param)}&sort=tcp`;
                        },
                        scales: {
                            ...commonOptions.scales,
                            y: {
                                ...commonOptions.scales.y,
                                min: 0,
                                ticks: {
                                    ...commonOptions.scales.y.ticks,
                                    callback: (value) => Math.round(value),
                                    precision: 0,
                                }
                            }
                        }
                    }
                });
            }

            // GPU telemetry charts share the history range but keep units separate.
            const gpuDevices = data.gpu || [];
            const gpuPalette = [
                'rgb(139, 92, 246)', 'rgb(14, 165, 233)',
                'rgb(234, 88, 12)', 'rgb(22, 163, 74)'
            ];
            const gpuChartSpecs = [
                { key: 'gpuUtilization', canvas: 'gpuUtilizationChart', field: 'utilization', label: 'Utilization', max: 100, unit: '%' },
                { key: 'gpuMemory', canvas: 'gpuMemoryChart', field: 'memory_used_mb', label: 'Memory used', max: null, unit: 'MiB' },
                { key: 'gpuTemperature', canvas: 'gpuTemperatureChart', field: 'temperature_celsius', label: 'Temperature', max: null, unit: '°C' },
                { key: 'gpuPower', canvas: 'gpuPowerChart', field: 'power_watts', label: 'Power', max: null, unit: 'W' },
            ];
            for (const spec of gpuChartSpecs) {
                const canvas = document.getElementById(spec.canvas);
                if (!canvas || canvas.offsetWidth === 0) continue;
                const labels = gpuDevices.length ? gpuDevices[0].timestamps : [];
                const datasets = gpuDevices.map((gpu, index) => ({
                    label: `${gpu.name} (${gpu.id}) ${spec.label}`,
                    data: gpu[spec.field] || [],
                    borderColor: gpuPalette[index % gpuPalette.length],
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    spanGaps: true,
                }));
                window.resourceMonitorUpsertChart(`history-${spec.key}`, canvas, {
                    type: 'line',
                    data: { labels, datasets },
                    options: {
                        ...commonOptions,
                        onClick: () => {},
                        scales: {
                            ...commonOptions.scales,
                            y: {
                                ...commonOptions.scales.y,
                                min: 0,
                                ...(spec.max !== null ? { max: spec.max } : {}),
                                title: { display: true, text: spec.unit, color: textColor },
                            }
                        }
                    }
                });
            }

            // ── Overview / Correlation Chart ──────────────────────────────
            // All metrics normalized to 0-100 so spikes are visually comparable
            // across completely different units on a single timeline.
            // Colors intentionally match their individual chart counterparts.
            const overviewCanvas = document.getElementById('overviewChart');
            if (overviewCanvas && overviewCanvas.offsetWidth > 0) {
                // Normalize an array to 0-100% of its own peak value.
                // Returns 0 for every point if peak is 0 (avoids divide-by-zero).
                const normPeak = (arr) => {
                    const peak = Math.max(...arr.filter(v => v != null && !isNaN(v)), 0);
                    if (peak === 0) return arr.map(() => 0);
                    return arr.map(v => v == null ? null : Math.round((v / peak) * 1000) / 10);
                };

                // Normalize a Celsius value to 0-100% of the 30-100°C hardware range.
                const normCelsius = (c) => Math.max(0, Math.min(100,
                    Math.round(((c - 30) / 70) * 1000) / 10
                ));

                // Disk and network: combine read+write / sent+recv into one signal each,
                // then normalize so the busiest moment in this window = 100%.
                const diskCombined = data.timestamps.map((_, i) =>
                    (data.disk_io.read[i] || 0) + (data.disk_io.write[i] || 0)
                );
                const netCombined = data.timestamps.map((_, i) =>
                    (data.network.sent[i] || 0) + (data.network.recv[i] || 0)
                );
                const diskNorm = normPeak(diskCombined);
                const netNorm  = normPeak(netCombined);
                const connNorm = data.connections ? normPeak(data.connections) : [];

                // Temperature: average all sensors at each point, normalize to
                // 30-100°C range. Renders as a separate dataset using its own
                // timestamps since the temp pipeline runs independently.
                const overviewDatasets = [
                    {
              label: 'CPU',
                        data: data.timestamps.map((ts, i) => ({ x: ts, y: data.cpu.avg[i] })),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'transparent',
                        fill: false, tension: 0.4, borderWidth: 2,
                        pointRadius: 0, pointHoverRadius: 4
                    },
                    {
                        label: 'Memory',
                        data: data.timestamps.map((ts, i) => ({ x: ts, y: data.memory.avg[i] })),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'transparent',
                        fill: false, tension: 0.4, borderWidth: 2,
                        pointRadius: 0, pointHoverRadius: 4
                    },
                    {
                        label: 'Disk I/O',
                        data: data.timestamps.map((ts, i) => ({ x: ts, y: diskNorm[i] })),
                        borderColor: 'rgb(245, 158, 11)',
                        backgroundColor: 'transparent',
                        fill: false, tension: 0.4, borderWidth: 2,
                        pointRadius: 0, pointHoverRadius: 4
                    },
                    {
                        label: 'Network',
                        data: data.timestamps.map((ts, i) => ({ x: ts, y: netNorm[i] })),
                        borderColor: 'rgb(139, 92, 246)',
                        backgroundColor: 'transparent',
                        fill: false, tension: 0.4, borderWidth: 2,
                        pointRadius: 0, pointHoverRadius: 4
                    }
                ];

                // TCP Connections — normalized to peak so it fits the 0-100 scale.
                if (connNorm.length > 0) {
                    overviewDatasets.push({
                        label: 'TCP Conn',
                        data: data.timestamps.map((ts, i) => ({ x: ts, y: connNorm[i] ?? null })),
                        borderColor: 'rgb(20, 184, 166)',
                        backgroundColor: 'transparent',
                        fill: false, tension: 0.4, borderWidth: 2,
                        pointRadius: 0, pointHoverRadius: 4
                    });
                }

                // Add temperature line if sensor data is available
                const tempSensors = (data.temperature && data.temperature.sensors)
                    ? data.temperature.sensors : {};
                const tempKeys = Object.keys(tempSensors);
                if (tempKeys.length > 0) {
                    const refSensor = tempSensors[tempKeys[0]];
                    const normTemps = refSensor.timestamps.map((ts, i) => {
                        const vals = tempKeys
                            .map(k => tempSensors[k].avg[i])
                            .filter(v => v != null && !isNaN(v));
                        if (!vals.length) return null;
                        const meanC = vals.reduce((a, b) => a + b, 0) / vals.length;
                        return normCelsius(meanC);
                    });
                    overviewDatasets.push({
                        label: 'Temperature',
                        data: refSensor.timestamps.map((ts, i) => ({ x: ts, y: normTemps[i] })),
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'transparent',
                        fill: false, tension: 0.4, borderWidth: 2,
                        pointRadius: 0, pointHoverRadius: 4
                    });
                }

                const overviewUnit = this.tempUnit;
                window.resourceMonitorUpsertChart('history-overview', overviewCanvas, {
                    type: 'line',
                    data: { datasets: overviewDatasets },
                    options: {
                        ...commonOptions,
                        scales: {
                            ...commonOptions.scales,
                            y: {
                                ...commonOptions.scales.y,
                                min: 0,
                                max: 100,
                                ticks: {
                                    ...commonOptions.scales.y.ticks,
                                    callback: (value) => value.toFixed(0) + '%'
                                }
                            }
                        }
                    }
                });
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
