<?php
$pageTitle = 'Process Explorer';
require_once __DIR__ . '/../includes/header.php';
?>

<div x-data="pageWrapper()" class="mb-8">

    <!-- ── Page tab bar ────────────────────────────────────────────────── -->
    <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
        <button @click="mainTab = 'live'"
                :class="mainTab === 'live'
                    ? 'border-walmart-blue text-gray-900 dark:text-white'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">
            ⚡ Live Processes
        </button>
        <button @click="mainTab = 'history'"
                :class="mainTab === 'history'
                    ? 'border-walmart-blue text-gray-900 dark:text-white'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">
            📊 Process History
        </button>
    </div>

    <!-- ── Live Processes tab ────────────────────────────────────────── -->
    <div x-show="mainTab === 'live'">
    <div x-data="processExplorer()" x-init="init()">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Process Explorer</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Top 50 processes by resource usage
            </p>
        </div>
        
        <!-- View Toggle -->
        <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-600 dark:text-gray-400">View by:</span>
            <button @click="viewMode = 'cpu'" 
                    :class="viewMode === 'cpu' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                🔥 CPU Usage
            </button>
            <button @click="viewMode = 'memory'" 
                    :class="viewMode === 'memory' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors">
                💾 Memory Usage
            </button>
            
            <!-- Pause/Resume Button -->
            <div class="border-l border-gray-300 dark:border-gray-600 pl-4 ml-2">
                <button @click="togglePause()" 
                        :class="paused ? 'bg-green-600 hover:bg-green-700' : 'bg-yellow-600 hover:bg-yellow-700'"
                        class="px-4 py-2 rounded-md text-sm font-medium text-white transition-colors">
                    <span x-text="paused ? '▶️ Resume' : '⏸️ Pause'"></span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Search Filter -->
    <div class="mb-6">
        <div class="relative">
            <input 
                type="text" 
                x-model="searchFilter" 
                placeholder="🔍 Search by PID, process name, or username..."
                class="w-full px-4 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-walmart-blue focus:border-transparent"
            />
            <!-- Clear button -->
            <button 
                x-show="searchFilter.length > 0"
                @click="searchFilter = ''"
                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                title="Clear search"
            >
                ✕
            </button>
        </div>
        <!-- Results count -->
        <div x-show="searchFilter.length > 0" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            <span x-show="viewMode === 'cpu'" x-text="`Showing ${filteredCpuProcesses.length} of ${topCpuProcesses.length} processes`"></span>
            <span x-show="viewMode === 'memory'" x-text="`Showing ${filteredMemoryProcesses.length} of ${topMemoryProcesses.length} processes`"></span>
        </div>
    </div>
    
    <!-- Loading State -->
    <div x-show="loading" class="text-center py-12">
        <svg class="animate-spin h-12 w-12 mx-auto text-walmart-blue" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="mt-4 text-gray-600 dark:text-gray-400">Loading processes...</p>
    </div>
    
    <!-- Process Table -->
    <div x-show="!loading" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <!-- CPU View -->
        <div x-show="viewMode === 'cpu'">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    🔥 Top 50 Processes by CPU Usage
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">PID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Process Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">CPU %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Memory %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"></th>
                        </tr>
                    </thead>
                    <template x-for="(proc, index) in filteredCpuProcesses" :key="proc.pid">
                        <tbody class="bg-white dark:bg-gray-800">
                            <!-- Main row (clickable) -->
                            <tr @click="toggleExpand(proc.pid)" 
                                class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" 
                                    :class="index === 0 ? 'text-red-600' : index === 1 ? 'text-orange-600' : index === 2 ? 'text-yellow-600' : 'text-gray-900 dark:text-white'" 
                                    x-text="index + 1"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="proc.pid"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white" x-text="proc.name"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="proc.username"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-1 mr-2">
                                            <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden" style="width: 100px;">
                                                <div class="h-full bg-red-500 rounded-full" :style="`width: ${Math.min(proc.cpu_percent, 100)}%`"></div>
                                            </div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="proc.cpu_percent.toFixed(1) + '%'"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="proc.mem_percent.toFixed(1) + '%'"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" 
                                          :class="proc.status === 'running' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                                          x-text="proc.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <span x-text="expandedRows.has(proc.pid) ? '▲' : '▼'"></span>
                                </td>
                            </tr>
                            <!-- Expanded detail row -->
                            <tr x-show="expandedRows.has(proc.pid)" 
                                x-transition
                                class="bg-gray-50 dark:bg-gray-900">
                                <td colspan="8" class="px-6 py-4">
                                    <div x-html="renderProcessDetails(proc.pid)"></div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                </table>
            </div>
        </div>
        
        <!-- Memory View -->
        <div x-show="viewMode === 'memory'">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    💾 Top 50 Processes by Memory Usage
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">PID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Process Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Memory %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Memory</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">CPU %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"></th>
                        </tr>
                    </thead>
                    <template x-for="(proc, index) in filteredMemoryProcesses" :key="proc.pid">
                        <tbody class="bg-white dark:bg-gray-800">
                            <!-- Main row (clickable) -->
                            <tr @click="toggleExpand(proc.pid)" 
                                class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" 
                                    :class="index === 0 ? 'text-purple-600' : index === 1 ? 'text-blue-600' : index === 2 ? 'text-cyan-600' : 'text-gray-900 dark:text-white'" 
                                    x-text="index + 1"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="proc.pid"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white" x-text="proc.name"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="proc.username"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-1 mr-2">
                                            <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden" style="width: 100px;">
                                                <div class="h-full bg-purple-500 rounded-full" :style="`width: ${Math.min(proc.mem_percent, 100)}%`"></div>
                                            </div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="proc.mem_percent.toFixed(1) + '%'"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="formatProcMem(proc.mem_rss)"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="proc.cpu_percent.toFixed(1) + '%'"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                          :class="proc.status === 'running' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                                          x-text="proc.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <span x-text="expandedRows.has(proc.pid) ? '▲' : '▼'"></span>
                                </td>
                            </tr>
                            <!-- Expanded detail row -->
                            <tr x-show="expandedRows.has(proc.pid)" 
                                x-transition
                                class="bg-gray-50 dark:bg-gray-900">
                                <td colspan="9" class="px-6 py-4">
                                    <div x-html="renderProcessDetails(proc.pid)"></div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                </table>
            </div>
        </div>
    </div>
    </div><!-- /processExplorer -->
    </div><!-- /live tab -->

