# Dragonfortune

Laravel 12 + Livewire dashboard with Vite-powered assets.

## Requirements

- PHP 8.2+
- Composer 2.x
- Node.js 18+ (recommended: 20+)

## Local development

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
composer run dev
```

Open `http://127.0.0.1:8000`.

If you ever see inconsistent behavior (e.g. different responses between requests), make sure you don’t have multiple `php artisan serve` processes running on the same port. On Windows you can start a clean single server with:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/dev-serve.ps1
```

## Server / production (typical)

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');" # if using sqlite
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ensure `storage/` and `bootstrap/cache/` are writable by the web user, and set `APP_ENV=production` and `APP_DEBUG=false`.

Optional: `chmod +x scripts/server-setup.sh && ./scripts/server-setup.sh`

See `docs/SERVER_DEPLOYMENT.md` for a full clean server walkthrough.

## Notes

- This repo does **not** commit `vendor/`, `node_modules/`, `storage/*` runtime files, or `public/build/`. After cloning, always run installs/build.
- Integration env vars live in `.env.example` (Coinglass, CryptoQuant, FRED, etc).
- API docs are in `docs/`.
