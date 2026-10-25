# Apache SSL Virtual Host Setup Guide

This directory contains Apache virtual host configurations for hosting the Resource Monitor behind SSL (HTTPS).

## 📁 Available Configurations

### 1. **Direct Hosting** (`resource-monitor-ssl.conf`)
Use when Apache directly serves the PHP application.

**Use Cases**:
- Single Apache instance
- No separate backend server
- Simple deployment

### 2. **Reverse Proxy** (`resource-monitor-reverse-proxy.conf`)
Use when proxying to a backend server.

**Use Cases**:
- Apache front-end proxying to Apache/Nginx backend
- Load balancing
- Separating SSL termination from application

---

## 🚀 Quick Setup

### Prerequisites

```bash
# Enable required Apache modules
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod rewrite
sudo a2enmod deflate
sudo a2enmod expires
sudo a2enmod proxy           # For reverse proxy only
sudo a2enmod proxy_http      # For reverse proxy only
sudo a2enmod http2           # Optional but recommended

# Restart Apache
sudo systemctl restart apache2
```

---

## 🔒 Option 1: Direct Hosting Setup

### Step 1: Copy Configuration

```bash
sudo cp apache/resource-monitor-ssl.conf /etc/apache2/sites-available/
```

### Step 2: Edit Configuration

```bash
sudo nano /etc/apache2/sites-available/resource-monitor-ssl.conf
```

**Update these lines**:
```apache
ServerName monitor.yourdomain.com
ServerAdmin admin@yourdomain.com

SSLCertificateFile /etc/ssl/certs/monitor.yourdomain.com.crt
SSLCertificateKeyFile /etc/ssl/private/monitor.yourdomain.com.key
SSLCertificateChainFile /etc/ssl/certs/ca-bundle.crt
```

### Step 3: Install SSL Certificate

