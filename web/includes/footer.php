    </main>
    
    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400">
                <div>
                    <?php echo e(APP_NAME); ?> v<?php echo e(APP_VERSION); ?>
                </div>
                <div>
                    Last updated: <span id="last-update"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </footer>
    
    <script>
        // Update last update time
        function updateLastUpdateTime() {
            const now = new Date();
            const formatted = now.toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            const elem = document.getElementById('last-update');
            if (elem) elem.textContent = formatted;
        }
        
        // Update every second
        setInterval(updateLastUpdateTime, 1000);
    </script>
</body>
</html>
