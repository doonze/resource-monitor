# Apache SSL Setup - Quick Reference

## 🎯 Choose Your Setup

### Scenario 1: Single Apache Server (Direct)
✅ Use `resource-monitor-ssl.conf`

```bash
sudo cp apache/resource-monitor-ssl.conf /etc/apache2/sites-available/
sudo nano /etc/apache2/sites-available/resource-monitor-ssl.conf
# Update: ServerName, SSL certificate paths
sudo a2ensite resource-monitor-ssl
sudo systemctl reload apache2
```

### Scenario 2: Apache Proxying to Backend (Reverse Proxy)
✅ Use `resource-monitor-reverse-proxy.conf`

```bash
sudo a2enmod proxy proxy_http
sudo cp apache/resource-monitor-reverse-proxy.conf /etc/apache2/sites-available/
sudo nano /etc/apache2/sites-available/resource-monitor-reverse-proxy.conf
# Update: ServerName, SSL paths, Define BACKEND
sudo a2ensite resource-monitor-reverse-proxy
sudo systemctl reload apache2
```

---

## 🔒 SSL Certificate Options

### Let's Encrypt (Recommended)
```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d monitor.yourdomain.com
```

### Self-Signed (Testing)
```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/monitor.key \
  -out /etc/ssl/certs/monitor.crt
```

### Corporate CA
```bash
sudo cp your-cert.crt /etc/ssl/certs/monitor.crt
sudo cp your-key.key /etc/ssl/private/monitor.key
sudo chmod 600 /etc/ssl/private/monitor.key
```

---

## 🚨 Troubleshooting

| Error | Solution |
|-------|----------|
| Certificate not found | Check paths in config, verify files exist |
| 403 Forbidden | `sudo chown -R www-data:www-data /var/www/html/resource-monitor` |
| 502 Bad Gateway | Backend not running or wrong BACKEND URL |
| Proxy error | `sudo a2enmod proxy proxy_http && sudo systemctl restart apache2` |

---

## ✅ Verification

```bash
# Test config
sudo apache2ctl configtest

# Test SSL
curl -I https://monitor.yourdomain.com

# Check certificate
openssl s_client -connect monitor.yourdomain.com:443

# View logs
sudo tail -f /var/log/apache2/resource-monitor-*-error.log
```

---

**Full documentation**: See `apache/README.md`
