#!/bin/bash

set -e

echo "Iniciando entrypoint do Laravel..."

# Aguardar o banco de dados estar pronto
echo "Aguardando banco de dados..."
MAX_TRIES=30
TRIES=0

until nc -z postgres 5432 2>/dev/null || [ $TRIES -eq $MAX_TRIES ]; do
  echo "Aguardando PostgreSQL... (tentativa $((TRIES+1))/$MAX_TRIES)"
  TRIES=$((TRIES+1))
  sleep 2
done

if [ $TRIES -eq $MAX_TRIES ]; then
  echo "PostgreSQL nao esta respondendo, mas continuando..."
else
  echo "PostgreSQL esta disponivel!"
fi

# Instalar/atualizar dependências do Composer
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Instalando dependencias do Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "Dependencias do Composer ja instaladas"
fi

# Gerar chave da aplicação se não existir
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo "Gerando chave da aplicacao..."
    php artisan key:generate --force
fi

# Rodar migrations automaticamente SOMENTE em ambiente local
if [ "$APP_ENV" = "local" ]; then
    echo "Ambiente local detectado — executando migrations..."
    php artisan migrate --database=pgsql_local --force
else
    echo "Ambiente '$APP_ENV' — migrations automaticas ignoradas"
fi

# Ajustar permissões
echo "Ajustando permissoes..."
chown -R www-data:www-data /var/www
chmod -R 777 /var/www/storage
chmod -R 777 /var/www/bootstrap/cache

echo "Aplicacao pronta!"
echo "Acesse: http://localhost:8000"

# Executar o comando passado para o container (php-fpm)
exec "$@"