<script>
function processExplorer() {
    return {
        viewMode: 'cpu',
        loading: true,
        paused: false,
        processes: [],
        topCpuProcesses: [],
        topMemoryProcesses: [],
        filteredCpuProcesses: [],
        filteredMemoryProcesses: [],
        searchFilter: '',
        expandedRows: new Set(),
        processDetails: {},
        refreshInterval: null,
        
        init() {
            this.loadProcesses();
            this.startAutoRefresh();
            // Filter live as you type
            this.$watch('searchFilter', () => this.filterProcesses());
            // Fetch immediately when the user returns to this tab.
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && !this.paused) this.loadProcesses();
            });
        },
        
        startAutoRefresh() {
            // Auto-refresh every <?php echo LIVE_UPDATE_INTERVAL; ?>ms (matches collector interval)
            this.refreshInterval = setInterval(() => {
                if (!this.paused && !document.hidden) {
                    this.loadProcesses();
                }
            }, <?php echo LIVE_UPDATE_INTERVAL; ?>);
        },
        
        togglePause() {
            this.paused = !this.paused;
        },

        formatProcMem(bytes) {
            if (bytes >= 1024 * 1024 * 1024) return (bytes / 1024 / 1024 / 1024).toFixed(2) + ' GB';
            if (bytes >= 1024 * 1024)        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
            if (bytes >= 1024)               return (bytes / 1024).toFixed(1) + ' KB';
            return bytes + ' B';
        },
        
        async loadProcesses() {
            try {
                const response = await fetch('../api/processes_api.php');
                const result = await response.json();
                
                if (result.success && result.data) {
                    this.processes = result.data;
                    
                    // Sort by CPU and get top 50
                    this.topCpuProcesses = [...this.processes]
                        .sort((a, b) => b.cpu_percent - a.cpu_percent)
                        .slice(0, 50);
                    
                    // Sort by Memory and get top 50
                    this.topMemoryProcesses = [...this.processes]
                        .sort((a, b) => b.mem_percent - a.mem_percent)
                        .slice(0, 50);
                    
                    // Apply search filter
                    this.filterProcesses();
                }
                
                this.loading = false;
            } catch (error) {
                console.error('Error loading processes:', error);
                this.loading = false;
            }
        },
        
        filterProcesses() {
            const filter = this.searchFilter.toLowerCase().trim();
            
            if (!filter) {
                // No filter - show all
                this.filteredCpuProcesses = this.topCpuProcesses;
                this.filteredMemoryProcesses = this.topMemoryProcesses;
            } else {
                // Filter by PID, name, or username (case-insensitive)
                this.filteredCpuProcesses = this.topCpuProcesses.filter(proc => {
                    return proc.pid.toString().includes(filter) ||
                           proc.name.toLowerCase().includes(filter) ||
                           (proc.username && proc.username.toLowerCase().includes(filter));
                });
                
                this.filteredMemoryProcesses = this.topMemoryProcesses.filter(proc => {
                    return proc.pid.toString().includes(filter) ||
                           proc.name.toLowerCase().includes(filter) ||
                           (proc.username && proc.username.toLowerCase().includes(filter));
                });
            }
        },
        
        toggleExpand(pid) {
            if (this.expandedRows.has(pid)) {
                this.expandedRows.delete(pid);
                delete this.processDetails[pid];
            } else {
                this.expandedRows.add(pid);
                this.loadProcessDetails(pid);
            }
            // Force Alpine to re-render
            this.expandedRows = new Set(this.expandedRows);
        },
        
        async loadProcessDetails(pid) {
            try {
                const response = await fetch(`../api/process-details_api.php?pid=${pid}`);
                const result = await response.json();
                
                if (result.success && result.data) {
                    this.processDetails[pid] = result.data;
                } else {
                    this.processDetails[pid] = { error: result.message || 'Failed to load details' };
                }
            } catch (error) {
                console.error('Error loading process details:', error);
                this.processDetails[pid] = { error: 'Failed to load process details' };
            }
        },
        
        renderProcessDetails(pid) {
            const details = this.processDetails[pid];
            
            if (!details) {
                return '<div class="text-gray-500 dark:text-gray-400">Loading...</div>';
            }
            
            if (details.error) {
                return `<div class="text-red-600 dark:text-red-400">⚠️ ${details.error}</div>`;
            }
            
            const formattedMem = this.formatProcMem((details.memory_rss_mb || 0) * 1024 * 1024);
            const cpuPercent = (details.cpu_percent || 0).toFixed(1);
            const memPercent = (details.memory_percent || 0).toFixed(1);
            
            return `
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-300 dark:border-gray-600">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Process Details - PID ${pid}</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <!-- Full Command -->
                        <div class="col-span-2">
                            <div class="font-semibold text-gray-700 dark:text-gray-300 mb-1">Full Command:</div>
                            <div class="bg-gray-100 dark:bg-gray-900 p-2 rounded font-mono text-xs overflow-x-auto">
                                ${details.cmdline || 'N/A'}
                            </div>
                        </div>
                        
                        <!-- Working Directory -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Working Directory:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.cwd || 'N/A'}</div>
                        </div>
                        
                        <!-- Executable -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Executable:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.exe || 'N/A'}</div>
                        </div>
                        
                        <!-- Start Time -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Start Time:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.create_time_formatted || 'N/A'}</div>
                        </div>
                        
                        <!-- Running Time -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Running Time:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.running_time || 'N/A'}</div>
                        </div>
                        
                        <!-- Resources Header -->
                        <div class="col-span-2 mt-3 mb-1">
                            <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200">Resources:</h4>
                        </div>
                        
                        <!-- CPU Usage -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">CPU Usage:</div>
                            <div class="text-gray-600 dark:text-gray-400">${cpuPercent}%</div>
                        </div>
                        
                        <!-- Memory Usage -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Memory Usage:</div>
                            <div class="text-gray-600 dark:text-gray-400">${formattedMem} (${memPercent}%)</div>
                        </div>
                        
                        <!-- Threads -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Threads:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.num_threads || 'N/A'}</div>
                        </div>
                        
                        <!-- Nice Value -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Nice Value:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.nice !== null && details.nice !== undefined ? details.nice : 'N/A'}</div>
                        </div>
                        
                        <!-- Parent Process -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Parent Process:</div>
                            <div class="text-gray-600 dark:text-gray-400">
                                ${details.parent_pid ? `${details.parent_name} (PID ${details.parent_pid})` : 'N/A'}
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Status:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.status || 'N/A'}</div>
                        </div>
                        
                        ${details.open_files_count !== null && details.open_files_count !== undefined ? `
                        <!-- Open Files -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Open Files:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.open_files_count}</div>
                        </div>
                        ` : ''}
                        
                        ${details.connections_count !== null && details.connections_count !== undefined ? `
                        <!-- Network Connections -->
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">Network Connections:</div>
                            <div class="text-gray-600 dark:text-gray-400">${details.connections_count}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }
    };
}
</script>

    <!-- ── Process History tab ───────────────────────────────────────── -->
    <div x-show="mainTab === 'history'">
    <div x-data="processHistory()" x-init="init()">

    <!-- Tab bar -->
    <div class="flex space-x-1 mb-6 border-b border-gray-200 dark:border-gray-700">
        <button @click="tab = 'time'"
                :class="tab === 'time'
                    ? 'border-walmart-blue text-walmart-blue'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">
            🕐 Time View
        </button>
        <button @click="tab = 'process'; loadNames()"
                :class="tab === 'process'
                    ? 'border-walmart-blue text-walmart-blue'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">
            📈 Process View
        </button>
    </div>

    <!-- ── TIME VIEW ─────────────────────────────────────────────────── -->
    <div x-show="tab === 'time'">
        <!-- Controls -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-4">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date &amp; Time</label>
                    <input type="datetime-local" x-model="timeAt"
                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-walmart-blue focus:border-transparent" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sort by</label>
                    <div class="flex rounded-md overflow-hidden border border-gray-300 dark:border-gray-600">
                        <button @click="timeSort = 'cpu'"
                                :class="timeSort === 'cpu' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-2 text-sm font-medium transition-colors">🔥 CPU</button>
                        <button @click="timeSort = 'mem'"
                                :class="timeSort === 'mem' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-2 text-sm font-medium transition-colors">💾 Memory</button>
                        <button @click="timeSort = 'tcp'"
                                :class="timeSort === 'tcp' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-2 text-sm font-medium transition-colors">🌐 TCP</button>
                    </div>
                </div>
                <button @click="loadTimeView()"
                        :disabled="!timeAt || timeLoading"
                        class="px-4 py-2 bg-walmart-blue text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span x-show="!timeLoading">Load</span>
                    <span x-show="timeLoading">Loading…</span>
                </button>
            </div>
            <!-- Resolved timestamp feedback -->
            <p x-show="timeResolved" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Showing nearest bucket: <span class="font-mono" x-text="timeResolved"></span>
                &nbsp;(<span x-text="timeTable === 'process_history_1min' ? '1-min data' : '5-min data'"></span>)
            </p>
        </div>

        <!-- Message (no data) -->
        <div x-show="timeMessage && !timeLoading" class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm" x-text="timeMessage"></div>

        <!-- Results table -->
        <div x-show="timeProcesses.length > 0 && !timeLoading" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rank</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Process</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">CPU Avg</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">CPU Max</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mem Avg</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mem Max</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">TCP Conns</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <template x-for="(proc, i) in timeProcesses" :key="proc.process_name + proc.username">
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors"
                                @click="openProcessView(proc.process_name)">
                                <td class="px-4 py-3 text-sm font-medium"
                                    :class="i===0?'text-red-600':i===1?'text-orange-500':i===2?'text-yellow-500':'text-gray-500 dark:text-gray-400'"
                                    x-text="i + 1"></td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white" x-text="proc.process_name"></td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="proc.username"></td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-red-500 rounded-full" :style="`width:${Math.min(proc.cpu_avg,100)}%`"></div>
                                        </div>
                                        <span class="text-gray-900 dark:text-white font-mono" x-text="proc.cpu_avg.toFixed(1)+'%'"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-500 dark:text-gray-400" x-text="proc.cpu_max.toFixed(1)+'%'"></td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white" x-text="fmtMb(proc.mem_avg_mb)"></td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-500 dark:text-gray-400" x-text="fmtMb(proc.mem_max_mb)"></td>
                                <td class="px-4 py-3 text-sm font-mono"
                                    :class="proc.tcp_conns_avg > 0 ? 'text-purple-600 dark:text-purple-400 font-medium' : 'text-gray-400 dark:text-gray-500'"
                                    x-text="proc.tcp_conns_avg != null ? proc.tcp_conns_avg.toFixed(1) : '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-2 text-xs text-gray-400 dark:text-gray-500 border-t border-gray-200 dark:border-gray-700">
                <span x-text="timeProcesses.length"></span> processes &mdash; click any row to open in Process View
            </div>
        </div>
    </div>

    <!-- ── PROCESS VIEW ─────────────────────────────────────────────────── -->
    <div x-show="tab === 'process'">
        <!-- Controls -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-4">

            <!-- Mode toggle -->
            <div class="flex items-center gap-2 mb-3">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Mode:</span>
                <div class="flex rounded-md overflow-hidden border border-gray-300 dark:border-gray-600">
                    <button @click="matchMode = 'exact'; matchedNames = []; showDropdown = false"
                            :class="matchMode === 'exact' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                            class="px-3 py-1.5 text-xs font-medium transition-colors">
                        🔍 Exact
                    </button>
                    <button @click="matchMode = 'group'; showDropdown = false"
                            :class="matchMode === 'group' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                            class="px-3 py-1.5 text-xs font-medium transition-colors">
                        🗂️ Group
                    </button>
                </div>
                <span x-show="matchMode === 'group'" class="text-xs text-gray-400 dark:text-gray-500 italic">
                    Type a prefix — matches all processes that start with it
                </span>
            </div>

            <div class="flex flex-wrap items-end gap-4">
                <!-- Process search -->
                <div class="relative flex-1 min-w-48">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"
                           x-text="matchMode === 'group' ? 'Prefix Pattern' : 'Process Name'"></label>
                    <input type="text" x-model="procSearch"
                           @input="matchMode === 'exact' ? filterNames() : null"
                           @focus="matchMode === 'exact' ? showDropdown = true : null"
                           @blur="setTimeout(()=>showDropdown=false, 150)"
                           :placeholder="matchMode === 'group' ? 'e.g. Emby Transcode, qemu, python…' : 'Search process name…'"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-walmart-blue focus:border-transparent" />
                    <!-- Exact-mode dropdown -->
                    <div x-show="matchMode === 'exact' && showDropdown && filteredNames.length > 0"
                         class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="n in filteredNames.slice(0,50)" :key="n.process_name+'|'+n.username">
                            <div @mousedown="selectProcess(n)"
                                 class="px-3 py-2 text-sm text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer">
                                <span class="font-medium" x-text="n.process_name"></span>
                                <span class="text-xs text-gray-400 ml-1" x-text="'('+n.username+')'"></span>
                            </div>
                        </template>
                    </div>
                </div>
                <!-- Range -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Range</label>
                    <div class="flex rounded-md overflow-hidden border border-gray-300 dark:border-gray-600">
                        <template x-for="r in ['1h','6h','24h','7d','30d']" :key="r">
                            <button @click="procRange = r"
                                    :class="procRange === r ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                    class="px-3 py-2 text-sm font-medium transition-colors"
                                    x-text="r"></button>
                        </template>
                    </div>
                </div>
                <button @click="loadView()"
                        :disabled="(matchMode === 'exact' ? !procSelected : !procSearch.trim()) || procLoading"
                        class="px-4 py-2 bg-walmart-blue text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span x-show="!procLoading">Load</span>
                    <span x-show="procLoading">Loading…</span>
                </button>
            </div>
        </div>

        <!-- Empty / no data -->
        <div x-show="!procSelected && !procLoading" class="text-center py-12 text-gray-400 dark:text-gray-500 text-sm">
            <span x-show="matchMode === 'exact'">Search for a process above to view its history.</span>
            <span x-show="matchMode === 'group'">Enter a prefix above (e.g. <code class="font-mono">Emby Transcode</code>) and click Load to see combined group usage.</span>
        </div>
        <div x-show="procSelected && procPoints.length === 0 && !procLoading" class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
            <span x-show="matchMode === 'exact'">No data found for <strong x-text="procSelected"></strong> in the selected range.</span>
            <span x-show="matchMode === 'group'">No processes matched prefix <strong x-text="procSelected"></strong> in the selected range.</span>
        </div>

        <!-- Chart area -->
        <div x-show="procPoints.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span x-show="matchMode === 'group'" class="text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-100">GROUP</span>
                        <span x-text="procSelected"></span>
                        <span class="text-sm font-normal text-gray-400 ml-1" x-text="'— ' + procRangeLabel"></span>
                    </h3>
                    <!-- Group manifest -->
                    <div x-show="matchMode === 'group' && matchedNames.length > 0" class="mt-2 flex flex-wrap gap-1">
                        <template x-for="n in matchedNames" :key="n.process_name">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-mono"
                                  x-text="n.process_name"></span>
                        </template>
                    </div>
                    <p x-show="matchMode === 'group'" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        CPU &amp; Memory values are <strong>summed</strong> across all matched processes.
                    </p>
                </div>
                <span class="text-xs text-gray-400 flex-shrink-0" x-text="procPoints.length + ' data points'"></span>
            </div>
            <!-- CPU chart -->
            <div class="mb-6">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">CPU Usage (%)</p>
                <div style="height: 180px;"><canvas id="procCpuChart"></canvas></div>
            </div>
            <!-- Memory chart -->
            <div class="mb-6">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Memory Usage (MB)</p>
                <div style="height: 180px;"><canvas id="procMemChart"></canvas></div>
            </div>
            <!-- TCP Connections chart -->
            <div x-show="procPoints.some(p => p.tcp_conns_avg != null)">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">TCP Connections (established)</p>
                <div style="height: 180px;"><canvas id="procTcpChart"></canvas></div>
            </div>
        </div>
    </div>

    </div><!-- /processHistory -->
    </div><!-- /history tab -->
