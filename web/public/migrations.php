<?php
$pageTitle = 'Database Migrations';
require_once __DIR__ . '/../includes/header.php';
?>

<div x-data="migrationManager()" x-init="init()" class="space-y-6">

    <!-- Page header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Database Migrations</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage schema versions &mdash; Resource Monitor
                <span class="ml-2 font-mono text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">
                    App&nbsp;v<?php echo e(APP_VERSION); ?>
                </span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button @click="dumpSchema()"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Dump Schema
            </button>
            <button @click="fetchStatus()"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors">
                <svg :class="loading ? 'animate-spin' : ''" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Status banner -->
    <div x-show="status" x-cloak>

        <!-- Up to date -->
        <div x-show="status && status.is_current && status.current_version >= 0"
             class="flex items-center gap-3 p-4 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
            <svg class="h-6 w-6 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="font-semibold text-green-800 dark:text-green-200">Schema is up to date</p>
                <p class="text-sm text-green-700 dark:text-green-300">
                    Running schema <strong x-text="status ? 'v' + status.current_version : ''"></strong> &mdash; matches app requirement.
                </p>
            </div>
        </div>

        <!-- Not initialized -->
        <div x-show="status && status.current_version < 0"
             class="flex items-start gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <svg class="h-6 w-6 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="font-semibold text-red-800 dark:text-red-200">Database not initialized</p>
                <p class="text-sm text-red-700 dark:text-red-300">
                    No migrations applied yet. Apply <strong>v000 &mdash; Initial Schema</strong> to create all tables.
                </p>
            </div>
        </div>

        <!-- Pending migrations -->
        <div x-show="status && !status.is_current && status.current_version >= 0"
             class="flex items-start gap-3 p-4 rounded-xl bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
            <svg class="h-6 w-6 text-yellow-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="font-semibold text-yellow-800 dark:text-yellow-200">Schema update required</p>
                <p class="text-sm text-yellow-700 dark:text-yellow-300">
                    Database is at schema <strong x-text="status ? 'v' + status.current_version : ''"></strong>
                    but this app requires <strong x-text="status ? 'v' + status.required_version : ''"></strong>.
                    <span x-text="status ? status.pending_count + ' migration(s) pending.' : ''"></span>
                </p>
            </div>
        </div>

    </div>

    <!-- Stats row -->
    <div x-show="status" x-cloak class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold text-gray-900 dark:text-white font-mono"
                 x-text="status ? (status.current_version >= 0 ? 'v' + status.current_version : '\u2014') : '\u2026'"></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Current Schema</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold text-gray-900 dark:text-white font-mono"
                 x-text="status ? 'v' + status.required_version : '\u2026'"></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Required Version</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold text-gray-900 dark:text-white font-mono"
                 x-text="status ? status.migrations.filter(function(m){ return m.status === 'applied'; }).length : '\u2026'"></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Applied</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold font-mono"
                 :class="status && status.pending_count > 0 ? 'text-yellow-500' : 'text-gray-900 dark:text-white'"
                 x-text="status ? status.pending_count : '\u2026'"></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pending</div>
        </div>
    </div>

    <!-- Action result log -->
    <div x-show="actionLog" x-cloak
         :class="actionLog && actionLog.success
                    ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                    : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'"
         class="rounded-xl border p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <svg x-show="actionLog && actionLog.success" class="h-5 w-5 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <svg x-show="actionLog && !actionLog.success" class="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <p class="font-medium text-sm"
                       :class="actionLog && actionLog.success ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'"
                       x-text="actionLog ? actionLog.message : ''"></p>
                    <p class="text-xs mt-1"
                       :class="actionLog && actionLog.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                       x-show="actionLog && actionLog.statements_run != null"
                       x-text="actionLog ? actionLog.statements_run + ' / ' + actionLog.total_statements + ' statements  \u00b7  ' + actionLog.execution_ms + 'ms' : ''"></p>
                    <p class="text-xs mt-0.5 font-mono text-green-600 dark:text-green-400"
                       x-show="actionLog && actionLog.filepath"
                       x-text="actionLog ? 'Saved \u2192 ' + actionLog.filepath + '  (' + actionLog.size_fmt + ')' : ''"></p>
                    <template x-if="actionLog && actionLog.errors && actionLog.errors.length">
                        <div class="mt-2 space-y-1">
                            <template x-for="err in actionLog.errors" :key="err.statement_preview">
                                <div class="font-mono text-xs bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded p-2">
                                    <span class="font-semibold">Error: </span><span x-text="err.error"></span><br>
                                    <span class="opacity-70" x-text="err.statement_preview"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
            <button @click="actionLog = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 flex-shrink-0">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Migration list -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

        <!-- Loading skeleton -->
        <div x-show="loading && !status" class="divide-y divide-gray-100 dark:divide-gray-700">
            <template x-for="i in 3" :key="i">
                <div class="p-4 animate-pulse flex items-center gap-4">
                    <div class="h-8 w-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="h-3 w-32 bg-gray-100 dark:bg-gray-700 rounded"></div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty state -->
        <div x-show="status && status.migrations.length === 0" class="p-10 text-center">
            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7c0-2 1-3 3-3h10c2 0 3 1 3 3M4 7h16"/>
            </svg>
            <p class="mt-3 text-gray-500 dark:text-gray-400">No migration files found in
                <code class="text-xs">database/migrations/</code></p>
        </div>

        <!-- Migration rows -->
        <div x-show="status && status.migrations.length > 0"
             class="divide-y divide-gray-100 dark:divide-gray-700">
            <template x-for="m in (status ? status.migrations : [])" :key="m.version">
                <div class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">

                    <!-- Version badge -->
                    <div class="flex-shrink-0 w-12 text-center">
                        <span class="font-mono text-sm font-bold"
                              :class="{
                                'text-green-600 dark:text-green-400':  m.status === 'applied',
                                'text-yellow-600 dark:text-yellow-400': m.status === 'pending',
                                'text-red-600 dark:text-red-400':      m.status === 'orphan'
                              }"
                              x-text="'v' + String(m.version).padStart(3, '0')"></span>
                    </div>

                    <!-- Description & meta -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-medium text-gray-900 dark:text-white truncate"
                                  x-text="m.description"></span>

                            <!-- Applied chip -->
                            <span x-show="m.status === 'applied'"
                                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Applied
                            </span>

                            <!-- Pending chip -->
                            <span x-show="m.status === 'pending'"
                                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-gray-700 dark:text-yellow-400 dark:border dark:border-yellow-500">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                </svg>
                                Pending
                            </span>

                            <!-- Orphan chip -->
                            <span x-show="m.status === 'orphan'"
                                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                &#9888; Orphan (file missing)
                            </span>
                        </div>

                        <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            <span x-text="'app v' + m.app_version"></span>
                            <span x-show="m.filename" x-text="m.filename" class="font-mono"></span>
                            <span x-show="m.applied_at"
                                  x-text="'Applied: ' + (m.applied_at ? new Date(m.applied_at).toLocaleString() : '')"></span>
                            <span x-show="m.execution_ms != null" x-text="m.execution_ms + 'ms'"></span>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex-shrink-0 flex items-center gap-2">

                        <!-- Apply button (only for the first/next pending) -->
                        <template x-if="m.status === 'pending' && isNextPending(m.version)">
                            <button @click="confirmAndRun('migrate_up', m)"
                                    :disabled="loading"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50 transition-colors">
                                <span aria-hidden="true">&uarr;</span> Apply
                            </button>
                        </template>

                        <!-- Rollback button (only for the most recent applied) -->
                        <template x-if="m.status === 'applied' && isLastApplied(m.version)">
                            <button @click="confirmAndRun('migrate_down', m)"
                                    :disabled="loading"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50 transition-colors">
                                <span aria-hidden="true">&darr;</span> Rollback
                            </button>
                        </template>

                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Confirm dialog -->
    <div x-show="confirmTarget" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
         @keydown.escape.window="confirmTarget = null">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full p-6 space-y-4"
             @click.stop>
            <div class="flex items-start gap-3">
                <!-- Apply icon -->
                <div x-show="confirmAction === 'migrate_up'"
                     class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                    </svg>
                </div>
                <!-- Rollback icon - normal -->
                <div x-show="confirmAction === 'migrate_down' && !(confirmTarget && confirmTarget.rollback_risk === 'destructive')"
                     class="flex-shrink-0 h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                    <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <!-- Rollback icon - destructive (skull) -->
                <div x-show="confirmAction === 'migrate_down' && confirmTarget && confirmTarget.rollback_risk === 'destructive'"
                     class="flex-shrink-0 h-10 w-10 rounded-full bg-red-200 dark:bg-red-800 flex items-center justify-center">
                    <svg class="h-5 w-5 text-red-700 dark:text-red-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold"
                        :class="confirmAction === 'migrate_down' && confirmTarget && confirmTarget.rollback_risk === 'destructive'
                            ? 'text-red-700 dark:text-red-300'
                            : 'text-gray-900 dark:text-white'"
                        x-text="confirmAction === 'migrate_up' ? 'Apply Migration' : 'Roll Back Migration'"></h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        <span x-show="confirmAction === 'migrate_up'">This will execute the <strong>UP</strong> section of </span>
                        <span x-show="confirmAction === 'migrate_down'">This will execute the <strong>DOWN</strong> section of </span>
                        <strong x-text="confirmTarget ? 'v' + String(confirmTarget.version).padStart(3,'0') + ' \u2014 ' + confirmTarget.description : ''"></strong>.
                    </p>

                    <!-- Standard rollback warning -->
                    <p x-show="confirmAction === 'migrate_down' && !(confirmTarget && confirmTarget.rollback_risk === 'destructive')"
                       class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">
                        &#9888; Rollbacks may drop tables and permanently delete data.
                    </p>

                    <!-- Destructive rollback warning -->
                    <div x-show="confirmAction === 'migrate_down' && confirmTarget && confirmTarget.rollback_risk === 'destructive'"
                         class="mt-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 p-3 space-y-2">
                        <p class="text-sm font-bold text-red-700 dark:text-red-300">
                            &#128721; DESTRUCTIVE ROLLBACK
                        </p>
                        <p class="text-sm text-red-600 dark:text-red-400"
                           x-text="confirmTarget && confirmTarget.rollback_note ? confirmTarget.rollback_note : 'This rollback will permanently destroy data.'"></p>
                        <p class="text-xs font-semibold text-red-700 dark:text-red-300">
                            There is no undo. This data cannot be recovered.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button @click="confirmTarget = null"
                        class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Cancel
                </button>
                <button @click="runAction()"
                        :disabled="loading"
                        :class="confirmAction === 'migrate_up'
                            ? 'bg-blue-600 hover:bg-blue-700 text-white'
                            : 'bg-red-600 hover:bg-red-700 text-white'"
                        class="px-4 py-2 text-sm font-medium rounded-lg disabled:opacity-50 transition-colors">
                    <span x-show="!loading"
                          x-text="confirmAction === 'migrate_up' ? 'Apply Now' :
                                  (confirmTarget && confirmTarget.rollback_risk === 'destructive' ? 'I understand, Roll Back Anyway' : 'Roll Back')"></span>
                    <span x-show="loading" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Running&hellip;
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- How it works -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-3">How Migrations Work</h2>
        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <li class="flex items-start gap-2">
                <span class="text-blue-500 font-bold mt-0.5">&#8594;</span>
                Migration files live in <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">database/migrations/</code>
                and are named <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">v000_description.sql</code>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-blue-500 font-bold mt-0.5">&#8594;</span>
                Each file has an <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">-- ==UP==</code>
                section (apply) and a <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">-- ==DOWN==</code> section (rollback).
            </li>
            <li class="flex items-start gap-2">
                <span class="text-blue-500 font-bold mt-0.5">&#8594;</span>
                Migrations apply in version order. You can only apply the <em>next</em> pending migration
                or rollback the <em>most recent</em> applied migration.
            </li>
            <li class="flex items-start gap-2">
                <span class="text-blue-500 font-bold mt-0.5">&#8594;</span>
                Applied migrations are recorded in <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">schema_migrations</code>
                with timestamps and execution times.
            </li>
            <li class="flex items-start gap-2">
                <span class="text-yellow-500 font-bold mt-0.5">&#9888;</span>
                The DB user in <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">config.php</code>
                needs <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">CREATE TABLE</code>,
                <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">DROP TABLE</code>, and
                <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">ALTER TABLE</code> privileges.
            </li>
        </ul>
    </div>

