<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
// Thresholds are now fetched client-side via /api/thresholds_api.php (6-level intelligence system)
?>

<!-- Alerts Banner -->
<div x-data="alertsBanner()" x-init="init()">
    <div x-show="hasAlerts && !loading" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="mb-6 border-l-4 rounded-md shadow-sm"
         :class="bannerBg()">

        <!-- Banner header -->
        <div class="flex items-center justify-between px-4 py-3">
            <button @click="collapsed = !collapsed"
                    class="flex items-center gap-2 text-sm font-semibold"
                    :class="hasCritical ? 'text-red-800 dark:text-red-200' : 'text-amber-800 dark:text-amber-200'">
                <span x-text="hasCritical ? '🚨' : '⚠'"></span>
                <span x-text="visibleAlerts.length + (visibleAlerts.length === 1 ? ' Active Alert' : ' Active Alerts')"></span>
                <span class="text-xs opacity-70" x-text="collapsed ? '▶ show' : '▼ hide'"></span>
            </button>
            <button @click="dismissAll()"
                    class="text-xs underline opacity-60 hover:opacity-100"
                    :class="hasCritical ? 'text-red-700 dark:text-red-300' : 'text-amber-700 dark:text-amber-300'">
                Dismiss all
            </button>
        </div>

        <!-- Alert rows -->
        <div x-show="!collapsed" x-collapse class="px-4 pb-3 space-y-2">
            <template x-for="alert in visibleAlerts" :key="alert.alert_id">
                <div class="flex items-start justify-between gap-3 px-3 py-2 rounded border"
                     :class="rowBg(alert)">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold" :class="titleColor(alert)" x-text="alert.title"></p>
                        <p class="text-xs mt-0.5" :class="msgColor(alert)" x-text="alert.message"></p>
                        <p class="text-xs mt-1 opacity-60" :class="msgColor(alert)"
                           x-text="'Firing for ' + firingDuration(alert)"></p>
                    </div>
                    <button @click="dismiss(alert)" title="Dismiss"
                            class="flex-shrink-0 text-lg leading-none mt-0.5"
                            :class="dismissBtnClass(alert)">&times;</button>
                </div>
            </template>
        </div>
    </div>
</div>

