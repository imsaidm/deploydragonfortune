# Server deployment (clean install)

This guide assumes a fresh Linux server (Ubuntu/Debian-like) with SSH access.

## 0) Clean old deployment (safe way)

> Replace paths with your real server paths.

```bash
cd /var/www
sudo mv dragonfortune dragonfortune_backup_$(date +%F_%H%M%S) || true
```

If you truly want to delete everything:

```bash
sudo rm -rf /var/www/dragonfortune
```

## 1) Install requirements

You need:

- PHP 8.2+ (recommended 8.3) + extensions: `mbstring`, `xml`, `curl`, `sqlite3` (or `mysql`), `zip`
- Composer 2.x
- Node.js 18+ (recommended 20) + npm
- A web server: Nginx or Apache

## 2) Clone the new repo

```bash
cd /var/www
git clone https://github.com/imsaidm/deploydragonfortune.git dragonfortune
cd dragonfortune
```

## 3) Create `.env`

```bash
cp .env.example .env
```

Edit `.env`:

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Set `APP_URL=https://your-domain.com`
- Configure DB:
  - **SQLite (simplest):** `DB_CONNECTION=sqlite` and ensure `database/database.sqlite` exists
  - **MySQL:** set `DB_CONNECTION=mysql` + host/user/password/dbname
- Set integration keys as needed (Coinglass/CryptoQuant/FRED, etc)

## 4) One-command setup (recommended)

```bash
chmod +x scripts/server-setup.sh
./scripts/server-setup.sh
```

This runs: composer install (no-dev), npm build (if available), key generate, migrate, cache config/routes/views.

## 5) Permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 6) Nginx example (PHP-FPM)

Create `/etc/nginx/sites-available/dragonfortune`:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    root /var/www/dragonfortune/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable + reload:

```bash
sudo ln -s /etc/nginx/sites-available/dragonfortune /etc/nginx/sites-enabled/dragonfortune
sudo nginx -t
sudo systemctl reload nginx
```

## 7) Queue & Scheduler (optional)

If you use queues:

```bash
php artisan queue:work --daemon
```

For scheduler (cron):

```bash
* * * * * cd /var/www/dragonfortune && php artisan schedule:run >> /dev/null 2>&1
```

## 8) Verify

- Visit `APP_URL`
- Check logs: `storage/logs/laravel.log`
- If assets look broken, ensure you ran `npm run build` and that `public/build/` exists on the server.