See [SSL Certificate Setup](#ssl-certificate-setup) below.

### Step 4: Enable Site

```bash
sudo a2ensite resource-monitor-ssl
sudo systemctl reload apache2
```

### Step 5: Test

```bash
# Check configuration
sudo apache2ctl configtest

# View logs
sudo tail -f /var/log/apache2/resource-monitor-ssl-error.log

# Open browser
# https://monitor.yourdomain.com
```

---

## 🔄 Option 2: Reverse Proxy Setup

### Step 1: Copy Configuration

```bash
sudo cp apache/resource-monitor-reverse-proxy.conf /etc/apache2/sites-available/
```

### Step 2: Edit Configuration

```bash
sudo nano /etc/apache2/sites-available/resource-monitor-reverse-proxy.conf
```

**Update these lines**:
```apache
ServerName monitor.yourdomain.com
ServerAdmin admin@yourdomain.com

# SSL Certificate paths
SSLCertificateFile /etc/ssl/certs/monitor.yourdomain.com.crt
SSLCertificateKeyFile /etc/ssl/private/monitor.yourdomain.com.key
SSLCertificateChainFile /etc/ssl/certs/ca-bundle.crt

# Backend server (IMPORTANT!)
Define BACKEND http://localhost:8080
# Or:
# Define BACKEND http://backend-server.internal:80
```

### Step 3: Configure Backend Server

If backend is on same server but different port:

```apache
# /etc/apache2/sites-available/resource-monitor-backend.conf
<VirtualHost *:8080>
    ServerName localhost
    
    DocumentRoot /var/www/html/resource-monitor/public
    
    <Directory /var/www/html/resource-monitor/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/resource-monitor-backend-error.log
    CustomLog ${APACHE_LOG_DIR}/resource-monitor-backend-access.log combined
</VirtualHost>
```

Enable backend:
```bash
sudo a2ensite resource-monitor-backend
```

### Step 4: Install SSL Certificate

See [SSL Certificate Setup](#ssl-certificate-setup) below.

### Step 5: Enable Proxy Site

```bash
sudo a2ensite resource-monitor-reverse-proxy
sudo systemctl reload apache2
```

### Step 6: Test

```bash
# Check configuration
sudo apache2ctl configtest

# Test backend directly
curl http://localhost:8080/

# Test SSL proxy
curl https://monitor.yourdomain.com/

# View logs
sudo tail -f /var/log/apache2/resource-monitor-proxy-error.log

# Open browser
# https://monitor.yourdomain.com
```

---

## 🔒 SSL Certificate Setup

### Option A: Let's Encrypt (Free, Recommended)

**Install Certbot**:
```bash
sudo apt update
sudo apt install certbot python3-certbot-apache
```

**Generate Certificate**:
```bash
# Automatic (certbot handles Apache config)
sudo certbot --apache -d monitor.yourdomain.com

# Or manual (you configure Apache)
sudo certbot certonly --webroot -w /var/www/html -d monitor.yourdomain.com
```

**Update Virtual Host**:
```apache
SSLCertificateFile /etc/letsencrypt/live/monitor.yourdomain.com/fullchain.pem
SSLCertificateKeyFile /etc/letsencrypt/live/monitor.yourdomain.com/privkey.pem
# No SSLCertificateChainFile needed (fullchain includes it)
```

**Auto-Renewal**:
```bash
# Test renewal
sudo certbot renew --dry-run

# Cron job (already added by certbot)
sudo systemctl status certbot.timer
```

### Option B: Self-Signed Certificate (Testing Only)

**Generate Certificate**:
```bash
sudo mkdir -p /etc/ssl/private
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/monitor.yourdomain.com.key \
    -out /etc/ssl/certs/monitor.yourdomain.com.crt \
    -subj "/C=US/ST=Arkansas/L=Bentonville/O=Walmart/CN=monitor.yourdomain.com"

sudo chmod 600 /etc/ssl/private/monitor.yourdomain.com.key
```

**Update Virtual Host**:
```apache
SSLCertificateFile /etc/ssl/certs/monitor.yourdomain.com.crt
SSLCertificateKeyFile /etc/ssl/private/monitor.yourdomain.com.key
# Comment out SSLCertificateChainFile
```

⚠️ **Warning**: Browsers will show security warning for self-signed certificates!

### Option C: Corporate/Internal CA

If your organization has an internal CA:

```bash
# Copy certificate and key
sudo cp your-cert.crt /etc/ssl/certs/monitor.yourdomain.com.crt
sudo cp your-key.key /etc/ssl/private/monitor.yourdomain.com.key
sudo cp ca-bundle.crt /etc/ssl/certs/ca-bundle.crt

# Set permissions
sudo chmod 644 /etc/ssl/certs/monitor.yourdomain.com.crt
sudo chmod 600 /etc/ssl/private/monitor.yourdomain.com.key
sudo chmod 644 /etc/ssl/certs/ca-bundle.crt
```

---

## 🔧 Configuration Options

### Walmart Specific Settings

If deploying at Walmart, update CSP to allow internal CDNs:

```apache
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://unpkg.com https://cdn.jsdelivr.net https://internal-cdn.walmart.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self';"
```

### Custom Backend URL

For reverse proxy, edit the `Define BACKEND` line:

```apache
# Local backend on different port
Define BACKEND http://localhost:8080

# Different server (internal network)
Define BACKEND http://backend.internal.walmart.com

# Different server with port
Define BACKEND http://10.0.1.50:8080

# Using IP address
Define BACKEND http://192.168.1.100

# Unix socket (advanced)
Define BACKEND unix:/var/run/resource-monitor.sock|http://localhost
```

### Subdirectory Installation

If installing in a subdirectory instead of root:

**Direct Hosting**:
```apache
Alias /monitor /var/www/html/resource-monitor/public

<Directory /var/www/html/resource-monitor/public>
    # ... same settings ...
</Directory>
```

**Reverse Proxy**:
```apache
ProxyPass /monitor ${BACKEND}/
ProxyPassReverse /monitor ${BACKEND}/
```

Access at: `https://yourdomain.com/monitor/`

### IP Restriction (Internal Only)

Restrict access to internal network:

```apache
<Directory /var/www/html/resource-monitor/public>
    Require ip 10.0.0.0/8
    Require ip 172.16.0.0/12
    Require ip 192.168.0.0/16
</Directory>
```

Or specific IPs:
```apache
Require ip 10.1.2.3
Require ip 10.1.2.4
```

### Basic Authentication (Optional)

Add password protection:

```bash
# Create password file
sudo htpasswd -c /etc/apache2/.htpasswd admin
sudo htpasswd /etc/apache2/.htpasswd user2
```

Add to virtual host:
```apache
<Directory /var/www/html/resource-monitor/public>
    AuthType Basic
    AuthName "Resource Monitor"
    AuthUserFile /etc/apache2/.htpasswd
    Require valid-user
</Directory>
```

---

## 📊 Monitoring & Troubleshooting

### Check Apache Status

```bash
# Service status
sudo systemctl status apache2

# Test configuration
sudo apache2ctl configtest

# List enabled sites
apache2ctl -S

# Check loaded modules
apache2ctl -M | grep -E 'ssl|proxy|rewrite'
```

### View Logs

```bash
# Error logs
sudo tail -f /var/log/apache2/resource-monitor-ssl-error.log
sudo tail -f /var/log/apache2/resource-monitor-proxy-error.log

# Access logs
sudo tail -f /var/log/apache2/resource-monitor-ssl-access.log

# PHP errors
sudo tail -f /var/log/apache2/resource-monitor-php-error.log
```

### Common Issues

**1. "SSLCertificateFile not found"**
```bash
# Check certificate exists
ls -la /etc/ssl/certs/monitor.yourdomain.com.crt
ls -la /etc/ssl/private/monitor.yourdomain.com.key

# Check permissions
sudo chmod 644 /etc/ssl/certs/monitor.yourdomain.com.crt
sudo chmod 600 /etc/ssl/private/monitor.yourdomain.com.key
```

**2. "Proxy Error"**
```bash
# Check backend is running
curl http://localhost:8080/

# Check proxy modules loaded
apache2ctl -M | grep proxy

# Enable proxy modules
sudo a2enmod proxy proxy_http
sudo systemctl restart apache2
```

**3. "403 Forbidden"**
```bash
# Check file permissions
sudo chown -R www-data:www-data /var/www/html/resource-monitor
sudo chmod -R 755 /var/www/html/resource-monitor

# Check SELinux (if enabled)
sudo setenforce 0  # Temporary
# Or configure SELinux policies
```

**4. "502 Bad Gateway" (Reverse Proxy)**
```bash
# Backend not running
sudo systemctl status apache2  # Check backend service

# Wrong backend URL
# Edit Define BACKEND in config

# Firewall blocking
sudo ufw allow from 127.0.0.1 to any port 8080
```

**5. "SSL Certificate Error in Browser"**
```bash
# Check certificate validity
openssl s_client -connect monitor.yourdomain.com:443 -servername monitor.yourdomain.com

# Check certificate dates
openssl x509 -in /etc/ssl/certs/monitor.yourdomain.com.crt -noout -dates

# Verify certificate chain
openssl verify -CAfile /etc/ssl/certs/ca-bundle.crt /etc/ssl/certs/monitor.yourdomain.com.crt
```

---

## 🔐 Security Checklist

- [ ] SSL certificate installed and valid
- [ ] HTTPS redirect enabled (HTTP → HTTPS)
- [ ] HSTS header enabled
- [ ] Security headers configured (X-Frame-Options, CSP, etc.)
- [ ] TLS 1.2+ only (TLS 1.0/1.1 disabled)
- [ ] Strong cipher suites configured
- [ ] File permissions correct (www-data owner, 755/644)
- [ ] Sensitive directories protected (.git, config.php)
- [ ] PHP display_errors disabled in production
- [ ] Logs rotated (logrotate configured)
- [ ] Firewall configured (only 80/443 open)
- [ ] IP restriction (if internal-only)
- [ ] Authentication enabled (if needed)

---

## 🚀 Performance Tuning

### Enable HTTP/2

Already included in configs! Requires:
```bash
sudo a2enmod http2
sudo systemctl restart apache2
```

### Enable Compression

Already included via `mod_deflate`. Verify:
```bash
# Check module loaded
apache2ctl -M | grep deflate

# Test compression
curl -I -H "Accept-Encoding: gzip" https://monitor.yourdomain.com/
# Should see: Content-Encoding: gzip
```

### Enable Caching

Browser caching already configured. For server-side caching:

```apache
# Add to virtual host
<IfModule mod_cache.c>
    CacheEnable disk /
    CacheRoot /var/cache/apache2/mod_cache_disk
    CacheDefaultExpire 3600
    CacheDirLevels 2
    CacheDirLength 1
</IfModule>
```

Enable module:
```bash
sudo a2enmod cache cache_disk
sudo mkdir -p /var/cache/apache2/mod_cache_disk
sudo chown www-data:www-data /var/cache/apache2/mod_cache_disk
```

### Connection Limits

For reverse proxy, adjust connection pool:

```apache
ProxySet connectiontimeout=5 timeout=300 ttl=120 max=100
```

- `connectiontimeout`: Time to establish connection (seconds)
- `timeout`: Total request timeout (seconds)
- `ttl`: Connection keep-alive time (seconds)
- `max`: Max connections to backend

---

## 📚 Additional Resources

- **Apache SSL/TLS**: https://httpd.apache.org/docs/2.4/ssl/
- **Let's Encrypt**: https://letsencrypt.org/
- **SSL Labs Test**: https://www.ssllabs.com/ssltest/
- **Mozilla SSL Config**: https://ssl-config.mozilla.org/
- **Apache Proxy**: https://httpd.apache.org/docs/2.4/mod/mod_proxy.html

---

## 🎯 Quick Commands

```bash
# Copy config
sudo cp apache/resource-monitor-ssl.conf /etc/apache2/sites-available/

# Edit config
sudo nano /etc/apache2/sites-available/resource-monitor-ssl.conf

# Test config
sudo apache2ctl configtest

# Enable site
sudo a2ensite resource-monitor-ssl

# Reload Apache
sudo systemctl reload apache2

# View logs
sudo tail -f /var/log/apache2/resource-monitor-ssl-error.log

# Test SSL
openssl s_client -connect monitor.yourdomain.com:443

# Test in browser
# https://monitor.yourdomain.com
```

---

**Need help?** Check logs first, then see Troubleshooting section above! 🐶
