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

echo "Done. Ensure storage/ and bootstrap/cache/ are writable by your web server user."
