#!/bin/bash
# ─── scaffold-laravel.sh ──────────────────────────────────────────────────────
# Run this ONCE on the host to scaffold Laravel into frontend/src/
# Requires Docker Desktop to be running.
#
# Compatible with: macOS · Linux · Git Bash on Windows · WSL/WSL2
#
# Usage:
#   ./scaffold-laravel.sh
#
# Git Bash on Windows note:
#   If you see a Docker path-conversion error, run as:
#   MSYS_NO_PATHCONV=1 ./scaffold-laravel.sh
# ─────────────────────────────────────────────────────────────────────────────

set -e

# Disable Git Bash path auto-conversion for Docker volume mounts on Windows
export MSYS_NO_PATHCONV=1

FRONTEND_SRC="$(pwd)/frontend/src"

echo "==> Clearing frontend/src/ before scaffolding..."

# Remove any placeholder files (e.g. .gitkeep) so composer finds an empty dir.
# Preserves the directory itself.
find "${FRONTEND_SRC}" -mindepth 1 -delete 2>/dev/null || true

echo "==> Scaffolding Laravel into frontend/src/ using Docker..."

# Use PHP 8.3-cli image — install zip/unzip/git/curl first, then Composer, then scaffold.
# This ensures composer.lock is generated for PHP 8.3, matching the Dockerfile.
docker run --rm \
  -v "${FRONTEND_SRC}:/app" \
  -w /app \
  php:8.3-cli \
  bash -c "
    apt-get update -qq && apt-get install -y -qq unzip zip git curl libzip-dev \
      && docker-php-ext-install zip \
      && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
      && rm -rf /tmp/dayflow-laravel \
      && composer create-project laravel/laravel /tmp/dayflow-laravel --prefer-dist --no-interaction \
      && cp -a /tmp/dayflow-laravel/. /app/
  "

echo ""
echo "==> Laravel scaffolded successfully."
echo "==> Patching frontend/src/.env with Dayflow settings..."

ENV_FILE="${FRONTEND_SRC}/.env"

# Use portable shell tools so Python is not required on the host.
ENV_FILE_TMP="${ENV_FILE}.tmp"
sed -e 's/^APP_NAME=Laravel$/APP_NAME=Dayflow/' -e 's/^APP_ENV=production$/APP_ENV=local/' -e 's/^APP_DEBUG=false$/APP_DEBUG=true/' -e 's/^SESSION_DRIVER=database$/SESSION_DRIVER=file/' -e 's/^CACHE_STORE=database$/CACHE_STORE=file/' "${ENV_FILE}" > "${ENV_FILE_TMP}"
mv "${ENV_FILE_TMP}" "${ENV_FILE}"

if ! grep -q '^BACKEND_URL=' "${ENV_FILE}"; then
  printf '\nBACKEND_URL=http://backend:5000\n' >> "${ENV_FILE}"
fi

echo "  .env patched successfully."

echo "==> .env patched."
echo ""
echo "==> Done! You can now run: docker compose up --build"
