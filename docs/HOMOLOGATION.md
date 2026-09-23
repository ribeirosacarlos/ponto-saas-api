# Homologacao Hostinger

Este ambiente sobe `hml-api.jornafy.com` na Hostinger sem alterar a producao atual em `api.jornafy.com` na AWS.

## Arquitetura Encontrada

- Dockerfiles encontrados: `.docker/php/Dockerfile` e `vendor/swagger-api/swagger-ui/Dockerfile` de dependencia.
- Compose encontrado: `docker-compose.yml` e `docker-compose.prod.yml`; nao ha `compose.yaml`.
- Laravel roda em PHP-FPM pela imagem de `.docker/php/Dockerfile`.
- PHP customizado em `.docker/php/php.ini` com upload/post de 100M, erros em stderr e display errors desligado.
- PHP-FPM envia logs para stdout/stderr via `.docker/php/zz-logs.conf`.
- Nginx atual esta em `.docker/nginx/default.conf` e atende `api.jornafy.com` e `n8n.jornafy.com`.
- Supervisor nao foi encontrado; filas rodam como container dedicado.
- Compose atual usa imagem do AWS ECR, `.env.production` e logging `awslogs`.
- Queue usa `php artisan queue:work database --queue=default`.
- Scheduler existe em `routes/console.php` e executa `subscriptions:sync-expired-access` a cada cinco minutos.
- Banco configurado no Laravel e PostgreSQL (`config/database.php`), mas o compose de producao nao sobe Postgres.
- Redis existe apenas como configuracao Laravel, sem container no compose atual.
- Volumes atuais: `public_data` para `/var/www/public` e `n8n_data` para o n8n.
- Portas atuais: API publica em 80/443 no Nginx; n8n fica em 5678, em producao preso a `127.0.0.1`.
- Storage suporta local e S3; alguns fluxos de documentos/certificados usam `Storage::disk('s3')` diretamente.
- Emails transacionais usam Resend em producao; HML deve usar `MAIL_MAILER=log` ou SMTP dedicado de teste.
- Stripe esta em `routes/api/v1/public.php`: `POST /api/v1/billing/stripe/webhook`.
- CI/CD atual esta em `.github/workflows/deploy.yml` e `.github/workflows/release-please.yml`, publicando imagem no ECR e atualizando EC2 via SSH.

## Dependencias AWS e Riscos

- Deploy atual: GitHub Actions -> AWS ECR -> EC2.
- Logging atual: CloudWatch via `awslogs`.
- Storage pode depender de S3 nos fluxos de documentos, certificados, blog e PDFs.
- `config/queue.php` e `config/cache.php` tambem suportam SQS/DynamoDB, embora HML use database por padrao.
- `config/services.php` possui SES, Stripe e Resend.
- Usar banco de producao em HML pode modificar dados reais, disparar jobs, alterar acesso de empresas pelo scheduler e processar webhooks.
- Usar credenciais Stripe live em HML pode gerar cobrancas reais.
- Usar Resend real em HML pode enviar emails para clientes.

## Arquivos de HML

- `docker-compose.hml.yml`: override do compose base para Hostinger.
- `.env.hml.example`: template sem secrets.
- `.docker/nginx/hml.conf`: vhost de homologacao com Basic Auth.
- `.docker/nginx/cloudflare-real-ip.conf`: ranges da Cloudflare para IP real.
- `deploy-hml.sh`: fluxo simples de deploy.

## Arquitetura de HML

- `app`: build local de `.docker/php/Dockerfile`, sem ECR.
- `nginx`: publica somente 80/443 e usa vhost `hml-api.jornafy.com`.
- `queue-worker`: processa fila `database/default` no banco HML.
- `hml-db`: Postgres 16 com volume persistente `hml_postgres_data`.
- `scheduler`: profile opcional, desligado por padrao.
- `n8n`: profile opcional, desligado por padrao.
- Logs usam `json-file` com rotacao `10m`/`5` arquivos.
- Volumes HML adicionados: `hml_storage` para `/var/www/storage` e `hml_postgres_data` para Postgres.

## Preparacao

Copie o template e preencha secrets somente no servidor:

```bash
cp .env.hml.example .env.hml
php artisan key:generate --show
```

Crie o Basic Auth:

```bash
mkdir -p .docker/nginx/certs
printf "usuario:$(openssl passwd -apr1)\n" > .docker/nginx/hml.htpasswd
```

Instale um certificado de origem para `hml-api.jornafy.com` em:

```text
.docker/nginx/certs/hml-api.pem
.docker/nginx/certs/hml-api.key
```

Use Cloudflare SSL/TLS em `Full (strict)`. O certificado pode ser Cloudflare Origin Certificate ou Let's Encrypt. Nunca versionar certificados privados.

O Basic Auth protege o ambiente inteiro, com excecao intencional de `/health`, `/up` e do webhook Stripe de HML. O webhook fica sem Basic Auth porque o Stripe precisa acessa-lo diretamente; a protecao desse endpoint deve ser o `STRIPE_WEBHOOK_SECRET` de test mode.

## Deploy

Validar compose:

```bash
docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml config
```

Subir HML:

```bash
./deploy-hml.sh
```

Rodar migrations apenas depois de confirmar que `DB_HOST`, `DB_DATABASE` e credenciais apontam para banco isolado de HML:

```bash
docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml exec -T app php artisan migrate --force
```

Scheduler fica desabilitado por padrao via profile. Habilite somente com banco isolado, Stripe test mode e email isolado:

```bash
docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml --profile scheduler up -d scheduler
```

## Logs

```bash
docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml logs -f
docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml logs -f app
docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml logs -f nginx
docker compose --env-file .env.hml -f docker-compose.yml -f docker-compose.hml.yml logs -f queue-worker
```

## Checklist de Validacao

- [ ] `https://hml-api.jornafy.com/health` responde sem dados internos.
- [ ] HTTPS funciona com Cloudflare em Full strict.
- [ ] IP real aparece via `CF-Connecting-IP`/`X-Forwarded-For`.
- [ ] Basic Auth protege rotas publicas de HML, exceto healthcheck e webhook Stripe.
- [ ] Autenticacao Sanctum/token funciona.
- [ ] API principal funciona.
- [ ] Banco HML conecta e nao aponta para producao.
- [ ] Redis nao e compartilhado com producao se for habilitado depois.
- [ ] Cache funciona com prefixo/armazenamento isolado.
- [ ] Sessoes funcionam com cookie `jornafy_hml_session`.
- [ ] Queue processa jobs em banco HML.
- [ ] Scheduler foi habilitado somente apos revisar tarefas.
- [ ] Uploads funcionam.
- [ ] Storage local ou bucket HML dedicado funciona.
- [ ] Emails ficam em log/Mailtrap/Mailpit e nao chegam a clientes reais.
- [ ] Stripe usa `sk_test_*` e endpoint HML.
- [ ] Webhook `https://hml-api.jornafy.com/api/v1/billing/stripe/webhook` funciona com segredo de teste.
- [ ] PDFs funcionam.
- [ ] Exportacoes funcionam.
- [ ] Registro de ponto funciona.
- [ ] Geolocalizacao funciona.
- [ ] Timezone `America/Sao_Paulo` esta correto.
- [ ] Logs rotacionam pelo driver `json-file`.
- [ ] Containers reiniciam corretamente apos reboot.

## Futuro Cutover de Producao

Nao alterar DNS agora. Quando a Hostinger estiver validada, manter a AWS ativa e mudar no Cloudflare apenas o registro de `api.jornafy.com` para o origin da Hostinger. O rollback e reverter esse registro para a AWS.