</div><!-- /x-data -->

<script>
function migrationManager() {
    return {
        loading:       false,
        status:        null,
        actionLog:     null,
        confirmAction: null,
        confirmTarget: null,

        async init() {
            // Restore success log that survived a post-action page reload
            var saved = sessionStorage.getItem('migrations_action_log');
            if (saved) {
                this.actionLog = JSON.parse(saved);
                sessionStorage.removeItem('migrations_action_log');
            }
            await this.fetchStatus();
        },

        async fetchStatus() {
            this.loading = true;
            try {
                var r = await fetch('../api/migrations_api.php');
                this.status = await r.json();
            } catch (e) {
                this.actionLog = { success: false, message: 'Could not reach migrations API: ' + e.message };
            } finally {
                this.loading = false;
            }
        },

        confirmAndRun(action, migration) {
            this.confirmAction = action;
            this.confirmTarget = migration;
        },

        async dumpSchema() {
            this.actionLog = null;
            this.loading   = true;
            try {
                var r = await fetch('../api/migrations_api.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ action: 'dump_schema' })
                });
                this.actionLog = await r.json();
            } catch (e) {
                this.actionLog = { success: false, message: 'Dump failed: ' + e.message };
            }
            this.loading = false;
        },

        async runAction() {
            if (!this.confirmAction || !this.confirmTarget) return;

            var action = this.confirmAction;
            this.confirmTarget = null;
            this.confirmAction = null;
            this.actionLog     = null;
            this.loading       = true;

            try {
                var r = await fetch('../api/migrations_api.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ action: action })
                });
                var result = await r.json();

                if (result.success) {
                    // Stash the log so it survives the reload, then reload
                    // so the PHP header banner recalculates from the DB.
                    sessionStorage.setItem('migrations_action_log', JSON.stringify(result));
                    window.location.reload();
                    return;
                }

                this.actionLog = result;
            } catch (e) {
                this.actionLog = { success: false, message: 'Request failed: ' + e.message };
            }

            await this.fetchStatus();
            this.loading = false;
        },

        isNextPending(version) {
            if (!this.status) return false;
            var pending = this.status.migrations.filter(function(m) { return m.status === 'pending'; });
            if (!pending.length) return false;
            return Math.min.apply(null, pending.map(function(m) { return m.version; })) === version;
        },

        isLastApplied(version) {
            if (!this.status) return false;
            var applied = this.status.migrations.filter(function(m) { return m.status === 'applied'; });
            if (!applied.length) return false;
            return Math.max.apply(null, applied.map(function(m) { return m.version; })) === version;
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
