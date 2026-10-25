# Resource Monitor

Workspace project for the PHP monitoring dashboard and Python 3 collector, including the planned Tesla P4 telemetry work.

## Production Locations

- Web app: `/var/www/html/resource-monitor/`
- Collector: `/opt/resource-monitor/collector/`
- Collector service: `resource-monitor.service`
- MariaDB database: `resource_monitor`

The workspace `collector/` path is a symlink to the live `/opt/resource-monitor/collector/`; do not edit it as a development copy. GPU collector/aggregator edits are live, migration v005 is applied, and the collector is producing GPU samples. The dedicated GPU page/API and header link are deployed and smoke-tested; the additional GPU charts in the workspace History page remain undeployed.

## GPU Telemetry Status

The collector records Tesla P4 utilization, memory, temperature, and power into the v005 GPU tables. The deployed GPU page auto-refreshes live readings every 10 seconds and history charts every minute; charts cover utilization, VRAM, temperature, and power draw. Temperatures display in Fahrenheit (stored in Celsius), and there is no manual refresh button. History charts support x-axis drag-to-zoom with reset controls and a full-viewport view; refreshes update existing chart instances. The History and Process History pages now use the same controls; the 2026-10-25 deployment also added four GPU history charts to History. The year-scale chart fills after hourly GPU rollups accumulate.

See `PROJECT_CONTEXT.md` for paths, constraints, and known operational details.