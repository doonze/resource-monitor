<?php
require_once __DIR__ . '/db.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$theme = getTheme();
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme === 'dark' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? APP_NAME); ?> - <?php echo e(APP_NAME); ?></title>
    
    <!-- Tailwind CSS (Production Build) -->
    <link rel="stylesheet" href="assets/tailwind.css?v=<?php echo filemtime(__DIR__ . '/../public/assets/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/chart-controls.css?v=<?php echo filemtime(__DIR__ . '/../public/assets/chart-controls.css'); ?>">
    
    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    
    <!-- Alerts banner component (must be deferred before Alpine so it's defined first) -->
    <script defer src="assets/alerts-banner.js"></script>

    <!-- Alpine.js with Collapse plugin -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
    
    <!-- Chart.js + drag-zoom controls -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.2.0/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="assets/chart-controls.js?v=<?php echo filemtime(__DIR__ . '/../public/assets/chart-controls.js'); ?>"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors duration-200">
    
    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <!-- Logo -->
                    <div class="flex-shrink-0 flex items-center">
                        <svg class="h-8 w-8 text-walmart-blue" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                        </svg>
                        <span class="ml-2 text-xl font-semibold"><?php echo e(APP_NAME); ?></span>
                    </div>
                    
                    <!-- Navigation Links -->
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="index.php" 
                           class="<?php echo $currentPage === 'index' ? 'border-walmart-blue text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="history.php" 
                           class="<?php echo $currentPage === 'history' ? 'border-walmart-blue text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            History
                        </a>
                        <a href="gpu.php" 
                           class="<?php echo $currentPage === 'gpu' ? 'border-walmart-blue text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            GPU
                        </a>
                        <a href="processes.php" 
                           class="<?php echo $currentPage === 'processes' ? 'border-walmart-blue text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Processes
                        </a>
                        <a href="settings.php" 
                           class="<?php echo $currentPage === 'settings' ? 'border-walmart-blue text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Settings
                        </a>
                        <a href="migrations.php" 
                           class="<?php echo $currentPage === 'migrations' ? 'border-walmart-blue text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Migrations
                        </a>
                    </div>
                </div>
                
                <!-- Right side (Theme toggle, etc) -->
                <div class="flex items-center">
                    <!-- Theme Toggle -->
                    <button 
                        @click="toggleTheme()" 
                        x-data="{
                            theme: '<?php echo $theme; ?>',
                            toggleTheme() {
                                this.theme = this.theme === 'light' ? 'dark' : 'light';
                                document.documentElement.classList.toggle('dark');
                                document.cookie = 'theme=' + this.theme + '; path=/; max-age=31536000';
                            }
                        }"
                        class="p-2 rounded-md text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-walmart-blue">
                        <svg x-show="theme === 'light'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg x-show="theme === 'dark'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div class="sm:hidden" x-data="{ open: false }">
            <button @click="open = !open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            
            <div x-show="open" x-cloak class="pt-2 pb-3 space-y-1">
                <a href="index.php" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium <?php echo $currentPage === 'index' ? 'border-walmart-blue bg-blue-50 dark:bg-gray-700 text-walmart-blue' : 'border-transparent text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                    Dashboard
                </a>
                <a href="history.php" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium <?php echo $currentPage === 'history' ? 'border-walmart-blue bg-blue-50 dark:bg-gray-700 text-walmart-blue' : 'border-transparent text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                    History
                </a>
                <a href="gpu.php" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium <?php echo $currentPage === 'gpu' ? 'border-walmart-blue bg-blue-50 dark:bg-gray-700 text-walmart-blue' : 'border-transparent text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                    GPU
                </a>
                <a href="processes.php" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium <?php echo $currentPage === 'processes' ? 'border-walmart-blue bg-blue-50 dark:bg-gray-700 text-walmart-blue' : 'border-transparent text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                    Processes
                </a>
                <a href="settings.php" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium <?php echo $currentPage === 'settings' ? 'border-walmart-blue bg-blue-50 dark:bg-gray-700 text-walmart-blue' : 'border-transparent text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                    Settings
                </a>
                <a href="migrations.php" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium <?php echo $currentPage === 'migrations' ? 'border-walmart-blue bg-blue-50 dark:bg-gray-700 text-walmart-blue' : 'border-transparent text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                    Migrations
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Schema version mismatch banner -->
    <?php
    try {
        $__db = getDB();
        $__tableExists = $__db->query("SHOW TABLES LIKE 'schema_migrations'")->rowCount() > 0;
        if (!$__tableExists) {
            echo '<div class="bg-red-600 text-white text-sm text-center py-2 px-4">';
            echo '&#9888;&nbsp; Database not initialized &mdash; <a href="migrations.php" class="underline font-semibold">Run migrations</a> to create all tables.';
            echo '</div>';
        } else {
            $__row = $__db->query("SELECT COALESCE(MAX(version), -1) AS v FROM schema_migrations")->fetch();
            $__current = (int)$__row['v'];
            if ($__current < REQUIRED_SCHEMA_VERSION) {
                $__behind = REQUIRED_SCHEMA_VERSION - $__current;
                echo '<div class="bg-yellow-500 text-white text-sm text-center py-2 px-4">';
                echo '&#9888;&nbsp; Schema update required: DB is at v' . $__current . ', app requires v' . REQUIRED_SCHEMA_VERSION . ' &mdash; ';
                echo '<a href="migrations.php" class="underline font-semibold">' . $__behind . ' migration(s) pending</a>.';
                echo '</div>';
            }
        }
    } catch (Throwable $__e) {
        // Silently skip banner if DB is unreachable
    }
    ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
