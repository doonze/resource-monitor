# Project Context: resource-monitor

**Last Updated:** 2026-10-25

## Systems

### Development System
- **Type:** Linux workspace checkout
- **OS:** Debian Linux
- **Path:** `/home/justin/puppy_workspace/projects/active/resource-monitor`

### Production System
- **Server:** Same Debian server used for the workspace
- **OS:** Debian Linux
- **Access:** Local execution

## Important Paths

### Production Paths
- **Web application:** `/var/www/html/resource-monitor/`
- **Collector code:** `/opt/resource-monitor/collector/`
- **Collector service:** `resource-monitor.service`
- **Web config:** `/var/www/html/resource-monitor/web/config.php` (runtime config; do not commit secrets)
- **Collector config:** `/opt/resource-monitor/collector/config.yaml` (runtime config; do not commit secrets)
- **Database:** MariaDB database `resource_monitor`; no credentials in this file
- **Logs:** `journalctl -u resource-monitor.service`; application-specific log paths not yet documented

### Development Paths
- **Workspace source:** `/home/justin/puppy_workspace/projects/active/resource-monitor`
- **Test data:** Not yet documented

##  Tech Stack
- **Language:** PHP web application; Python 3 collector
- **Framework:** Not yet documented
- **Database:** MariaDB (`resource_monitor`)
- **Web Server:** Apache
- **Other:** systemd collector service

##  Conventions
- **Branch naming:** Not yet documented
- **Commit style:** Not yet documented
- **Code style:** Follow existing project files after they are reviewed and imported
- **Testing:** Not yet documented

##  Things to Always Remember
- Never commit deployed runtime configuration or credentials.
- Process-history `sample_count` must accommodate the 5-minute aggregate sum.
- `AlertChecker` uses a separate DB connection from the collector thread.
- Keep database migrations and application code distinct from runtime data/dumps.
- Workspace `collector/` is a symlink to `/opt/resource-monitor/collector/`; never use it for development edits until replaced by an independent copy.
- GPU collector/aggregator edits are on the production disk and loaded by the running service after Justin-approved restart on 2026-10-18. Migration v005 is applied. Check service logs and stored samples after future restarts.
- Verify telemetry and chart changes without disrupting the live collector or dashboard.

##  Project-Specific Notes
- The deployed PHP web app currently resides under `/var/www/html/resource-monitor/`.
- The deployed Python collector currently resides under `/opt/resource-monitor/collector/`.
- Tesla P4 verified after reboot with `nvidia-smi`: visible; startup sample was 0% utilization, 7 MiB / 7680 MiB, 37°C, and 6.88 W (2026-10-18 21:55).
- MariaDB `resource_monitor` migration v005 was applied at 2026-10-18 21:38; all four GPU tables exist. The collector restarted at 21:55, remained active with zero systemd restarts, and produced raw samples plus a 1-minute rollup by 21:57. No application errors appeared; the existing PyMySQL `ping(reconnect=True)` deprecation warning remains.
- GPU UI deployed 2026-10-18: `web/public/gpu.php`, `web/api/gpu_api.php`, GPU history in `web/api/history_api.php`, desktop/mobile navigation, and `version.json` schema requirement 5. Live cards refresh every 10 seconds; utilization, VRAM, Fahrenheit temperature, and power-draw charts refresh every minute with cache disabled. The VRAM chart scales to observed use and low/zero utilization samples get visible points. Temperature is converted from stored Celsius to Fahrenheit in both the live card and history chart. The manual Refresh button was removed. PHP lint and Chromium desktop/mobile smoke checks passed; 24-hour history returns non-null GPU points including power. Year-scale history remains empty until hourly GPU rollups accumulate. Interactive chart controls were deployed 2026-10-25: x-axis drag-to-zoom and reset controls via chartjs-plugin-zoom 2.2.0 (Hammer.js 2.0.8), plus a full-viewport view with Escape exit. Automatic history refresh updates existing Chart.js instances in place. PHP and JavaScript syntax checks passed; real-browser smoke testing is still pending.
- `/opt/resource-monitor/collector/config.yaml` was restored from Restic snapshot `e5bb3ae8`; do not expose its contents.
- Workspace web files remain a copied development source. The dedicated GPU page and APIs were deployed from selected files; extra GPU charts added to workspace `web/public/history.php` remain undeployed.
- The workspace `collector/` path is still a symlink to production. Never edit through it; replace it with an independent source copy before further collector development.