<div x-data="dashboard()" x-init="init()">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">System Dashboard</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Real-time system resource monitoring
        </p>
    </div>
    
    <!-- Collector Status Alert -->
    <div x-show="!collectorHealthy" x-cloak class="mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700 dark:text-red-200">
                    Data collector is not running! Last update: <span x-text="lastHeartbeat"></span>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-5 mb-8">
        <!-- CPU Card -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="text-center">
                    <div class="flex justify-center mb-3">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">CPU Usage</h3>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mb-2" x-text="metrics.cpu + '%'"></div>
                    <div class="flex justify-center">
                        <span x-show="getStatus6('cpu_percent', metrics.cpu) === 'critical'" :class="badgeClass('critical')" class="px-2 py-1 text-xs font-medium rounded-full">✗ Critical</span>
                        <span x-show="getStatus6('cpu_percent', metrics.cpu) === 'warning'"  :class="badgeClass('warning')"  class="px-2 py-1 text-xs font-medium rounded-full">⚠ Warning</span>
                        <span x-show="getStatus6('cpu_percent', metrics.cpu) === 'high'"     :class="badgeClass('high')"     class="px-2 py-1 text-xs font-medium rounded-full">↑↑ High</span>
                        <span x-show="getStatus6('cpu_percent', metrics.cpu) === 'elevated'" :class="badgeClass('elevated')" class="px-2 py-1 text-xs font-medium rounded-full">↑ Elevated</span>
                        <span x-show="getStatus6('cpu_percent', metrics.cpu) === 'normal'"   :class="badgeClass('normal')"   class="px-2 py-1 text-xs font-medium rounded-full">✓ Normal</span>
                        <span x-show="getStatus6('cpu_percent', metrics.cpu) === 'learning'" :class="badgeClass('learning')" class="px-2 py-1 text-xs font-medium rounded-full">◌ Learning</span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-center text-gray-500 dark:text-gray-400">Load: <span x-text="metrics.load1"></span> / <span x-text="metrics.load5"></span> / <span x-text="metrics.load15"></span></div>
                    <div class="text-xs text-center text-gray-400 dark:text-gray-500 mt-1" x-show="thresholdData" x-text="baselineSummary('cpu_percent')"></div>
                </div>
            </div>
        </div>
        
        <!-- Memory Card -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="text-center">
                    <div class="flex justify-center mb-3">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Memory Usage</h3>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mb-2" x-text="metrics.memory + '%'"></div>
                    <div class="flex justify-center">
                        <span x-show="getStatus6('mem_percent', metrics.memory) === 'critical'" :class="badgeClass('critical')" class="px-2 py-1 text-xs font-medium rounded-full">✗ Critical</span>
                        <span x-show="getStatus6('mem_percent', metrics.memory) === 'warning'"  :class="badgeClass('warning')"  class="px-2 py-1 text-xs font-medium rounded-full">⚠ Warning</span>
                        <span x-show="getStatus6('mem_percent', metrics.memory) === 'high'"     :class="badgeClass('high')"     class="px-2 py-1 text-xs font-medium rounded-full">↑↑ High</span>
                        <span x-show="getStatus6('mem_percent', metrics.memory) === 'elevated'" :class="badgeClass('elevated')" class="px-2 py-1 text-xs font-medium rounded-full">↑ Elevated</span>
                        <span x-show="getStatus6('mem_percent', metrics.memory) === 'normal'"   :class="badgeClass('normal')"   class="px-2 py-1 text-xs font-medium rounded-full">✓ Normal</span>
                        <span x-show="getStatus6('mem_percent', metrics.memory) === 'learning'" :class="badgeClass('learning')" class="px-2 py-1 text-xs font-medium rounded-full">◌ Learning</span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-center text-gray-500 dark:text-gray-400" x-text="metrics.memUsed + ' / ' + metrics.memTotal"></div>
                    <div class="text-xs text-center text-gray-400 dark:text-gray-500 mt-1" x-show="thresholdData" x-text="baselineSummary('mem_percent')"></div>
                </div>
            </div>
        </div>
        
        <!-- Disk I/O Card -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="text-center">
                    <div class="flex justify-center mb-3">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Disk I/O</h3>
                    <div class="space-y-1 mb-2">
                        <div class="flex items-center justify-center space-x-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">↑ Write:</span>
                            <span class="text-lg font-semibold text-gray-900 dark:text-white" x-text="metrics.diskWrite"></span>
                        </div>
                        <div class="flex items-center justify-center space-x-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">↓ Read:</span>
                            <span class="text-lg font-semibold text-gray-900 dark:text-white" x-text="metrics.diskRead"></span>
                        </div>
                    </div>
                    <div class="flex justify-center">
                        <span x-show="getStatus6('disk_io_write', rawMetrics.diskWriteBytes) === 'critical'" :class="badgeClass('critical')" class="px-2 py-1 text-xs font-medium rounded-full">✗ Critical</span>
                        <span x-show="getStatus6('disk_io_write', rawMetrics.diskWriteBytes) === 'warning'"  :class="badgeClass('warning')"  class="px-2 py-1 text-xs font-medium rounded-full">⚠ Warning</span>
                        <span x-show="getStatus6('disk_io_write', rawMetrics.diskWriteBytes) === 'high'"     :class="badgeClass('high')"     class="px-2 py-1 text-xs font-medium rounded-full">↑↑ High</span>
                        <span x-show="getStatus6('disk_io_write', rawMetrics.diskWriteBytes) === 'elevated'" :class="badgeClass('elevated')" class="px-2 py-1 text-xs font-medium rounded-full">↑ Elevated</span>
                        <span x-show="getStatus6('disk_io_write', rawMetrics.diskWriteBytes) === 'normal'"   :class="badgeClass('normal')"   class="px-2 py-1 text-xs font-medium rounded-full">✓ Normal</span>
                        <span x-show="getStatus6('disk_io_write', rawMetrics.diskWriteBytes) === 'learning'" :class="badgeClass('learning')" class="px-2 py-1 text-xs font-medium rounded-full">◌ Learning</span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Reads: <span class="font-medium text-gray-900 dark:text-white" x-text="metrics.diskReadOps"></span></span>
                        <span>Writes: <span class="font-medium text-gray-900 dark:text-white" x-text="metrics.diskWriteOps"></span></span>
                    </div>
                    <div class="text-xs text-center text-gray-400 dark:text-gray-500 mt-1" x-show="thresholdData" x-text="baselineSummary('disk_io_write', '↑')"></div>
                    <div class="text-xs text-center text-gray-400 dark:text-gray-500" x-show="thresholdData" x-text="baselineSummary('disk_io_read', '↓')"></div>
                </div>
            </div>
        </div>
        
        <!-- CPU Temperature Card -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="text-center">
                    <div class="flex justify-center mb-3">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">CPU Temp</h3>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        <span x-show="tempAverage > 0" x-text="displayTemp(tempAverage)"></span>
                        <button 
                            @click="toggleTempUnit()" 
                            x-show="tempAverage > 0"
                            class="ml-1 text-lg font-normal text-walmart-blue hover:text-blue-700 cursor-pointer transition-colors"
                            x-text="'°' + tempUnit">
                        </button>
                        <span x-show="tempAverage === 0" class="text-gray-400">N/A</span>
                    </div>
                    <div class="flex justify-center" x-show="tempAverage > 0">
                        <span x-show="getTempStatus(tempAverage) === 'critical'" :class="badgeClass('critical')" class="px-2 py-1 text-xs font-medium rounded-full">✗ Critical</span>
                        <span x-show="getTempStatus(tempAverage) === 'warning'"  :class="badgeClass('warning')"  class="px-2 py-1 text-xs font-medium rounded-full">⚠ Warning</span>
                        <span x-show="getTempStatus(tempAverage) === 'high'"     :class="badgeClass('high')"     class="px-2 py-1 text-xs font-medium rounded-full">↑↑ High</span>
                        <span x-show="getTempStatus(tempAverage) === 'elevated'" :class="badgeClass('elevated')" class="px-2 py-1 text-xs font-medium rounded-full">↑ Elevated</span>
                        <span x-show="getTempStatus(tempAverage) === 'normal'"   :class="badgeClass('normal')"   class="px-2 py-1 text-xs font-medium rounded-full">✓ Normal</span>
                        <span x-show="getTempStatus(tempAverage) === 'learning'" :class="badgeClass('learning')" class="px-2 py-1 text-xs font-medium rounded-full">◌ Learning</span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700" x-show="tempAverage > 0">
                    <div class="text-xs text-center text-gray-500 dark:text-gray-400">
                        Range: <span x-text="displayTemp(tempMin)"></span>°<span x-text="tempUnit"></span> - <span x-text="displayTemp(tempMax)"></span>°<span x-text="tempUnit"></span>
                    </div>
                    <div class="text-xs text-center text-gray-400 dark:text-gray-500 mt-1" x-show="thresholdData" x-text="baselineSummary('temp_avg')"></div>
                </div>
            </div>
        </div>
        
        <!-- Network Card -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="text-center">
                    <div class="flex justify-center mb-3">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-15.857 21.213 0" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Network</h3>
                    <div class="space-y-1 mb-2">
                        <div class="flex items-center justify-center space-x-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">↑ Sent:</span>
                            <span class="text-lg font-semibold text-gray-900 dark:text-white" x-text="metrics.netSent"></span>
                        </div>
                        <div class="flex items-center justify-center space-x-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">↓ Recv:</span>
                            <span class="text-lg font-semibold text-gray-900 dark:text-white" x-text="metrics.netRecv"></span>
                        </div>
                    </div>
                    <div class="flex justify-center">
                        <span x-show="getStatus6('net_sent', rawMetrics.netSentBytes) === 'critical'" :class="badgeClass('critical')" class="px-2 py-1 text-xs font-medium rounded-full">✗ Critical</span>
                        <span x-show="getStatus6('net_sent', rawMetrics.netSentBytes) === 'warning'"  :class="badgeClass('warning')"  class="px-2 py-1 text-xs font-medium rounded-full">⚠ Warning</span>
                        <span x-show="getStatus6('net_sent', rawMetrics.netSentBytes) === 'high'"     :class="badgeClass('high')"     class="px-2 py-1 text-xs font-medium rounded-full">↑↑ High</span>
                        <span x-show="getStatus6('net_sent', rawMetrics.netSentBytes) === 'elevated'" :class="badgeClass('elevated')" class="px-2 py-1 text-xs font-medium rounded-full">↑ Elevated</span>
                        <span x-show="getStatus6('net_sent', rawMetrics.netSentBytes) === 'normal'"   :class="badgeClass('normal')"   class="px-2 py-1 text-xs font-medium rounded-full">✓ Normal</span>
                        <span x-show="getStatus6('net_sent', rawMetrics.netSentBytes) === 'learning'" :class="badgeClass('learning')" class="px-2 py-1 text-xs font-medium rounded-full">◌ Learning</span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Packets: <span class="font-medium text-gray-900 dark:text-white" x-text="metrics.netPackets"></span></span>
                        <span>Connections: <span class="font-medium text-gray-900 dark:text-white" x-text="metrics.netConnections"></span></span>
                    </div>
                    <div class="text-xs text-center text-gray-400 dark:text-gray-500 mt-1" x-show="thresholdData" x-text="baselineSummary('net_sent', '↑')"></div>
                    <div class="text-xs text-center text-gray-400 dark:text-gray-500" x-show="thresholdData" x-text="baselineSummary('net_recv', '↓')"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- CPU Cores (Collapsible) -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6" x-data="{ showAllCores: false }">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">CPU Cores</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="cores.length + ' cores'"></span>
            </div>
            
            <!-- Average CPU -->
            <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600 dark:text-gray-400 font-medium">Average</span>
                    <span class="font-semibold text-gray-900 dark:text-white" x-text="coreAverage + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                    <div 
                        class="h-3 rounded-full transition-all duration-500"
                        :class="getBarColor('cpu_percent', coreAverage)"
                        :style="`width: ${coreAverage}%`">
                    </div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-2">
                    <span>Min: <span x-text="coreMin + '%'"></span></span>
                    <span>Max: <span x-text="coreMax + '%'"></span></span>
                </div>
            </div>
            
            <!-- Expand/Collapse Button -->
            <button 
                @click="showAllCores = !showAllCores"
                class="w-full flex items-center justify-center space-x-2 py-2 px-4 text-sm font-medium text-walmart-blue hover:bg-blue-50 dark:hover:bg-gray-700 rounded-md transition-colors">
                <svg x-show="!showAllCores" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
                <svg x-show="showAllCores" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                </svg>
                <span x-text="showAllCores ? 'Hide all cores' : 'Show all ' + cores.length + ' cores'"></span>
            </button>
            
            <!-- All Cores (Expandable) -->
            <div x-show="showAllCores" x-cloak x-collapse class="mt-4">
                <div class="max-h-96 overflow-y-auto pr-2">
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="(core, index) in (cores || [])" :key="core.number">
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-600 dark:text-gray-400" x-text="'Core ' + core.number"></span>
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="core.percent + '%'"></span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div 
                                        class="h-1.5 rounded-full"
                                        :class="getBarColor('cpu_percent', core.percent)"
                                        :style="`width: ${core.percent}%`">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top CPU Processes -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Top CPU Processes</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">PID</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">CPU%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Mem%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <template x-for="(proc, index) in (topCPU || [])" :key="proc.pid">
                            <tr>
                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white" x-text="proc.pid"></td>
                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white truncate max-w-xs" x-text="proc.name"></td>
                                <td class="px-3 py-2 text-sm text-right font-medium text-gray-900 dark:text-white" x-text="proc.cpu_percent.toFixed(1)"></td>
                                <td class="px-3 py-2 text-sm text-right text-gray-500 dark:text-gray-400" x-text="proc.mem_percent.toFixed(1)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Temperatures (Collapsible) -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6" x-show="temps && temps.length > 0" x-cloak>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">CPU Temperatures</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="temps.length + ' sensors'"></span>
            </div>
            
            <!-- Average Temperature -->
            <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400 font-medium">Average Temperature</span>
                    <div class="flex items-center space-x-2">
                        <span class="text-lg font-semibold text-gray-900 dark:text-white" x-text="displayTemp(tempAverage) + '°' + tempUnit"></span>
                        <span 
                            x-show="tempMax >= 80"
                            class="px-2 py-1 text-xs font-medium text-yellow-800 bg-yellow-100 dark:bg-yellow-800 dark:text-yellow-100 rounded-full">
                            High
                        </span>
                        <span 
                            x-show="tempMax < 80"
                            class="px-2 py-1 text-xs font-medium text-green-800 bg-green-100 dark:bg-green-800 dark:text-green-100 rounded-full">
                            Normal
                        </span>
                    </div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-2">
                    <span>Min: <span x-text="displayTemp(tempMin) + '°' + tempUnit"></span></span>
                    <span>Max: <span x-text="displayTemp(tempMax) + '°' + tempUnit"></span></span>
                </div>
            </div>
            
            <!-- Expand/Collapse Button -->
            <button 
                @click="showAllTemps = !showAllTemps"
                class="w-full flex items-center justify-center space-x-2 py-2 px-4 text-sm font-medium text-walmart-blue hover:bg-blue-50 dark:hover:bg-gray-700 rounded-md transition-colors">
                <svg x-show="!showAllTemps" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
                <svg x-show="showAllTemps" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                </svg>
                <span x-text="showAllTemps ? 'Hide all sensors' : 'Show all sensors'"></span>
            </button>
            
            <!-- All Temperatures (Expandable) -->
            <div x-show="showAllTemps" x-transition class="mt-4">
                <div class="max-h-96 overflow-y-auto pr-2 space-y-2">
                    <template x-for="(temp, index) in (temps || [])" :key="index">
                        <div class="flex justify-between items-center p-2 rounded" :class="index % 2 === 0 ? 'bg-gray-50 dark:bg-gray-700' : ''">
                            <span class="text-sm text-gray-600 dark:text-gray-400" x-text="temp.label"></span>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="displayTemp(temp.current) + '°' + tempUnit"></span>
                                <span 
                                    x-show="temp.critical && temp.current >= temp.critical"
                                    class="px-2 py-0.5 text-xs font-medium text-red-800 bg-red-100 dark:bg-red-800 dark:text-red-100 rounded-full">
                                    Critical
                                </span>
                                <span 
                                    x-show="temp.high && temp.current >= temp.high && (!temp.critical || temp.current < temp.critical)"
                                    class="px-2 py-0.5 text-xs font-medium text-yellow-800 bg-yellow-100 dark:bg-yellow-800 dark:text-yellow-100 rounded-full">
                                    High
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        
        <!-- Disk Usage -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Disk Usage</h3>
            <div class="space-y-4">
                <template x-for="(disk, index) in (disks || [])" :key="disk.mountpoint">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600 dark:text-gray-400" x-text="disk.mountpoint"></span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="formatBytes(disk.used) + ' / ' + formatBytes(disk.total)"></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div 
                                class="h-2 rounded-full transition-all duration-500"
                                :class="getSimpleBarColor(disk.percent, 90)"
                                :style="`width: ${disk.percent}%`">
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="disk.percent.toFixed(1) + '% used'"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function dashboard() {
    return {
        metrics: {
            cpu: 0,
            memory: 0,
            load1: 0,
            load5: 0,
            load15: 0,
            memUsed: '0 GB',
            memTotal: '0 GB',
            diskRead: '0 B/s',
            diskWrite: '0 B/s',
            diskReadOps: '0/s',
            diskWriteOps: '0/s',
            netSent: '0 B/s',
            netRecv: '0 B/s',
            netPackets: '0/s',
            netConnections: 0
        },
        cores: [],
        coreAverage: 0,
        coreMin: 0,
        coreMax: 0,
        temps: [],
        tempAverage: 0,
        tempMin: 0,
        tempMax: 0,
        tempUnit: localStorage.getItem('tempUnit') || 'F', // Default to Fahrenheit
        showAllTemps: false,  // For temperature sensor expansion
        disks: [],
        topCPU: [],
        collectorHealthy: true,
        lastHeartbeat: '',
        thresholdData: null,          // 6-level intelligence layer data
        rawMetrics: { diskWriteBytes: 0, netSentBytes: 0 }, // Raw bytes for threshold comparison
        
        init() {
            this.fetchThresholds(); // Load intelligence layer data once on init
            this.fetchData();
            setInterval(() => { if (!document.hidden) this.fetchData(); }, <?php echo LIVE_UPDATE_INTERVAL; ?>);
            setInterval(() => { if (!document.hidden) this.fetchThresholds(); }, 5 * 60 * 1000); // Refresh thresholds every 5 min

            // Fetch immediately when the user returns to this tab so they
            // never sit looking at stale data after switching away.
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.fetchData();
            });

            // Listen for temp unit changes from settings page
            window.addEventListener('tempUnitChanged', (e) => {
                this.tempUnit = e.detail;
            });
        },
        
        async fetchThresholds() {
            try {
                const res  = await fetch('../api/thresholds_api.php');
                this.thresholdData = await res.json();
            } catch (e) {
                console.warn('Could not load thresholds:', e);
            }
        },
        
        async fetchData() {
            try {
                // Fetch live metrics
                const response = await fetch('../api/live_api.php');
                const data = await response.json();
                
                // Update metrics
                this.metrics.cpu = data.system.cpu.percent.toFixed(1);
                this.metrics.memory = data.system.memory.percent.toFixed(1);
                this.metrics.load1 = data.system.cpu.load['1min'].toFixed(2);
                this.metrics.load5 = data.system.cpu.load['5min'].toFixed(2);
                this.metrics.load15 = data.system.cpu.load['15min'].toFixed(2);
                this.metrics.memUsed = this.formatBytes(data.system.memory.used);
                this.metrics.memTotal = this.formatBytes(data.system.memory.total);
                this.metrics.diskRead = this.formatBytes(data.system.disk_io.read_bytes) + '/s';
                this.metrics.diskWrite = this.formatBytes(data.system.disk_io.write_bytes) + '/s';
                this.metrics.diskReadOps = data.system.disk_io.read_count + '/s';
                this.metrics.diskWriteOps = data.system.disk_io.write_count + '/s';
                this.metrics.netSent = this.formatBytes(data.system.network.bytes_sent) + '/s';
                this.metrics.netRecv = this.formatBytes(data.system.network.bytes_recv) + '/s';
                // Store raw bytes for 6-level threshold comparison
                this.rawMetrics.diskWriteBytes = data.system.disk_io.write_bytes;
                this.rawMetrics.netSentBytes   = data.system.network.bytes_sent;
                
                // Calculate total packets per second
                const totalPackets = data.system.network.packets_sent + data.system.network.packets_recv;
                this.metrics.netPackets = totalPackets + '/s';
                
                // Get connection count
                if (data.connections) {
                    this.metrics.netConnections = data.connections.total;
                } else {
                    this.metrics.netConnections = 0;
                }
                
                this.cores = data.system.cpu.cores.map(c => ({
                    number: c.number,
                    percent: parseFloat(c.percent.toFixed(1))
                }));
                
                // Calculate core stats
                if (this.cores.length > 0) {
                    const coreValues = this.cores.map(c => c.percent);
                    this.coreAverage = (coreValues.reduce((a, b) => a + b, 0) / coreValues.length).toFixed(1);
                    this.coreMin = Math.min(...coreValues).toFixed(1);
                    this.coreMax = Math.max(...coreValues).toFixed(1);
                }
                
                this.temps = data.temperatures || [];
                console.log('Temps loaded:', this.temps);
                
                // Calculate temperature stats
                if (this.temps && this.temps.length > 0) {
                    const tempValues = this.temps.map(t => t.current);
                    this.tempAverage = (tempValues.reduce((a, b) => a + b, 0) / tempValues.length).toFixed(1);
                    this.tempMin = Math.min(...tempValues).toFixed(1);
                    this.tempMax = Math.max(...tempValues).toFixed(1);
                }
                
                this.disks = data.disks || [];
                
                // Collector status
                if (data.collector) {
                    this.collectorHealthy = data.collector.is_healthy;
                    this.lastHeartbeat = data.collector.last_heartbeat;
                }
                
                // Fetch top processes
                const procResponse = await fetch('../api/processes_api.php?sort=cpu&limit=10');
                const procData = await procResponse.json();
                this.topCPU = Array.isArray(procData) ? procData : (procData.data || []);
                
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        },
        
        // ----------------------------------------------------------------
        // 6-level intelligence status system
        // Levels: critical > warning > high > elevated > normal > learning
        // ----------------------------------------------------------------
        getStatus6(metric, value) {
            if (!this.thresholdData) return 'learning';
            const t = this.thresholdData.metrics[metric];
            if (!t) return 'learning';

            // Guard rails — absolute hard stops, always fire
            if (t.guard_rail_ceiling !== null && value >= t.guard_rail_ceiling) return 'critical';
            if (t.guard_rail_floor   !== null && value >= t.guard_rail_floor)   return 'warning';

            // Manual override — use fixed threshold, skip baseline
            if (t.manual_override) {
                if (t.threshold !== null) {
                    if (value >= t.threshold)         return 'critical';
                    if (value >= t.threshold * 0.8)   return 'warning';
                }
                return 'normal';
            }

            // Baseline sigma checks
            if (!this.thresholdData.has_baseline || !t.baseline) return 'learning';
            const { mean, stddev } = t.baseline;
            if (!stddev || stddev === 0) return value > mean ? 'elevated' : 'normal';
            if (value >= mean + t.sigma_high     * stddev) return 'high';
            if (value >= mean + t.sigma_elevated * stddev) return 'elevated';
            return 'normal';
        },

        // Badge classes for each level
        badgeClass(status) {
            return {
                critical: 'text-red-800    bg-red-100    dark:bg-red-800    dark:text-red-100',
                warning:  'text-yellow-800 bg-yellow-100 dark:bg-yellow-800 dark:text-yellow-100',
                high:     'text-orange-800 bg-orange-100 dark:bg-orange-800 dark:text-orange-100',
                elevated: 'text-teal-800   bg-teal-100   dark:bg-teal-800   dark:text-teal-100',
                normal:   'text-green-800  bg-green-100  dark:bg-green-800  dark:text-green-100',
                learning: 'text-blue-800   bg-blue-100   dark:bg-blue-800   dark:text-blue-100',
            }[status] || 'text-gray-800 bg-gray-100';
        },

        getBarColor(metric, value) {
            const status = this.getStatus6(metric, value);
            return {
                critical: 'bg-red-500',
                warning:  'bg-yellow-500',
                high:     'bg-orange-500',
                elevated: 'bg-teal-500',
                normal:   'bg-green-500',
                learning: 'bg-blue-400',
            }[status] || 'bg-green-500';
        },

        // Simple bar color for metrics not in the intelligence layer (disk %, etc.)
        getSimpleBarColor(value, threshold) {
            if (value >= threshold)         return 'bg-red-500';
            if (value >= threshold * 0.8)   return 'bg-yellow-500';
            return 'bg-green-500';
        },

        getTempStatus(tempCelsius) {
            return this.getStatus6('temp_avg', parseFloat(tempCelsius));
        },

        // Returns "Avg: X ± Y" for a metric using current baseline bucket.
        // Optionally pass a label (e.g. '↑') to prefix the output: "↑ Avg: X ± Y"
        // Returns '◌ learning' if baseline not ready yet.
        // Returns '' if thresholdData hasn't loaded at all (before first fetch).
        baselineSummary(metric, label) {
            if (!this.thresholdData) return '';
            const t = this.thresholdData.metrics[metric];
            const prefix = label ? label + ' ' : '';
            if (!t || !t.baseline || t.baseline.mean === null) return prefix + '◌ learning';
            const { mean, stddev } = t.baseline;
            const fmt = (v) => {
                if (metric === 'cpu_percent' || metric === 'mem_percent')
                    return parseFloat(v).toFixed(1) + '%';
                if (metric === 'disk_io_write' || metric === 'disk_io_read' || metric === 'net_sent' || metric === 'net_recv')
                    return this.formatBytes(v) + '/s';
                if (metric === 'temp_avg')
                    return this.displayTemp(v) + '\u00b0' + this.tempUnit;
                return parseFloat(v).toFixed(1);
            };
            return stddev !== null
                ? `${prefix}Avg: ${fmt(mean)} \u00b1 ${fmt(stddev)}`
                : `${prefix}Avg: ${fmt(mean)}`;
        },
        
        celsiusToFahrenheit(celsius) {
            return (celsius * 9/5) + 32;
        },
        
        displayTemp(celsius) {
            if (this.tempUnit === 'F') {
                return this.celsiusToFahrenheit(celsius).toFixed(1);
            }
            return parseFloat(celsius).toFixed(1);
        },
        
        toggleTempUnit() {
            this.tempUnit = this.tempUnit === 'C' ? 'F' : 'C';
            localStorage.setItem('tempUnit', this.tempUnit);
        },
        
        // Disk and network status now handled by getStatus6() using rawMetrics
        
        parseMetricValue(str) {
            // Parse values like "12.5 MB" back to bytes
            const match = str.match(/([0-9.]+)\s*([A-Z]+)/);
            if (!match) return 0;
            
            const value = parseFloat(match[1]);
            const unit = match[2];
            
            const multipliers = {
                'B': 1,
                'KB': 1024,
                'MB': 1024 * 1024,
                'GB': 1024 * 1024 * 1024,
                'TB': 1024 * 1024 * 1024 * 1024
            };
            
            return value * (multipliers[unit] || 1);
        },
        
        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
