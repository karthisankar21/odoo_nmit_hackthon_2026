#!/bin/sh
# ─── DB Migration Script ──────────────────────────────────────────────────────

set -e

echo "==> Waiting for PostgreSQL to be ready..."
until flask db current > /dev/null 2>&1; do
  echo "    Postgres not ready yet — retrying in 2s..."
  sleep 2
done

echo "==> Running DB migrations..."
flask db upgrade

echo "==> Migration complete!"
