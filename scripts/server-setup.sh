#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example. Update it for your server, then re-run this script."
  exit 1
fi

db_connection="$(grep -E '^DB_CONNECTION=' .env | tail -n 1 | cut -d= -f2- | tr -d '\"' || true)"
if [[ "${db_connection:-}" == "sqlite" ]]; then
  db_database="$(grep -E '^DB_DATABASE=' .env | tail -n 1 | cut -d= -f2- | tr -d '\"' || true)"
  if [[ -z "${db_database:-}" ]]; then
    db_database="database/database.sqlite"
  fi
  if [[ "${db_database}" != /* ]]; then
    db_database="${PWD}/${db_database}"
  fi
  mkdir -p "$(dirname "${db_database}")"
  touch "${db_database}"
fi

composer install --no-interaction --no-dev --optimize-autoloader

if command -v npm >/dev/null 2>&1; then
  npm ci
  npm run build
else
  echo "npm not found; skipping asset build (public/build will be missing)."
fi

if ! grep -qE '^APP_KEY=' .env || grep -qE '^APP_KEY=$' .env; then
  php artisan key:generate --ansi
fi

php artisan vendor:publish --tag=livewire:assets --force

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Fixing permissions (storage/ and bootstrap/cache/ must be writable)..."

mkdir -p storage/framework/{cache,views,sessions} storage/logs bootstrap/cache

web_user="www"
if ! id -u "${web_user}" >/dev/null 2>&1; then
  web_user="www-data"
fi

# Make sure directories/files are group writable.
chmod -R ug+rwX storage bootstrap/cache || true
find storage bootstrap/cache -type d -exec chmod 775 {} \; 2>/dev/null || true
find storage bootstrap/cache -type f -exec chmod 664 {} \; 2>/dev/null || true

if [[ "$(id -u)" == "0" ]]; then
  chown -R "${web_user}:${web_user}" storage bootstrap/cache 2>/dev/null || true
else
  echo "Not running as root; skipping chown. Ensure ownership allows PHP to write."
fi

echo "Done."
