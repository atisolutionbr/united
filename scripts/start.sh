#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

docker compose up -d

echo "Plataforma360: http://localhost:${APP_PORT:-8080}"
echo "Adminer: http://localhost:${ADMINER_PORT:-8081}"