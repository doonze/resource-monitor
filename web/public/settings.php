<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div x-data="settings()" x-init="init()">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Settings</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Configure alert thresholds and application settings
        </p>
    </div>
    
    <!-- Alert Thresholds -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Alert Thresholds</h2>
            <button 
                @click="showAddModal = true"
                class="px-4 py-2 bg-walmart-blue hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                + Add Threshold
            </button>
        </div>
        
        <!-- Temperature Unit Preference -->
        <div class="mb-6 p-4 bg-blue-50 dark:bg-gray-700 rounded-md">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Temperature Unit Preference</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Choose how temperatures are displayed throughout the dashboard</p>
                </div>
                <div class="flex space-x-2">
                    <button 
                        @click="setTempUnit('C')"
                        :class="tempUnit === 'C' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300'"
                        class="px-4 py-2 rounded-md text-sm font-medium border border-gray-300 dark:border-gray-500 hover:bg-blue-50 dark:hover:bg-gray-500 transition-colors">
                        °C (Celsius)
                    </button>
                    <button 
                        @click="setTempUnit('F')"
                        :class="tempUnit === 'F' ? 'bg-walmart-blue text-white' : 'bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300'"
                        class="px-4 py-2 rounded-md text-sm font-medium border border-gray-300 dark:border-gray-500 hover:bg-blue-50 dark:hover:bg-gray-500 transition-colors">
                        °F (Fahrenheit)
                    </button>
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Metric</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Condition</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Threshold</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <template x-for="threshold in thresholds" :key="threshold.id">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white" x-text="threshold.name"></td>
                            <td class="px-6 py-4 whitespap text-sm text-gray-500 dark:text-gray-400" x-text="threshold.metric"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="threshold.condition === 'gt' ? '>' : (threshold.condition === 'lt' ? '<' : '=')"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                <span x-text="formatThresholdValue(threshold)"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="threshold.duration_seconds + 's'"></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button 
                                    @click="toggleEnabled(threshold.id, !threshold.enabled)"
                                    :class="threshold.enabled ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                    <span x-text="threshold.enabled ? 'Enabled' : 'Disabled'"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-me">
                                <button @click="editThreshold(threshold)" class="text-walmart-blue hover:text-blue-700 mr-3">Edit</button>
                                <button @click="deleteThreshold(threshold.id)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Intelligence System -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">Intelligence System</h2>

        <div x-show="intelligenceLoading" class="text-center py-8 text-gray-400 dark:text-gray-500">Loading intelligence data...</div>

        <div x-show="!intelligenceLoading">

            <!-- Learning Status -->
            <div class="mb-6 p-4 bg-blue-50 dark:bg-gray-700 rounded-md">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Learning Status</h3>
                    <span class="text-xs font-semibold px-2 py-1 rounded-full"
                        :class="intelligenceData.learning_status.has_baseline
                            ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100'
                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100'"
                        x-text="intelligenceData.learning_status.has_baseline ? '✓ Active' : '◌ Learning'"></span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 mb-1">
                    <div class="h-2 rounded-full transition-all"
                        :class="intelligenceData.learning_status.has_baseline ? 'bg-green-500' : 'bg-yellow-400'"
                        :style="'width: ' + Math.min(Math.round(intelligenceData.learning_status.data_days / intelligenceData.learning_status.min_days * 100), 100) + '%'"></div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400"
                    x-text="intelligenceData.learning_status.data_days + ' of ' + intelligenceData.learning_status.min_days + ' days collected — ' + (intelligenceData.learning_status.has_baseline ? 'baselines are active' : 'still building history')"></p>
            </div>

            <!-- Sigma Sensitivity -->
            <div class="mb-6 p-4 border border-gray-200 dark:border-gray-600 rounded-md">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Alert Sensitivity (Sigma Thresholds)</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Controls how many standard deviations above normal triggers each alert level</p>
                    </div>
                    <button @click="saveSigmas()"
                        :disabled="sigmaSaving"
                        class="px-3 py-1.5 bg-walmart-blue hover:bg-blue-700 disabled:opacity-50 text-white rounded text-sm font-medium">
                        <span x-text="sigmaSaving ? 'Saving...' : 'Save'"></span>
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-yellow-600 dark:text-yellow-400 mb-1">↑ Elevated (σ)</label>
                        <input type="number" step="0.1" min="0.1" x-model="sigmaForm.elevated"
                            class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        <p class="text-xs text-gray-400 mt-1">Above mean by this many σ</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-orange-600 dark:text-orange-400 mb-1">↑↑ High (σ)</label>
                        <input type="number" step="0.1" min="0.1" x-model="sigmaForm.high"
                            class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        <p class="text-xs text-gray-400 mt-1">Above mean by this many σ</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-blue-600 dark:text-blue-400 mb-1">↓ Low (σ)</label>
                        <input type="number" step="0.1" min="0.1" x-model="sigmaForm.low"
                            class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        <p class="text-xs text-gray-400 mt-1">Below mean by this many σ</p>
                    </div>
                </div>

                <!-- Sustained Alert Windows -->
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-3">
                        Sustained Alert Windows
                        <span class="font-normal text-gray-400">— metric must be elevated for this long before alerting</span>
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                CPU / Memory / Network / Disk I/O (minutes)
                            </label>
                            <input type="number" step="5" min="10" max="480"
                                   x-model="sigmaForm.sustainedMins"
                                   class="block w-full rounded border-gray-300 dark:border-gray-600
                                          dark:bg-gray-700 dark:text-white text-sm">
                            <p class="text-xs text-gray-400 mt-1">Default: 60 min</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                Temperature (minutes)
                            </label>
                            <input type="number" step="5" min="5" max="120"
                                   x-model="sigmaForm.tempSustainedMins"
                                   class="block w-full rounded border-gray-300 dark:border-gray-600
                                          dark:bg-gray-700 dark:text-white text-sm">
                            <p class="text-xs text-gray-400 mt-1">Default: 15 min — sooner is safer for thermals</p>
                        </div>
                    </div>
                </div>

                <!-- Alert Cooldown -->
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-3">
                        Alert Cooldown Period
                        <span class="font-normal text-gray-400">— after an alert resolves, block it from re-firing for this long</span>
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                Cooldown (hours)
                            </label>
                            <input type="number" step="1" min="0" max="72"
                                   x-model="sigmaForm.cooldownHours"
                                   class="block w-full rounded border-gray-300 dark:border-gray-600
                                          dark:bg-gray-700 dark:text-white text-sm">
                            <p class="text-xs text-gray-400 mt-1">Default: 4h — set to 0 to disable cooldown</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Per-Metric Configuration -->
            <div>
                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Per-Metric Guard Rails &amp; Mode</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Guard rails are absolute hard stops — they fire before the baseline system.
                    Dynamic mode uses baseline + sigma math. Manual Override uses the fixed threshold from Alert Rules.
                    Disk &amp; network inputs are in MB/s.
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pr-4">Metric</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pr-4">Mode</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pr-4">⚠ Warning Floor</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pr-4">✗ Critical Ceiling</th>
                                <th class="pb-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pr-4">Current Baseline</th>
                                <th class="pb-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-for="m in metricList" :key="m.key">
                                <tr class="py-2">
                                    <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white whitespace-nowrap" x-text="m.label"></td>
                                    <td class="py-3 pr-4">
                                        <select x-model="metricForms[m.key].manual_override"
                                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs py-1">
                                            <option value="false">Dynamic</option>
                                            <option value="true">Manual Override</option>
                                        </select>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div class="flex items-center space-x-1">
                                            <input type="number" step="any" x-model="metricForms[m.key].floor"
                                                placeholder="—"
                                                class="w-24 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs py-1">
                                            <span class="text-xs text-gray-400" x-text="metricUnit(m.key)"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div class="flex items-center space-x-1">
                                            <input type="number" step="any" x-model="metricForms[m.key].ceiling"
                                                placeholder="—"
                                                class="w-24 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs py-1">
                                            <span class="text-xs text-gray-400" x-text="metricUnit(m.key)"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" x-text="formatBaselineStat(m)"></td>
                                    <td class="py-3">
                                        <button @click="saveMetricRule(m.key)"
                                            :disabled="metricSaving[m.key]"
                                            class="px-3 py-1 bg-walmart-blue hover:bg-blue-700 disabled:opacity-50 text-white rounded text-xs font-medium whitespace-nowrap">
                                            <span x-text="metricSaving[m.key] ? 'Saving...' : 'Save'"></span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Alert Notifications -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Email Alert Notifications</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Alerts are emailed by the collector service. Changes take effect on the next check cycle — no restart needed.</p>
            </div>
            <button @click="testAllRecipients()"
                    :disabled="testingAll || recipients.filter(r=>r.enabled).length === 0"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-md text-sm font-medium">
                <span x-text="testingAll ? 'Sending...' : 'Test All'"></span>
            </button>
        </div>

        <!-- Email Config -->
        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-md">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Email Configuration</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">From Address</label>
                    <input type="email" x-model="emailConfig.email_from"
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Disk Full Alert (%)</label>
                    <input type="number" x-model="emailConfig.disk_full_pct" min="50" max="99" step="1"
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Space Change Alert (%/hr)</label>
                    <input type="number" x-model="emailConfig.disk_change_pct" min="1" max="100" step="0.5"
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Space Change Alert (GB/hr)</label>
                    <input type="number" x-model="emailConfig.disk_change_gb" min="1" step="1"
                           class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Disk space change alert fires if <strong>either</strong> the % or GB threshold is exceeded in a 1-hour window — whichever triggers first.</p>
            <div class="mt-3 flex items-center gap-3">
                <button @click="saveEmailConfig()"
                        :disabled="emailConfigSaving"
                        class="px-4 py-1.5 bg-walmart-blue hover:bg-blue-700 disabled:opacity-50 text-white rounded text-sm font-medium">
                    <span x-text="emailConfigSaving ? 'Saving...' : 'Save Config'"></span>
                </button>
                <span x-show="emailConfigSaved" class="text-green-600 text-sm">&#10003; Saved</span>
            </div>
        </div>

        <!-- Recipients table -->
        <div x-show="recipients.length > 0">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm mb-4">
                <thead>
                    <tr class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="pb-3 text-left">Recipient</th>
                        <th class="pb-3 text-center">Enabled</th>
                        <th class="pb-3 text-center">Min Severity</th>
                        <th class="pb-3 text-center" title="Guard Rail (current)">Guard Rail</th>
                        <th class="pb-3 text-center" title="Sustained High">Sustained</th>
                        <th class="pb-3 text-center" title="Disk Full">Disk Full</th>
                        <th class="pb-3 text-center" title="Disk Space Change">Disk Change</th>
                        <th class="pb-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-for="rec in recipients" :key="rec.id">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="py-3 pr-4">
                                <p class="font-medium text-gray-900 dark:text-white" x-text="rec.display_name || rec.email"></p>
                                <p x-show="rec.display_name" class="text-xs text-gray-500" x-text="rec.email"></p>
                            </td>
                            <td class="py-3 text-center">
                                <button @click="patchRecipient(rec.id, { enabled: !rec.enabled })"
                                        :class="rec.enabled ? 'bg-green-100 text-green-800 dark:bg-green-800/40 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                        class="px-2 py-0.5 rounded-full text-xs font-semibold">
                                    <span x-text="rec.enabled ? 'On' : 'Off'"></span>
                                </button>
                            </td>
                            <td class="py-3 text-center">
                                <select :value="rec.min_severity"
                                        @change="patchRecipient(rec.id, { min_severity: $event.target.value })"
                                        class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-0.5">
                                    <option value="warning">Warning+</option>
                                    <option value="critical">Critical only</option>
                                </select>
                            </td>
                            <!-- Category toggles -->
                            <template x-for="cat in ['notify_current','notify_sustained','notify_disk_full','notify_disk_change']" :key="cat">
                                <td class="py-3 text-center">
                                    <button @click="patchRecipient(rec.id, { [cat]: !rec[cat] })"
                                            :class="rec[cat] ? 'text-walmart-blue' : 'text-gray-300 dark:text-gray-600'"
                                            class="text-lg leading-none" title="Click to toggle">
                                        &#9679;
                                    </button>
                                </td>
                            </template>
                            <td class="py-3 text-right whitespace-nowrap">
                                <span :class="testClass(rec.id)" class="mr-2 font-bold text-sm"
                                      :title="testPolls[rec.id]?.error ?? ''"
                                      x-text="testIcon(rec.id)"></span>
                                <button @click="testRecipient(rec.id)"
                                        :disabled="['pending','sending'].includes(testPolls[rec.id]?.status)"
                                        class="px-2 py-1 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded mr-1 disabled:opacity-50">
                                    <span x-text="['pending','sending'].includes(testPolls[rec.id]?.status) ? '...' : 'Test'"></span>
                                </button>
                                <button @click="deleteRecipient(rec.id, rec.email)"
                                        class="px-2 py-1 text-xs bg-red-50 hover:bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="recipients.length === 0" class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            No recipients yet. Add one below.
        </div>

        <!-- Add recipient form -->
        <div>
            <button x-show="!recipientFormVisible" @click="recipientFormVisible = true"
                    class="px-4 py-2 border border-dashed border-gray-300 dark:border-gray-600 rounded text-sm text-gray-500 dark:text-gray-400 hover:border-walmart-blue hover:text-walmart-blue w-full">
                + Add Recipient
            </button>
            <div x-show="recipientFormVisible" x-cloak class="border dark:border-gray-700 rounded-md p-4">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Add New Recipient</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Display Name (optional)</label>
                        <input type="text" x-model="recipientForm.display_name" placeholder="e.g. Justin"
                               class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Email Address *</label>
                        <input type="email" x-model="recipientForm.email" placeholder="user@example.com" required
                               class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm shadow-sm">
                    </div>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-2 mb-3 text-sm text-gray-700 dark:text-gray-300">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="recipientForm.notify_current" class="rounded">
                        Guard Rail
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="recipientForm.notify_sustained" class="rounded">
                        Sustained High
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="recipientForm.notify_disk_full" class="rounded">
                        Disk Full
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="recipientForm.notify_disk_change" class="rounded">
                        Disk Space Change
                    </label>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">Min severity:</span>
                        <select x-model="recipientForm.min_severity"
                                class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-0.5">
                            <option value="warning">Warning+</option>
                            <option value="critical">Critical only</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button @click="addRecipient()" :disabled="recipientSaving || !recipientForm.email"
                            class="px-4 py-1.5 bg-walmart-blue hover:bg-blue-700 disabled:opacity-50 text-white rounded text-sm font-medium">
                        <span x-text="recipientSaving ? 'Adding...' : 'Add Recipient'"></span>
                    </button>
                    <button @click="recipientFormVisible = false"
                            class="px-4 py-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-900 rounded text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Alert Banner Display -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <div class="flex items-start justify-between mb-1">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Dashboard Alert Banner</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Choose which alert types appear in the banner on the main dashboard.
                    These are global UI settings — independent of who receives emails.
                    Changes take effect on the dashboard’s next 60-second poll.
                </p>
            </div>
            <button @click="saveDashAlerts()"
                    :disabled="dashAlerts.saving"
                    class="flex-shrink-0 ml-6 px-4 py-1.5 bg-walmart-blue hover:bg-blue-700
                           disabled:opacity-50 text-white rounded text-sm font-medium">
                <span x-text="dashAlerts.saving ? 'Saving...' : dashAlerts.saved ? 'Saved ✓' : 'Save'"></span>
            </button>
        </div>

        <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">

            <!-- CPU -->
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200
                          dark:border-gray-700 cursor-pointer
                          hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <input type="checkbox" x-model="dashAlerts.dash_show_cpu"
                       class="rounded border-gray-300 text-walmart-blue
                              focus:ring-walmart-blue dark:border-gray-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    🖥️ CPU
                </span>
            </label>

            <!-- Memory -->
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200
                          dark:border-gray-700 cursor-pointer
                          hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <input type="checkbox" x-model="dashAlerts.dash_show_memory"
                       class="rounded border-gray-300 text-walmart-blue
                              focus:ring-walmart-blue dark:border-gray-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    🧠 Memory
                </span>
            </label>

            <!-- Disk I/O -->
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200
                          dark:border-gray-700 cursor-pointer
                          hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <input type="checkbox" x-model="dashAlerts.dash_show_disk_io"
                       class="rounded border-gray-300 text-walmart-blue
                              focus:ring-walmart-blue dark:border-gray-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    💿 Disk I/O
                </span>
            </label>

            <!-- Network -->
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200
                          dark:border-gray-700 cursor-pointer
                          hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <input type="checkbox" x-model="dashAlerts.dash_show_network"
                       class="rounded border-gray-300 text-walmart-blue
                              focus:ring-walmart-blue dark:border-gray-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    🌐 Network
                </span>
            </label>

            <!-- Temperature -->
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200
                          dark:border-gray-700 cursor-pointer
                          hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <input type="checkbox" x-model="dashAlerts.dash_show_temp"
                       class="rounded border-gray-300 text-walmart-blue
                              focus:ring-walmart-blue dark:border-gray-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    🌡️ Temperature
                </span>
            </label>

            <!-- Disk Full -->
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200
                          dark:border-gray-700 cursor-pointer
                          hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <input type="checkbox" x-model="dashAlerts.dash_show_disk_full"
                       class="rounded border-gray-300 text-walmart-blue
                              focus:ring-walmart-blue dark:border-gray-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    💾 Disk Full
                </span>
            </label>

            <!-- Disk Change -->
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200
                          dark:border-gray-700 cursor-pointer
                          hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <input type="checkbox" x-model="dashAlerts.dash_show_disk_change"
                       class="rounded border-gray-300 text-walmart-blue
                              focus:ring-walmart-blue dark:border-gray-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    📈 Disk Change
                </span>
            </label>

        </div>
    </div>

    <!-- Add/Edit Threshold Modal -->
    <div 
        x-show="showAddModal || editingThreshold" 
        x-cloak
        class="fixed z-10 inset-0 overflow-y-auto" 
        @click.self="showAddModal = false; editingThreshold = null">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4" x-text="editingThreshold ? 'Edit Threshold' : 'Add Threshold'"></h3>
                
                <form @submit.prevent="saveThreshold()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                            <input 
                                type="text" 
                                x-model="formData.name"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Metric</label>
                            <select 
                                x-model="formData.metric"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                                <option value="cpu_percent">CPU Percentage</option>
                                <option value="mem_percent">Memory Percentage</option>
                                <option value="swap_percent">Swap Percentage</option>
                                <option value="disk_percent">Disk Percentage</option>
                                <option value="load_1min">Load Average (1min)</option>
                                <option value="temp_avg">Average Temperature</option>
                                <option value="disk_io_write">Disk I/O Write Speed</option>
                                <option value="network_sent">Network Sent Speed</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Condition</label>
                            <select 
                                x-model="formData.condition"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                                <option value="gt">Greater than (>)</option>
                                <option value="lt">Less than (<)</option>
                                <option value="eq">Equal to (=)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Threshold Value</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <input 
                                    type="number" 
                                    x-model="formData.threshold"
                                    step="0.01"
                                    required
                                    :class="needsUnit(formData.metric) ? 'pr-16' : ''"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                                <div x-show="needsUnit(formData.metric)" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 dark:text-gray-400 sm:text-sm">
                                        <span x-show="formData.metric === 'temp_avg'" x-text="'°' + tempUnit"></span>
                                        <span x-show="formData.metric.includes('percent')">%</span>
                                        <span x-show="formData.metric === 'disk_io_write' || formData.metric === 'network_sent'">MB/s</span>
                                    </span>
                                </div>
                            </div>
                            <p x-show="formData.metric === 'temp_avg'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <span x-show="tempUnit === 'F'">Value will be converted to Celsius for storage (Normal: <176°F, High: 176-194°F, Critical: ≥194°F)</span>
                                <span x-show="tempUnit === 'C'">Normal: <80°C, High: 80-90°C, Critical: ≥90°C</span>
                            </p>
                            <p x-show="formData.metric === 'disk_io_write'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Normal: <10 MB/s, Active: 10-50 MB/s, Busy: ≥50 MB/s
                            </p>
                            <p x-show="formData.metric === 'network_sent'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Normal: <1 MB/s, Active: 1-10 MB/s, Busy: ≥10 MB/s
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Duration (seconds)</label>
                            <input 
                                type="number" 
                                x-model="formData.duration_seconds"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-walmart-blue focus:ring-walmart-blue">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Alert triggers after value exceeds threshold for this duration</p>
                        </div>
                        
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                x-model="formData.enabled"
                                class="h-4 w-4 text-walmart-blue focus:ring-walmart-blue border-gray-300 dark:border-gray-600 rounded">
                            <label class="ml-2 block text-sm text-gray-700 dark:text-gray-300">Enabled</label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button 
                            type="button"
                            @click="showAddModal = false; editingThreshold = null"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 bg-walmart-blue hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function settings() {
    return {
        thresholds: [],
        showAddModal: false,
        editingThreshold: null,
        tempUnit: localStorage.getItem('tempUnit') || 'F',
        // Intelligence state
        intelligenceLoading: true,
        intelligenceData: {
            learning_status: { has_baseline: false, data_days: 0, min_days: 7 },
            app_settings: {},
            metrics: {},
        },
        metricList: [],
        sigmaForm: { elevated: 1.0, high: 2.0, low: 1.5 },
        metricForms: {},
        sigmaSaving: false,
        metricSaving: {},

        // ── Email alert state ─────────────────────────────────────────────────────────────────
        emailConfig: {
            email_from:      'monitor@localhost',
            disk_change_pct: 5,
            disk_change_gb:  50,
            disk_full_pct:   90,
        },
        emailConfigSaving: false,
        emailConfigSaved:  false,

        // ── Dashboard alert banner display settings ───────────────────────────────
        dashAlerts: {
            dash_show_cpu:         true,
            dash_show_memory:      true,
            dash_show_disk_io:     true,
            dash_show_network:     true,
            dash_show_temp:        true,
            dash_show_disk_full:   true,
            dash_show_disk_change: true,
            saving: false,
            saved:  false,
        },
        recipients: [],
        recipientForm: {
            display_name:      '',
            email:             '',
            enabled:           true,
            min_severity:      'warning',
            notify_current:    true,
            notify_sustained:  true,
            notify_disk_full:  true,
            notify_disk_change: true,
        },
        recipientFormVisible: false,
        recipientSaving: false,
        testingAll: false,   // true while the Test All POST is in-flight
        testPolls:  {},      // { recipient_id: { status, error, queueId } }
                             //   status: pending | sending | success | failed
        formData: {
            name: '',
            metric: 'cpu_percent',
            condition: 'gt',
            threshold: 85,
            duration_seconds: 300,
            enabled: true
        },
        
        async init() {
            await this.fetchThresholds();
            await this.fetchIntelligence();
            await this.fetchEmailConfig();
        },
        
        async fetchThresholds() {
            try {
                const response = await fetch('../api/settings_api.php');
                const data = await response.json();
                this.thresholds = data.thresholds;
            } catch (error) {
                console.error('Error fetching thresholds:', error);
                alert('Failed to load thresholds');
            }
        },
        
        setTempUnit(unit) {
            this.tempUnit = unit;
            localStorage.setItem('tempUnit', unit);
            // Save to DB so the collector formats alert messages in the right unit
            fetch('../api/alert_recipients_api.php', {
                method:  'PUT',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ config: { temp_unit: unit } }),
            }).catch(e => console.error('Failed to save temp_unit:', e));
            // Trigger a custom event so the dashboard can update
            window.dispatchEvent(new CustomEvent('tempUnitChanged', { detail: unit }));
        },
        
        celsiusToFahrenheit(celsius) {
            return (celsius * 9/5) + 32;
        },
        
        fahrenheitToCelsius(fahrenheit) {
            return (fahrenheit - 32) * 5/9;
        },
        
        formatThresholdValue(threshold) {
            if (threshold.metric === 'temp_avg') {
                // Threshold is stored in Celsius, display in user's preferred unit
                const value = this.tempUnit === 'F' ? this.celsiusToFahrenheit(threshold.threshold) : threshold.threshold;
                return value.toFixed(1) + '°' + this.tempUnit;
            } else if (threshold.metric.includes('percent')) {
                return threshold.threshold + '%';
            } else if (threshold.metric === 'disk_io_write' || threshold.metric === 'network_sent') {
                return threshold.threshold + ' MB/s';
            }
            return threshold.threshold;
        },
        
        needsUnit(metric) {
            return metric === 'temp_avg' ||
                   metric.includes('percent') ||
                   metric === 'disk_io_write' ||
                   metric === 'network_sent';
        },
        
        editThreshold(threshold) {
            this.editingThreshold = threshold;
            // Convert temperature threshold to display unit if needed
            let displayThreshold = threshold.threshold;
            if (threshold.metric === 'temp_avg' && this.tempUnit === 'F') {
                displayThreshold = this.celsiusToFahrenheit(threshold.threshold);
            }
            this.formData = {
                id: threshold.id,
                name: threshold.name,
                metric: threshold.metric,
                condition: threshold.condition,
                threshold: displayThreshold,
                duration_seconds: threshold.duration_seconds,
                enabled: threshold.enabled
            };
        },
        
        async saveThreshold() {
            try {
                const method = this.editingThreshold ? 'PUT' : 'POST';
                
                // Convert temperature threshold to Celsius for storage if needed
                let thresholdValue = this.formData.threshold;
                if (this.formData.metric === 'temp_avg' && this.tempUnit === 'F') {
                    thresholdValue = this.fahrenheitToCelsius(this.formData.threshold);
                }
                
                const dataToSend = {
                    ...this.formData,
                    threshold: thresholdValue
                };
                
                const response = await fetch('../api/settings_api.php', {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataToSend)
                });
                
                if (response.ok) {
                    this.showAddModal = false;
                    this.editingThreshold = null;
                    this.formData = {
                        name: '',
                        metric: 'cpu_percent',
                        condition: 'gt',
                        threshold: 85,
                        duration_seconds: 300,
                        enabled: true
                    };
                    await this.fetchThresholds();
                } else {
                    const error = await response.json();
                    alert('Error: ' + error.error);
                }
            } catch (error) {
                console.error('Error saving threshold:', error);
                alert('Failed to save threshold');
            }
        },
        
        async toggleEnabled(id, enabled) {
            try {
                const response = await fetch('/resource-monitor/web/api/settings_api.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, enabled })
                });
                
                if (response.ok) {
                    await this.fetchThresholds();
                } else {
                    alert('Failed to update threshold');
                }
            } catch (error) {
                console.error('Error toggling threshold:', error);
                alert('Failed to update threshold');
            }
        },
        
        async deleteThreshold(id) {
            if (!confirm('Are you sure you want to delete this threshold?')) {
                return;
            }
            
            try {
                const response = await fetch('../api/settings_api.php?id=' + id, {
                    method: 'DELETE'
                });
                
                if (response.ok) {
                    await this.fetchThresholds();
                } else {
                    alert('Failed to delete threshold');
                }
            } catch (error) {
                console.error('Error deleting threshold:', error);
                alert('Failed to delete threshold');
            }
        },

        // ----------------------------------------------------------------
        // Intelligence methods
        // ----------------------------------------------------------------

        async fetchIntelligence() {
            try {
                const response = await fetch('../api/intelligence_api.php');
                const data = await response.json();
                this.intelligenceData    = data;
                this.intelligenceLoading = false;
                // Sigma form
                this.sigmaForm.elevated         = data.app_settings.sigma_elevated;
                this.sigmaForm.high              = data.app_settings.sigma_high;
                this.sigmaForm.low               = data.app_settings.sigma_low;
                this.sigmaForm.sustainedMins     = data.app_settings.sustained_minutes;
                this.sigmaForm.tempSustainedMins = data.app_settings.temp_sustained_minutes;
                this.sigmaForm.cooldownHours     = data.app_settings.alert_cooldown_hours;
                // Build flat metricList array for x-for + per-metric forms
                this.metricList = Object.entries(data.metrics).map(([key, m]) => ({ key, ...m }));
                this.metricForms = {};
                this.metricSaving = {};
                for (const [key, m] of Object.entries(data.metrics)) {
                    this.metricForms[key] = {
                        manual_override: String(m.manual_override),
                        floor:   this.rawToDisplay(key, m.guard_rail_floor),
                        ceiling: this.rawToDisplay(key, m.guard_rail_ceiling),
                    };
                    this.metricSaving[key] = false;
                }
            } catch (error) {
                console.error('Error fetching intelligence data:', error);
            }
        },

        async saveSigmas() {
            this.sigmaSaving = true;
            try {
                const response = await fetch('../api/intelligence_api.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        app_settings: {
                            sigma_elevated:         parseFloat(this.sigmaForm.elevated)  || 1.0,
                            sigma_high:             parseFloat(this.sigmaForm.high)      || 2.0,
                            sigma_low:              parseFloat(this.sigmaForm.low)       || 1.5,
                            sustained_minutes:      parseInt(this.sigmaForm.sustainedMins,     10) || 60,
                            temp_sustained_minutes: parseInt(this.sigmaForm.tempSustainedMins, 10) || 15,
                            alert_cooldown_hours:   isNaN(parseInt(this.sigmaForm.cooldownHours, 10))
                                                    ? 4 : parseInt(this.sigmaForm.cooldownHours, 10),
                        }
                    })
                });
                if (!response.ok) throw new Error('Save failed');
                await this.fetchIntelligence();
            } catch (error) {
                alert('Failed to save sigma settings');
            } finally {
                this.sigmaSaving = false;
            }
        },

        async saveMetricRule(key) {
            this.metricSaving[key] = true;
            const form = this.metricForms[key];
            try {
                const response = await fetch('../api/intelligence_api.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        metric_rule: {
                            metric:           key,
                            manual_override:  form.manual_override === 'true',
                            guard_rail_floor:   this.displayToRaw(key, form.floor),
                            guard_rail_ceiling: this.displayToRaw(key, form.ceiling),
                        }
                    })
                });
                if (!response.ok) throw new Error('Save failed');
                await this.fetchIntelligence();
            } catch (error) {
                alert('Failed to save rule for ' + key);
            } finally {
                this.metricSaving[key] = false;
            }
        },

        // Bytes metrics stored as raw bytes in DB; display/accept in MB/s
        rawToDisplay(key, raw) {
            if (raw === null || raw === undefined || raw === '') return '';
            if (this.isBytesMetric(key)) return (raw / 1048576).toFixed(2);
            if (key === 'temp_avg' && this.tempUnit === 'F') return this.celsiusToFahrenheit(raw).toFixed(1);
            return raw;
        },

        displayToRaw(key, display) {
            if (display === null || display === undefined || display === '') return null;
            const n = parseFloat(display);
            if (isNaN(n)) return null;
            if (this.isBytesMetric(key)) return Math.round(n * 1048576);
            if (key === 'temp_avg' && this.tempUnit === 'F') return this.fahrenheitToCelsius(n);
            return n;
        },

        isBytesMetric(key) {
            return ['disk_io_write', 'disk_io_read', 'net_sent', 'net_recv'].includes(key);
        },

        metricUnit(key) {
            if (this.isBytesMetric(key)) return 'MB/s';
            if (key === 'temp_avg') return '°' + this.tempUnit;
            return '%';
        },

        formatBaselineStat(m) {
            if (!m.baseline || m.baseline.mean === null) return '\u25cc learning';
            const fmt = (v) => {
                if (m.unit === 'bytes')   return (v / 1048576).toFixed(2) + ' MB/s';
                if (m.unit === 'celsius') return (this.tempUnit === 'F'
                    ? this.celsiusToFahrenheit(v).toFixed(1) + '\u00b0F'
                    : v.toFixed(1) + '\u00b0C');
                return v.toFixed(1) + '%';
            };
            return m.baseline.stddev !== null
                ? 'avg ' + fmt(m.baseline.mean) + ' \u00b1 ' + fmt(m.baseline.stddev)
                : 'avg ' + fmt(m.baseline.mean);
        },

        // ----------------------------------------------------------------
        // Email alert methods
        // ----------------------------------------------------------------

        async fetchEmailConfig() {
            try {
                const r    = await fetch('../api/alert_recipients_api.php');
                const data = await r.json();
                this.recipients  = data.recipients ?? [];
                const cfg = data.config ?? {};
                Object.assign(this.emailConfig, cfg);
                // Load dashboard display toggles (stored as 0/1 in DB, coerce to bool)
                const dashKeys = ['dash_show_cpu','dash_show_memory','dash_show_disk_io',
                                  'dash_show_network','dash_show_temp',
                                  'dash_show_disk_full','dash_show_disk_change'];
                for (const k of dashKeys) {
                    if (k in cfg) this.dashAlerts[k] = !!cfg[k];
                }
                // Sync temp unit from DB — DB is source of truth for the collector
                if (cfg.temp_unit) {
                    this.tempUnit = cfg.temp_unit;
                    localStorage.setItem('tempUnit', cfg.temp_unit);
                }
            } catch (e) { console.error('fetchEmailConfig:', e); }
        },

        async saveDashAlerts() {
            this.dashAlerts.saving = true;
            try {
                const config = {};
                const keys = ['dash_show_cpu','dash_show_memory','dash_show_disk_io',
                              'dash_show_network','dash_show_temp',
                              'dash_show_disk_full','dash_show_disk_change'];
                keys.forEach(k => { config[k] = this.dashAlerts[k] ? 1 : 0; });
                const r = await fetch('../api/alert_recipients_api.php', {
                    method:  'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ config }),
                });
                if (!r.ok) throw new Error('Save failed');
                this.dashAlerts.saved = true;
                setTimeout(() => this.dashAlerts.saved = false, 3000);
            } catch (e) {
                alert('Failed to save dashboard alert settings');
            } finally {
                this.dashAlerts.saving = false;
            }
        },

        async saveEmailConfig() {
            this.emailConfigSaving = true;
            try {
                const r = await fetch('../api/alert_recipients_api.php', {
                    method:  'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ config: this.emailConfig }),
                });
                if (!r.ok) throw new Error('Save failed');
                this.emailConfigSaved = true;
                setTimeout(() => this.emailConfigSaved = false, 3000);
            } catch (e) {
                alert('Failed to save email config');
            } finally {
                this.emailConfigSaving = false;
            }
        },

        async addRecipient() {
            if (!this.recipientForm.email) return;
            this.recipientSaving = true;
            try {
                const r = await fetch('../api/alert_recipients_api.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(this.recipientForm),
                });
                const data = await r.json();
                if (!r.ok) { alert(data.error ?? 'Failed to add recipient'); return; }
                this.recipients.push(data.recipient);
                this.recipientForm = {
                    display_name: '', email: '', enabled: true, min_severity: 'warning',
                    notify_current: true, notify_sustained: true,
                    notify_disk_full: true, notify_disk_change: true,
                };
                this.recipientFormVisible = false;
            } catch (e) {
                alert('Failed to add recipient');
            } finally {
                this.recipientSaving = false;
            }
        },

        async patchRecipient(id, fields) {
            try {
                const r = await fetch('../api/alert_recipients_api.php', {
                    method:  'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ id, ...fields }),
                });
                const data = await r.json();
                if (!r.ok) { alert(data.error ?? 'Update failed'); return; }
                const idx = this.recipients.findIndex(x => x.id === id);
                if (idx !== -1) this.recipients[idx] = data.recipient;
            } catch (e) { alert('Update failed'); }
        },

        async deleteRecipient(id, email) {
            if (!confirm(`Remove ${email} from alert recipients?`)) return;
            try {
                const r = await fetch('../api/alert_recipients_api.php?id=' + id, { method: 'DELETE' });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(data.error || `HTTP ${r.status}`);
                this.recipients = this.recipients.filter(x => x.id !== id);
            } catch (e) { alert('Delete failed: ' + e.message); }
        },

        async testRecipient(id) {
            this.testPolls = { ...this.testPolls, [id]: { status: 'pending' } };
            try {
                const r    = await fetch('../api/test_alert_api.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ recipient_id: id }),
                });
                const data = await r.json();
                if (!r.ok) throw new Error(data.error || `HTTP ${r.status}`);
                this.testPolls[id].queueId = data.queue_id;
                this._pollTest(id, data.queue_id);
            } catch (e) {
                this.testPolls = { ...this.testPolls, [id]: { status: 'failed', error: e.message } };
            }
        },

        async _pollTest(recipientId, queueId, attempts = 0) {
            if (attempts > 15) {
                this.testPolls = { ...this.testPolls,
                    [recipientId]: { status: 'failed', error: 'Timed out — is the collector running?' }
                };
                return;
            }
            await new Promise(r => setTimeout(r, 2000));
            try {
                const r = await fetch(`../api/test_alert_api.php?queue_id=${queueId}`);
                if (r.status === 404) {
                    this.testPolls = { ...this.testPolls,
                        [recipientId]: { status: 'failed', error: 'Result expired — collector may be down' }
                    };
                    return;
                }
                const data = await r.json();
                if (data.status === 'pending' || data.status === 'sending') {
                    this._pollTest(recipientId, queueId, attempts + 1);
                } else {
                    this.testPolls = { ...this.testPolls,
                        [recipientId]: { status: data.status, error: data.error_message }
                    };
                }
            } catch (e) {
                this.testPolls = { ...this.testPolls,
                    [recipientId]: { status: 'failed', error: 'Poll request failed' }
                };
            }
        },

        async testAllRecipients() {
            this.testingAll = true;
            this.testPolls  = {};
            try {
                const r    = await fetch('../api/test_alert_api.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ recipient_id: null }),
                });
                const data = await r.json();
                if (!r.ok) throw new Error(data.error || `HTTP ${r.status}`);
                for (const { recipient_id, queue_id } of data.queued ?? []) {
                    this.testPolls[recipient_id] = { status: 'pending', queueId: queue_id };
                    this._pollTest(recipient_id, queue_id);
                }
            } catch (e) {
                alert('Test All failed: ' + e.message);
            } finally {
                this.testingAll = false;
            }
        },

        testIcon(id) {
            const r = this.testPolls[id];
            if (!r) return '';
            if (r.status === 'pending' || r.status === 'sending') return '\u23f3';
            return r.status === 'success' ? '\u2713' : '\u2717';
        },

        testClass(id) {
            const r = this.testPolls[id];
            if (!r) return '';
            if (r.status === 'pending' || r.status === 'sending')
                return 'text-yellow-500 dark:text-yellow-400';
            return r.status === 'success'
                ? 'text-green-600 dark:text-green-400'
                : 'text-red-500 dark:text-red-400';
        },

    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
