# Resource Monitor - Web Interface

Modern, responsive web interface for the Resource Monitor system.

## Features

### ✅ Implemented
- **Dashboard** - Real-time system metrics with live updates
- **Settings** - Manage alert thresholds through web UI
- **Migrations** - View database migration status
- **Dark/Light Theme** - User-selectable themes with persistence
- **Status Indicators** - Green/yellow/red badges for quick issue identification
- **Responsive Design** - Works on desktop, tablet, and mobile

### 🚧 Coming Soon
- **Historical Graphs** - Chart.js-based visualizations
- **Process Explorer** - Search and filter processes
- **Alert History** - View triggered alerts
- **User Authentication** - Login system (optional)

## Tech Stack

- **Backend**: PHP 8.0+
- **Frontend**: HTMX, Alpine.js, Tailwind CSS
- **Charts**: Chart.js
- **Database**: MariaDB 10.5+ via PDO

## Installation

### 1. Copy Files

```bash
# Copy to Apache document root
sudo cp -r web/ /var/www/html/resource-monitor/
sudo chown -R www-data:www-data /var/www/html/resource-monitor
```

### 2. Configure Database

```bash
cd /var/www/html/resource-monitor
cp config.php.example config.php
nano config.php
```

Update these settings:
```php
define('DB_USER', 'resmon_web');
define('DB_PASS', 'YourWebPassword456!');
define('TIMEZONE', 'America/Chicago');
```

### 3. Configure Apache

Create virtual host `/etc/apache2/sites-available/resource-monitor.conf`:

```apache
<VirtualHost *:80>
    ServerName monitor.yourdomain.com
    DocumentRoot /var/www/html/resource-monitor/public
    
    <Directory /var/www/html/resource-monitor/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Enable PHP
        <IfModule mod_php.c>
            php_flag display_errors Off
            php_flag log_errors On
        </IfModule>
    </Directory>
    
    # Deny access to sensitive directories
    <DirectoryMatch "/(includes|api)">
        Require all denied
    </DirectoryMatch>
    
    # Allow API access
    <Directory /var/www/html/resource-monitor/api>
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/resource-monitor-error.log
    CustomLog ${APACHE_LOG_DIR}/resource-monitor-access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite resource-monitor
sudo systemctl reload apache2
```

### 4. Test

Open browser: `http://monitor.yourdomain.com` or `http://your-server-ip/resource-monitor`

## Directory Structure

```
web/
├── config.php.example      # Configuration template
├── .htaccess               # Apache security rules
│
├── includes/               # Shared PHP includes
│   ├── db.php             # Database connection and utilities
│   ├── header.php         # Header with navigation
│   └── footer.php         # Footer
│
├── api/                   # JSON API endpoints
│   ├── live.php           # Live system metrics
│   ├── processes.php      # Process data
│   └── settings.php       # Settings CRUD
│
└── public/                # Web UI pages
    ├── index.php          # Dashboard (main page)
    ├── history.php        # Historical graphs
    ├── processes.php      # Process explorer
    ├── settings.php       # Settings management
    └── migrations.php     # Migration status
```

## API Endpoints

### GET /api/live.php
Returns current system metrics in JSON format.

**Response:**
```json
{
  "timestamp": "2026-04-27 15:30:00",
  "system": {
    "cpu": { "percent": 45.2, "cores": [...], "load": {...} },
    "memory": { "total": 16777216000, "used": 8388608000, ... },
    "network": { "bytes_sent": 1024, "bytes_recv": 2048 },
    ...
  },
  "temperatures": [...],
  "disks": [...],
  "collector": { "status": "running", ... }
}
```

### GET /api/processes.php?sort=cpu&limit=10
Returns top processes.

**Parameters:**
- `sort`: `cpu` or `memory` (default: `cpu`)
- `limit`: Number of results, max 50 (default: 10)

### GET/POST/PUT/DELETE /api/settings.php
Manage alert thresholds.

**GET** - List all thresholds
**POST** - Create new threshold
**PUT** - Update threshold
**DELETE** - Delete threshold

## Customization

### Thresholds

Default warning/critical thresholds can be adjusted in **Settings** page:

- CPU: Warning at 70%, Critical at 85%
- Memory: Warning at 70%, Critical at 85%
- Disk: Warning at 80%, Critical at 90%

### Update Intervals

Edit `config.php`:
```php
define('LIVE_UPDATE_INTERVAL', 5000);   // Dashboard updates (ms)
define('CHART_UPDATE_INTERVAL', 30000); // Chart updates (ms)
```

### Theme Colors

The interface uses Walmart brand colors:
- Primary Blue: `#0071ce`
- Accent Yellow: `#ffc220`

Status colors:
- Green: Success/Normal
- Yellow: Warning
- Red: Critical/Error

## Security

### Implemented
- ✅ PDO prepared statements (SQL injection prevention)
- ✅ CSRF protection ready (tokens in forms)
- ✅ XSS protection (`htmlspecialchars` on all output)
- ✅ Security headers in `.htaccess`
- ✅ Config file protection
- ✅ API input validation

### Recommended
- [ ] Enable HTTPS (Let's Encrypt)
- [ ] Add authentication (set `ENABLE_AUTH = true`)
- [ ] Restrict by IP if internal-only
- [ ] Regular security updates

## Troubleshooting

### "Database connection failed"
Check `config.php` settings and verify database user exists:
```bash
mysql -u resmon_web -p resource_monitor
```

### "No data available"
Ensure collector is running:
```bash
sudo systemctl status resource-monitor
```

### Theme not persisting
Check browser accepts cookies. Theme stored in `theme` cookie.

### API returns errors
Check PHP error log:
```bash
sudo tail -f /var/log/apache2/resource-monitor-error.log
```

Enable debug mode in `config.php`:
```php
define('DEBUG_MODE', true);
```

## Performance

### Caching

For high-traffic deployments, consider:
- OpCache for PHP
- Redis/Memcached for session storage
- CDN for static assets

### Database

API queries use database views for performance:
- `v_latest_metrics` - Latest system snapshot
- `v_top_cpu_processes` - Top CPU processes
- `v_top_mem_processes` - Top memory processes

## Development

### Adding New Pages

1. Create PHP file in `public/`
2. Include header: `require_once __DIR__ . '/../includes/header.php';`
3. Set page title: `$pageTitle = 'My Page';`
4. Add navigation link in `includes/header.php`
5. Include footer: `require_once __DIR__ . '/../includes/footer.php';`

### Adding API Endpoints

1. Create PHP file in `api/`
2. Include db.php: `require_once __DIR__ . '/../includes/db.php';`
3. Use `jsonResponse()` for output
4. Use `jsonError()` for errors
5. Validate all inputs!

### Styling

Uses Tailwind CSS via CDN. Common patterns:

```html
<!-- Card -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
    ...
</div>

<!-- Button -->
<button class="px-4 py-2 bg-walmart-blue hover:bg-blue-700 text-white rounded-md">
    Click Me
</button>

<!-- Status Badge -->
<span class="px-2 py-1 bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100 rounded-full text-xs">
    Active
</span>
```

## License

MIT - Same as parent project

## Support

See main project documentation:
- `README.md` - Project overview
- `INSTALL.md` - Installation guide
- `MIGRATION_GUIDE.md` - Database migrations
