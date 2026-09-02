#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

docker compose down -v --remove-orphans
docker compose build --no-cache
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction || true