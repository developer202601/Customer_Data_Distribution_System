# Hosting / Deployment Guide (Linux)

This guide is a copy-paste oriented checklist for deploying this Laravel 12 + PHP 8.2 app with:

- Nginx + PHP-FPM
- MySQL (recommended) + Laravel migrations / schema dump
- A persistent queue worker for the `exports` queue (systemd)
- Correct filesystem permissions for `storage/` artifacts (exports, exclusions, logs, temp)

It assumes you are deploying to a single Linux VM.

---

## 0) Variables (adjust to your server)

```bash
APP_DIR=/var/www/html/cdds-laravel
APP_USER=nginx
APP_GROUP=nginx
APP_PORT=8005
APP_URL=http://YOUR_SERVER_IP:${APP_PORT}
```

> Notes
> - This repo uses a PHP-FPM socket at `/run/php-fpm/cdds.sock` in the sample config below.
> - If your distro uses `www-data` instead of `nginx`, substitute accordingly.

---

## 1) System packages

### RHEL / Rocky / Alma (dnf)

```bash
sudo dnf install -y \
  git unzip zip curl \
  nginx \
  php php-cli php-fpm php-mbstring php-xml php-pdo php-mysqlnd php-gd php-intl php-opcache \
  python3
```

### Debian / Ubuntu (apt)

Package names vary by version; the rough equivalent is:

```bash
sudo apt update
sudo apt install -y \
  git unzip zip curl \
  nginx \
  php8.2-cli php8.2-fpm php8.2-mbstring php8.2-xml php8.2-mysql php8.2-gd php8.2-intl \
  python3
```

### Composer + Node

- Install Composer: https://getcomposer.org/download/
- Install Node.js LTS (for Vite build): https://nodejs.org/

---

## 2) Get the code

```bash
sudo mkdir -p "$(dirname "$APP_DIR")"
sudo chown -R "$USER":"$USER" "$(dirname "$APP_DIR")"

cd "$(dirname "$APP_DIR")"
# clone your repo here (example)
# git clone git@github.com:OWNER/REPO.git cdds-laravel

cd "$APP_DIR"
```

---

## 3) Install dependencies

```bash
cd "$APP_DIR"

composer install --no-dev --optimize-autoloader

npm ci
npm run build
```

---

## 4) Environment (.env)

```bash
cd "$APP_DIR"

cp .env.example .env
php artisan key:generate
```

Edit `.env` with your production values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=$APP_URL`
- DB settings (MySQL recommended)
- `QUEUE_CONNECTION=database` (if you want the systemd worker below)

---

## 5) Database

### Option A: Use migrations + schema dump (fast bootstrap)

The repo includes a schema dump at `database/schema/mysql-schema.sql`.

1) Create a database + user (example):

```bash
mysql -u root -p -e "CREATE DATABASE cdds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'cdds'@'localhost' IDENTIFIED BY 'CHANGE_ME';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON cdds.* TO 'cdds'@'localhost'; FLUSH PRIVILEGES;"
```

2) Point `.env` at it:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cdds
DB_USERNAME=cdds
DB_PASSWORD=CHANGE_ME
```

3) Apply schema + any newer migrations:

```bash
cd "$APP_DIR"
php artisan migrate --schema-path=database/schema/mysql-schema.sql
```

---

## 6) Storage permissions (critical for this app)

This app writes many artifacts under `storage/app/private`:

- `exports/<token>/...`
- `exclusions/<process_id>/...`
- temp extraction path: `storage/app/tmp/master/...`
- logs: `storage/logs/laravel.log`

### 6.1 Base ownership

```bash
cd "$APP_DIR"

sudo chown -R "$APP_USER":"$APP_GROUP" storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 6.2 Recommended: ACLs + setgid so uploads/jobs never break

This prevents the recurring “permission denied”, “manifest not found”, and temp-file read issues when:
- nginx/php-fpm writes files as `$APP_USER`
- queue workers run as `$APP_USER`
- shells/ops users need to inspect files

```bash
cd "$APP_DIR"

# Make sure these directories exist
sudo mkdir -p storage/app/tmp/master
sudo mkdir -p storage/app/private/exports
sudo mkdir -p storage/app/private/exclusions
sudo mkdir -p storage/logs