</div><!-- /outer tab wrapper -->

<script>
// ── Outer page wrapper ──────────────────────────────────────────────────────────────
// Controls the Live / Process History main tab.
// If the page is opened with ?at=<timestamp> (e.g. from a history.php chart
// click), jump straight to the Process History tab so the user lands in the
// right place without an extra click.
function pageWrapper() {
    return {
        mainTab: new URLSearchParams(window.location.search).has('at') ? 'history' : 'live',
    };
}

function processHistory() {
    return {
        tab: 'time',

        // Time view state
        timeAt: '',
        timeSort: 'cpu',
        timeLoading: false,
        timeProcesses: [],
        timeResolved: '',
        timeTable: '',
        timeMessage: '',

        // Process view state
        procSearch: '',
        procSelected: '',
        procRange: '24h',
        procRangeLabel: '',
        procLoading: false,
        procPoints: [],
        matchMode: 'exact',      // 'exact' | 'group'
        matchedNames: [],        // populated by view=group — names in the current group
        allNames: [],
        filteredNames: [],
        showDropdown: false,
        namesLoaded: false,

        // Chart instances
        cpuChart: null,
        memChart: null,
        tcpChart: null,

        init() {
            const params = new URLSearchParams(window.location.search);
            const at     = params.get('at'); // "YYYY-MM-DDTHH:MM" from history.php
            const sort   = params.get('sort');

            // Pre-select sort mode if passed in URL (e.g. sort=tcp from Network I/O chart)
            if (sort && ['cpu', 'mem', 'tcp'].includes(sort)) {
                this.timeSort = sort;
            }

            if (at) {
                // Pre-fill the timestamp picker and fire the query automatically.
                // $nextTick ensures Alpine has finished its own init cycle first.
                this.timeAt = at;
                this.$nextTick(() => this.loadTimeView());
            } else {
                // Default: 1 minute ago in LOCAL time — process_history_1min
                // buckets at 60s so this lands on the most recent available row.
                // toISOString() always returns UTC — using it with a
                // datetime-local input (which has no timezone concept)
                // shows a time offset from local by the user's UTC offset.
                // getFullYear/Month/etc. return local time, so this is correct.
                const d   = new Date(Date.now() - 60000);
                const pad = n => String(n).padStart(2, '0');
                this.timeAt = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            }
        },

        // ── Time View ────────────────────────────────────────────────
        async loadTimeView() {
            if (!this.timeAt) return;
            this.timeLoading  = true;
            this.timeProcesses = [];
            this.timeResolved  = '';
            this.timeMessage   = '';

            try {
                const at  = this.timeAt.replace('T', ' ');
                const url = `../api/process_history_api.php?view=time&at=${encodeURIComponent(at)}&sort=${this.timeSort}`;
                const res = await fetch(url);
                const data = await res.json();

                if (data.success) {
                    this.timeProcesses = data.processes || [];
                    this.timeResolved  = data.resolved || '';
                    this.timeTable     = data.table    || '';
                    if (this.timeProcesses.length === 0) {
                        this.timeMessage = data.message || 'No processes found for that time.';
                    }
                } else {
                    this.timeMessage = data.error || 'Failed to load data.';
                }
            } catch (e) {
                this.timeMessage = 'Network error loading time view.';
                console.error(e);
            } finally {
                this.timeLoading = false;
            }
        },

        // Open a process in the Process View when clicking a row
        openProcessView(name) {
            this.tab = 'process';
            this.matchMode    = 'exact';  // always exact when jumping from Time View
            this.matchedNames = [];
            this.procSearch   = name;
            this.procSelected = name;
            this.loadNames().then(() => this.loadProcessView());
        },

        // ── Process View ─────────────────────────────────────────────
        async loadNames() {
            if (this.namesLoaded) return;
            try {
                const res  = await fetch('../api/process_history_api.php?view=names');
                const data = await res.json();
                if (data.success) {
                    this.allNames    = data.names || [];
                    this.filteredNames = this.allNames;
                    this.namesLoaded = true;
                }
            } catch (e) {
                console.error('Failed to load process names:', e);
            }
        },

        filterNames() {
            const q = this.procSearch.toLowerCase().trim();
            this.filteredNames = q
                ? this.allNames.filter(n => n.process_name.toLowerCase().includes(q))
                : this.allNames;
            this.showDropdown = true;
        },

        selectProcess(n) {
            this.procSearch   = n.process_name;
            this.procSelected = n.process_name;
            this.showDropdown = false;
        },

        async loadProcessView() {
            if (!this.procSelected) return;
            this.procLoading  = true;
            this.procPoints   = [];
            this.matchedNames = [];

            try {
                const url  = `../api/process_history_api.php?view=process&name=${encodeURIComponent(this.procSelected)}&range=${this.procRange}`;
                const res  = await fetch(url);
                const data = await res.json();

                if (data.success) {
                    this.procPoints    = data.points || [];
                    this.procRangeLabel = data.label || this.procRange;
                    if (this.procPoints.length > 0) {
                        this.$nextTick(() => this.renderCharts());
                    }
                }
            } catch (e) {
                console.error('Failed to load process history:', e);
            } finally {
                this.procLoading = false;
            }
        },

        // ── Group View ────────────────────────────────────────────────
        async loadGroupView() {
            const prefix = this.procSearch.trim();
            if (!prefix) return;
            this.procLoading  = true;
            this.procPoints   = [];
            this.matchedNames = [];
            this.procSelected = prefix;   // used for chart title

            try {
                const url  = `../api/process_history_api.php?view=group&prefix=${encodeURIComponent(prefix)}&range=${this.procRange}`;
                const res  = await fetch(url);
                const data = await res.json();

                if (data.success) {
                    this.procPoints    = data.points       || [];
                    this.matchedNames  = data.matched_names || [];
                    this.procRangeLabel = data.label       || this.procRange;
                    if (this.procPoints.length > 0) {
                        this.$nextTick(() => this.renderCharts());
                    }
                }
            } catch (e) {
                console.error('Failed to load group history:', e);
            } finally {
                this.procLoading = false;
            }
        },

        // Route Load button depending on current mode
        loadView() {
            if (this.matchMode === 'group') {
                this.loadGroupView();
            } else {
                this.loadProcessView();
            }
        },

        renderCharts() {
            const labels   = this.procPoints.map(p => p.timestamp);
            const cpuAvg   = this.procPoints.map(p => p.cpu_avg);
            const cpuMax   = this.procPoints.map(p => p.cpu_max);
            const memAvg   = this.procPoints.map(p => p.mem_avg_mb);
            const memMax   = this.procPoints.map(p => p.mem_max_mb);
            const tcpConns = this.procPoints.map(p => p.tcp_conns_avg);
            const hasTcp   = tcpConns.some(v => v != null);

            // Labels adapt to match mode — group mode uses SUM so we say Total/Peak
            const isGroup    = this.matchMode === 'group';
            const cpuAvgLbl  = isGroup ? 'CPU Total %'  : 'CPU Avg %';
            const cpuMaxLbl  = isGroup ? 'CPU Peak %'   : 'CPU Max %';
            const memAvgLbl  = isGroup ? 'Mem Total MB'  : 'Mem Avg MB';
            const memMaxLbl  = isGroup ? 'Mem Peak MB'   : 'Mem Max MB';
            const tcpAvgLbl  = isGroup ? 'TCP Total Conns' : 'TCP Avg Conns';

            const isDark   = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
            const textColor = isDark ? '#9ca3af' : '#6b7280';

            const baseOpts = {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { labels: { color: textColor, boxWidth: 12, font: { size: 11 } } },
                    tooltip: { mode: 'index', intersect: false },
                },
                scales: {
                    x: {
                        ticks: { color: textColor, maxTicksLimit: 8, font: { size: 10 } },
                        grid:  { color: gridColor },
                    },
                    y: {
                        ticks: { color: textColor, font: { size: 10 } },
                        grid:  { color: gridColor },
                        beginAtZero: true,
                    },
                },
            };

            // Destroy old charts before re-rendering
            if (this.cpuChart) { this.cpuChart.destroy(); this.cpuChart = null; }
            if (this.memChart) { this.memChart.destroy(); this.memChart = null; }
            if (this.tcpChart) { this.tcpChart.destroy(); this.tcpChart = null; }

            this.cpuChart = new Chart(document.getElementById('procCpuChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: cpuAvgLbl, data: cpuAvg, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', borderWidth: 1.5, pointRadius: 0, fill: true },
                        { label: cpuMaxLbl, data: cpuMax, borderColor: '#f97316', backgroundColor: 'transparent',          borderWidth: 1,   pointRadius: 0, borderDash: [4,3] },
                    ],
                },
                options: { ...baseOpts, scales: { ...baseOpts.scales, y: { ...baseOpts.scales.y, suggestedMax: 10 } } },
            });

            this.memChart = new Chart(document.getElementById('procMemChart'), {
           type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: memAvgLbl, data: memAvg, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', borderWidth: 1.5, pointRadius: 0, fill: true },
                        { label: memMaxLbl, data: memMax, borderColor: '#6366f1', backgroundColor: 'transparent',           borderWidth: 1,   pointRadius: 0, borderDash: [4,3] },
                    ],
                },
                options: baseOpts,
            });

            if (hasTcp) {
                this.tcpChart = new Chart(document.getElementById('procTcpChart'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            { label: tcpAvgLbl, data: tcpConns, borderColor: '#14b8a6', backgroundColor: 'rgba(20,184,166,0.1)', borderWidth: 1.5, pointRadius: 0, fill: true, spanGaps: true },
                        ],
                    },
                    options: { ...baseOpts, scales: { ...baseOpts.scales, y: { ...baseOpts.scales.y, suggestedMax: 5, ticks: { ...baseOpts.scales.y.ticks, precision: 0 } } } },
                });
            }
        },

        // Format MB nicely
        fmtMb(mb) {
            if (mb >= 1024) return (mb / 1024).toFixed(2) + ' GB';
            if (mb >= 1)    return mb.toFixed(1) + ' MB';
            return (mb * 1024).toFixed(0) + ' KB';
        },
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
