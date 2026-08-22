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
      && composer create-project laravel/laravel . --prefer-dist --no-interaction
  "

echo ""
echo "==> Laravel scaffolded successfully."
echo "==> Patching frontend/src/.env with Dayflow settings..."

ENV_FILE="${FRONTEND_SRC}/.env"

# Use python3 for .env patching — avoids sed -i portability issues
# between GNU sed (Linux/WSL) and the sed bundled with Git Bash on Windows.
python3 - "${ENV_FILE}" <<'PYEOF'
import sys, pathlib

path = pathlib.Path(sys.argv[1])
text = path.read_text()

replacements = {
    "APP_NAME=Laravel":   "APP_NAME=Dayflow",
    "APP_ENV=production": "APP_ENV=local",
    "APP_DEBUG=false":    "APP_DEBUG=true",
}
for old, new in replacements.items():
    text = text.replace(old, new)

if "BACKEND_URL" not in text:
    text = text.rstrip("\n") + "\nBACKEND_URL=http://backend:5000\n"

pathlib.Path(sys.argv[1]).write_text(text)
print("  .env patched successfully.")
PYEOF

echo "==> .env patched."
echo ""
echo "==> Done! You can now run: docker compose up --build"
