#!/bin/bash
set -e

echo "Iniciando entrypoint do Laravel..."

# Espera banco (opcional)
if [ "${WAIT_FOR_DB}" = "true" ]; then
  DB_HOST="${DB_HOST:-localhost}"
  DB_PORT="${DB_PORT:-5432}"

  echo "Aguardando DB em ${DB_HOST}:${DB_PORT}..."
  MAX_TRIES=30
  TRIES=0

  until nc -z "${DB_HOST}" "${DB_PORT}" 2>/dev/null || [ $TRIES -eq $MAX_TRIES ]; do
    echo "Aguardando... (tentativa $((TRIES+1))/$MAX_TRIES)"
    TRIES=$((TRIES+1))
    sleep 2
  done

  if [ $TRIES -eq $MAX_TRIES ]; then
    echo "DB nao respondeu a tempo. Continuando mesmo assim..."
  else
    echo "DB OK!"
  fi
fi

# Composer install (NORMALMENTE FALSE em produção porque vendor já vem na imagem)
if [ "${RUN_COMPOSER_INSTALL}" = "true" ]; then
  echo "Rodando composer install..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# APP_KEY: em produção deve vir setada
if [ "${APP_ENV}" != "local" ]; then
  if [ -z "${APP_KEY}" ] || [[ "${APP_KEY}" != base64:* ]]; then
    echo "ERRO: APP_KEY nao definida corretamente para ambiente ${APP_ENV}."
    echo "Defina APP_KEY no .env.production/Secrets antes de subir."
    exit 1
  fi
fi

# Otimizações
if [ "${RUN_OPTIMIZE}" = "true" ]; then
  echo "Otimizando caches..."
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

# Migrations (por padrão FALSE)
if [ "${RUN_MIGRATIONS}" = "true" ]; then
  echo "Rodando migrations..."
  php artisan migrate --force
fi

# Permissões (sem 777)
echo "Ajustando permissoes..."
chown -R www-data:www-data /var/www
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "Aplicacao pronta!"

exec "$@"