# Apply setgid so new files inherit group
sudo find storage/app/tmp storage/app/private storage/logs -type d -exec chmod 2775 {} +

# Ensure group write on existing items
sudo chmod -R g+rwX storage/app/tmp storage/app/private storage/logs

# Default ACLs so new files/dirs inherit access
# (add your own ops user too if desired)
sudo setfacl -R -m u:"$APP_USER":rwX -m g:"$APP_GROUP":rwX storage/app/tmp storage/app/private storage/logs
sudo setfacl -R -d -m u:"$APP_USER":rwX -m g:"$APP_GROUP":rwX storage/app/tmp storage/app/private storage/logs
```

### 6.3 SELinux (if enforcing)

If SELinux is enabled, allow RW access:

```bash
sudo semanage fcontext -a -t httpd_sys_rw_content_t "${APP_DIR}/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "${APP_DIR}/bootstrap/cache(/.*)?"
sudo restorecon -Rv "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
```

---

## 7) Nginx + PHP-FPM

### 7.1 Nginx site

Create `/etc/nginx/conf.d/cdds.conf`:

```nginx
server {
    listen 8005;
    server_name _;

    # allow uploads (exclusion ZIPs can be ~20MB)
    client_max_body_size 25m;

    root /var/www/html/cdds-laravel/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/cdds.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    location ~ /\. {
        deny all;
    }
}
```

Then:

```bash
sudo nginx -t
sudo systemctl enable --now nginx
sudo systemctl reload nginx
```

### 7.2 PHP-FPM pool

Create `/etc/php-fpm.d/cdds.conf`:

```ini
[cdds]
user = nginx
group = nginx

listen = /run/php-fpm/cdds.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660

pm = dynamic
pm.max_children = 15
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5

php_admin_flag[log_errors] = on
php_admin_value[error_log] = /var/log/php-fpm/cdds-error.log

; upload sizes (Laravel validates zip <= 20MB)
php_admin_value[upload_max_filesize] = 22M
php_admin_value[post_max_size] = 25M
```

Apply:

```bash
sudo systemctl enable --now php-fpm
sudo systemctl reload php-fpm
```

---

## 8) Laravel optimize/cache

```bash
cd "$APP_DIR"
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true
```

---

## 9) Queue worker (systemd service)

This app uses an `exports` queue for long-running jobs.

Create `/etc/systemd/system/cdds-queue-exports.service`:

```ini
[Unit]
Description=CDDS Laravel Queue Worker (exports)
After=network.target

[Service]
Type=simple
WorkingDirectory=/var/www/html/cdds-laravel
User=nginx
Group=nginx

ExecStart=/usr/bin/php artisan queue:work --queue=exports --sleep=3 --tries=3 --timeout=0

Restart=always
RestartSec=2
KillSignal=SIGTERM

NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

Enable + start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now cdds-queue-exports.service
sudo systemctl status --no-pager -l cdds-queue-exports.service
```

Logs:

```bash
sudo journalctl -u cdds-queue-exports.service -f
```

---

## 10) Scheduler (optional but typical)

If you use Laravel’s scheduler, add a cron entry:

```bash
sudo crontab -u "$APP_USER" -e
```

Add:

```cron
* * * * * cd /var/www/html/cdds-laravel && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## 11) Smoke tests

```bash
cd "$APP_DIR"
php artisan about
php artisan migrate:status

# queue sanity (should show running if jobs exist)
sudo systemctl status --no-pager -l cdds-queue-exports.service
```

---

## Common failures & fixes

### 413 Request Entity Too Large

- Fix in Nginx vhost: `client_max_body_size 25m;`

### Upload fails but Nginx accepts it

- Increase PHP-FPM pool limits: `upload_max_filesize` / `post_max_size`

### Jobs fail with permission denied under storage

- Re-apply ACLs + setgid from section 6.2
- Check SELinux labels from section 6.3

### “The ZIP file must contain exactly one Excel (.xlsx) workbook”

- Your uploaded ZIP contains 0 or multiple `.xlsx` files. Re-create the ZIP with exactly one workbook.

---
