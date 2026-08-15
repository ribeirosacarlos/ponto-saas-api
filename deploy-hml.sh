#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

COMPOSE=(docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml)

require_file() {
  if [ ! -f "$1" ]; then
    echo "Missing required file: $1" >&2
    exit 1
  fi
}

require_file ".env.hml"
require_file ".docker/nginx/hml.htpasswd"
require_file ".docker/nginx/certs/hml-api.pem"
require_file ".docker/nginx/certs/hml-api.key"

git pull --ff-only

"${COMPOSE[@]}" config >/dev/null
"${COMPOSE[@]}" build app
"${COMPOSE[@]}" up -d --remove-orphans

"${COMPOSE[@]}" exec -T app php artisan optimize:clear
"${COMPOSE[@]}" exec -T app php artisan config:cache
"${COMPOSE[@]}" exec -T app php artisan route:cache
"${COMPOSE[@]}" exec -T app php artisan view:cache

echo "HML deploy finished. Run migrations manually only after confirming the HML database is isolated:"
echo "docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml exec -T app php artisan migrate --force"
