#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

if [ ! -f .env ]; then
  cp .env.example .env
fi

docker compose build
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction || true

echo "Plataforma360 instalada em http://localhost:${APP_PORT:-8080}"
echo "Adminer disponivel em http://localhost:${ADMINER_PORT:-8081}"