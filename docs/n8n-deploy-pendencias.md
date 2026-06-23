# n8n — Pendências de Deploy (EC2 / AWS)

Checklist do que falta fazer fora do código para o n8n entrar no ar em produção. O código (`docker-compose.prod.yml`, `.docker/nginx/default.conf`, `docker-compose.yml` local) já foi ajustado neste repo.

## 1. DNS

Criar um registro **A** apontando para o mesmo IP público da EC2 que já serve `api.jornafy.com`:

```
n8n.jornafy.com  ->  <IP público da EC2>
```

Sem isso, o certificado TLS do passo 3 não pode ser emitido (validação HTTP do Let's Encrypt depende do DNS já resolver).

## 2. Security Group da AWS

Confirmar no Security Group da instância EC2:

| Porta | Origem | Situação esperada |
|---|---|---|
| 80 | 0.0.0.0/0 | Já liberada (serve api.jornafy.com) |
| 443 | 0.0.0.0/0 | Já liberada |
| 5678 | — | **NÃO liberar.** O n8n só deve ser acessível via proxy nginx (443), nunca direto |

Não é necessário abrir nenhuma porta nova — o n8n reaproveita 80/443 que já existem.

## 3. Certificado TLS do subdomínio

O nginx já está configurado (`default.conf`) esperando os arquivos `n8n.pem` e `n8n.key` em `./.docker/nginx/certs/` (mesma pasta onde já está `api.pem`/`api.key`). Esses arquivos **não existem ainda** — é preciso gerá-los na EC2:

```bash
cd /opt/jornafy-api

# Para liberar a porta 80 temporariamente para o certbot --standalone
docker compose -f docker-compose.prod.yml stop nginx

sudo certbot certonly --standalone -d n8n.jornafy.com

sudo cp /etc/letsencrypt/live/n8n.jornafy.com/fullchain.pem ./.docker/nginx/certs/n8n.pem
sudo cp /etc/letsencrypt/live/n8n.jornafy.com/privkey.pem  ./.docker/nginx/certs/n8n.key

docker compose -f docker-compose.prod.yml up -d nginx
```

> Se o certificado de `api.jornafy.com` foi emitido de outra forma (Cloudflare origin cert, wildcard, etc.), repita o mesmo processo em vez do `certbot --standalone` acima — o importante é o resultado final ser `n8n.pem`/`n8n.key` nesse diretório.

Lembrar de configurar renovação automática (se ainda não existir um cron/systemd timer do certbot rodando na instância) incluindo o novo domínio.

## 4. Variáveis de ambiente no servidor

O `docker-compose.prod.yml` usa `${N8N_HOST}`, `${N8N_PROTOCOL}`, `${N8N_WEBHOOK_URL}` e `${N8N_ENCRYPTION_KEY}` por interpolação. Essas variáveis **não vêm do `.env.production` do Laravel** — precisam estar no arquivo `.env` que fica ao lado do `docker-compose.prod.yml`, em `/opt/jornafy-api/.env` na EC2.

Gerar a chave de criptografia:

```bash
openssl rand -hex 32
```

Adicionar em `/opt/jornafy-api/.env`:

```bash
N8N_HOST=n8n.jornafy.com
N8N_PROTOCOL=https
N8N_WEBHOOK_URL=https://n8n.jornafy.com/
N8N_ENCRYPTION_KEY=<resultado do openssl rand -hex 32>
```

**Importante:** guardar esse `N8N_ENCRYPTION_KEY` em um cofre (1Password, Bitwarden, etc.). Se for perdido, as credenciais salvas dentro do n8n (ex.: chave da OpenAI) ficam ilegíveis e precisam ser recriadas.

### Erro "TypeError: Invalid URL" em appendResumeToken (qualquer node, ex. Code "Normalizar Entrada")

Bug conhecido do n8n self-hosted em versões acima da 2.13.4 (ainda presente na 2.26.9): sem `N8N_EDITOR_BASE_URL` configurado, o motor de execução quebra ao montar a resume URL interna (`getAdditionalKeys`/`appendResumeToken`) e **qualquer** node falha, mesmo um Code node trivial sem nenhuma URL no próprio código — não é bug no workflow. Refs: [n8n-io/n8n#28346](https://github.com/n8n-io/n8n/issues/28346), [n8n-io/n8n#29533](https://github.com/n8n-io/n8n/issues/29533).

Correção: definir `N8N_EDITOR_BASE_URL` com o mesmo valor de `WEBHOOK_URL` (já aplicado em `docker-compose.yml` e `docker-compose.prod.yml`, este último via `${N8N_WEBHOOK_URL}`). Depois de atualizar o `.env` em `/opt/jornafy-api`, recriar o container:

```bash
docker compose -f docker-compose.prod.yml up -d n8n
```

Em dev local basta `docker compose up -d n8n` (a var já está fixa no compose). Se o erro persistir mesmo com `N8N_EDITOR_BASE_URL` setado, confirmar nos issues acima se já existe correção em versão mais nova do n8n.

## 5. Sincronizar os arquivos editados com o servidor

Os arquivos `docker-compose.prod.yml` e `.docker/nginx/default.conf` foram editados aqui no repo, mas o deploy automático (`deploy.yml` / `release-please.yml`) **não copia esses arquivos para a EC2** — ele só faz build/push da imagem do Laravel e roda `docker compose up` usando o que já está em `/opt/jornafy-api`. É preciso atualizar manualmente:

```bash
# Opção A: se /opt/jornafy-api é um clone do repo
cd /opt/jornafy-api && git pull

# Opção B: copiar manualmente
scp src/docker-compose.prod.yml usuario@ec2:/opt/jornafy-api/docker-compose.prod.yml
scp src/.docker/nginx/default.conf usuario@ec2:/opt/jornafy-api/.docker/nginx/default.conf
```

## 6. Subir o serviço

```bash
cd /opt/jornafy-api
docker compose -f docker-compose.prod.yml pull n8n
docker compose -f docker-compose.prod.yml up -d n8n
docker compose -f docker-compose.prod.yml restart nginx   # recarrega o novo server_name
docker logs -f jornafy_n8n
```

## 7. Validar

- `curl -I https://n8n.jornafy.com` → deve responder `200`.
- Abrir `https://n8n.jornafy.com` no navegador → tela de criação da conta admin (primeiro acesso).
- `docker logs jornafy_n8n` sem erros de permissão/bind.

## 8. Credencial da OpenAI dentro do n8n

A chave da OpenAI **não vai em nenhum `.env`** — é cadastrada como Credential dentro da própria interface do n8n (Settings → Credentials → OpenAI API), já protegida pelo `N8N_ENCRYPTION_KEY` do passo 4.

## 9. Token de acesso à API do Laravel

A rota `POST /v1/admin/blog/posts` agora exige a role **`super_admin`** (antes era qualquer usuário autenticado — corrigido em `routes/api/v1/blog.php`). O token do n8n precisa ser de um usuário com essa role; usuários `admin` comuns recebem `403 Forbidden`.

Validado localmente (E2E real, não só leitura de código):
- Token de `super_admin` → `201 Created`, post salvo com `"source":"n8n"`.
- Token de `admin` comum → `403 Forbidden`.
- `config/sanctum.php` tem `expiration: null` → o token não expira, só é revogado manualmente.

### Conta de serviço dedicada (não reaproveitar o super_admin pessoal)

O n8n não precisa de login/senha — só do Bearer token. Mas o token deve vir de uma **conta de serviço dedicada** (`automacao@jornafy.com`, role `super_admin`), não da conta pessoal do super_admin que já usa o painel:

- **Revogação isolada:** se o token do n8n vazar, você revoga só os tokens dessa conta, sem deslogar ninguém do painel.
- **Clareza:** na tabela `personal_access_tokens` fica óbvio o que é automação e o que é sessão humana.
- **Custo zero:** `blog_posts` não tem coluna de "criado por usuário" (só o `source` adicionado abaixo), então criar essa conta extra não exige nenhuma migration nova.

Validado localmente (E2E real): criei essa conta de serviço, gerei o token, e o `POST /v1/admin/blog/posts` com ele retornou `201` com `"source":"n8n"` salvo.

Criar a conta e gerar o token em produção (rodar uma única vez, não versionar o token em nenhum lugar):

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan tinker
```
```php
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

$user = \App\Models\User::firstOrNew(['email' => 'automacao@jornafy.com']);
if (!$user->exists) {
    $user->id = (string) Str::uuid();
}
$user->company_id = null;
$user->name = 'Automação n8n';
$user->password = Hash::make(Str::random(40)); // senha não é usada para login, só o token importa
$user->save();
$user->syncRoles(['super_admin']);

$token = $user->createToken('n8n-blog-automation');
echo $token->plainTextToken;
```

Colar o token gerado na credencial de Header Auth do node HTTP Request no n8n (não em texto plano dentro do JSON do node).

### Flow pronto para importar

O workflow já está montado em `docs/n8n-workflows/blog-post-diario.json`. Para subir:

1. No n8n: **Workflows → Import from File** → selecionar esse JSON (ou colar o conteúdo em **Import from URL/Clipboard**).
2. Abrir os nodes **"Pesquisar Tema (OpenAI Web Search)"** e **"Gerar Conteúdo do Post (OpenAI)"** e selecionar/criar a mesma credential do tipo **OpenAi API** (Settings → Credentials → já mencionado no passo 8 acima). O node de pesquisa usa o tool `web_search_preview`, exige um modelo da OpenAI com suporte a Responses API (ex.: `gpt-4o`) — se a conta não tiver acesso, troque o `model` no node "Montar Pesquisa de Tema" por um que tenha.
3. Abrir os nodes **"Buscar Posts Recentes"** e **"Criar Post (platform, draft)"** e selecionar/criar uma credential do tipo **Header Auth**: nome do header `Authorization`, valor `Bearer <token gerado no passo acima>`.
4. Ativar o workflow (toggle "Active"). Isso ativa tanto o Schedule Trigger quanto o Form Trigger.

**Pipeline (execução automática diária, 08:00 America/Sao_Paulo):**
`Schedule Trigger` → `Normalizar Entrada` → `IF: tema foi sugerido manualmente?` (não, no caminho automático) → `Buscar Posts Recentes` (últimos 8 posts, pra IA não repetir tema) → `Montar Pesquisa de Tema` → `HTTP: OpenAI Responses API com web_search_preview` (pesquisa notícias/tendências reais e decide tema + keyword + categoria + intenção de busca) → `Montar Variáveis (Pesquisa)` → `Montar Prompt do Artigo` (substitui os placeholders no prompt grande de SEO/copywriting) → `HTTP: OpenAI chat/completions` (gera o artigo completo em pt/es/en) → `Montar Body do Post` (mapeia para o schema real da API) → `HTTP: POST /v1/platform/blog/posts`.

**Sugestão manual de tema:** o node **"Sugestão manual de tema"** é um Form Trigger — após importar e ativar o workflow, abra o node para copiar a URL do formulário (production URL) e guarde-a. Sempre que você tiver uma pauta específica, acesse essa URL e preencha "Tema sugerido" (e opcionalmente "Keyword sugerida" / "Categoria sugerida"); isso dispara o mesmo pipeline mas pula a etapa de pesquisa na web e usa direto o que você escreveu. Deixar o formulário em branco equivale a não usá-lo — o fluxo automático diário continua pesquisando por conta própria.

Sai sempre como **status: draft** de propósito — alguém precisa revisar e publicar manualmente no painel admin (ou via `PATCH /v1/platform/blog/posts/{id}/publish`) antes de ir ao ar.

**Mapeamento de campos:** o node "Montar Prompt do Artigo" reaproveita o prompt de SEO/copywriting fornecido (com placeholders de tema/keyword/categoria/intenção substituídos dinamicamente), que devolve `translations.{pt,es,en}.{title,excerpt,content,meta_title,meta_description,og_title,og_description}`. O node "Montar Body do Post" converte isso para o schema real esperado por `StoreBlogPostRequest`: `title`/`excerpt`/`content_html`/`seo_title`/`seo_description` (cada um como objeto `{pt,es,en}`), define `author: "Equipe Jornafy"` (fixo, a IA não gera esse campo) e `source: "n8n"`, e anexa a data ao slug para nunca colidir com um post de outro dia. Os campos `og_title`/`og_description` que a IA gera são descartados de propósito — a API não tem essas colunas (só existe `og_image_url`, que é uma URL).

**Categorias:** o prompt usa 6 categorias (`produto`, `controle-ponto`, `gestao-rh`, `compliance`, `produtividade`, `folha-ponto`). As 4 que não existiam foram adicionadas ao `BlogCategorySeeder` (ver seção de migrations abaixo) para aparecerem corretamente no filtro do blog público — rodar o seeder em produção depois do deploy.

### Marcando a origem do post (campo `source`)

Foi adicionada a coluna `source` em `blog_posts` (migration `2026_06_22_000001_add_source_to_blog_posts_table.php`, default `manual`). Para identificar posts gerados pelo n8n, envie `"source": "n8n"` no body do `POST`. O campo aparece em `BlogPostAdminResource` (painel admin) — **não** é exposto no endpoint público do blog (`BlogPostListResource`), de propósito, para não vazar detalhe interno no site.

Lembrar de rodar a migration em produção:

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
```

### Seed das categorias novas

`BlogCategorySeeder` ganhou 5 categorias novas (`produto`, `controle-ponto`, `gestao-rh`, `produtividade`, `folha-ponto` — só `compliance` já existia das 6 usadas pelo prompt; `registroHorario` continua existindo mas não é usada pelo prompt). Rodar em produção depois do deploy (é idempotente, usa `updateOrCreate`):

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan db:seed --class=BlogCategorySeeder --force
```

## Resumo de status

| Item | Responsável | Status |
|---|---|---|
| docker-compose.prod.yml (serviço n8n) | Código (feito neste repo) | ✅ |
| nginx default.conf (server_name n8n) | Código (feito neste repo) | ✅ |
| docker-compose.yml local (n8n para dev) | Código (feito neste repo) | ✅ |
| Rota /admin/blog travada para `role:super_admin` | Código (feito neste repo, testado E2E) | ✅ |
| Campo `source` (manual/n8n) em blog_posts | Código (feito neste repo, migration + testado E2E) | ✅ |
| Categorias novas no `BlogCategorySeeder` | Código (feito neste repo) | ✅ |
| Workflow n8n pronto (`docs/n8n-workflows/blog-post-diario.json`) | Código (feito neste repo) | ✅ |
| DNS n8n.jornafy.com | Infra (você) | ⬜ |
| Security Group AWS | Infra (você) | ⬜ verificar |
| Certificado n8n.pem/n8n.key | Infra (você) | ⬜ |
| .env em /opt/jornafy-api (N8N_*) | Infra (você) | ⬜ |
| Sync dos arquivos para a EC2 | Infra (você) | ⬜ |
| `docker compose up -d n8n` | Infra (você) | ⬜ |
| Migration `add_source_to_blog_posts_table` em produção | Infra (você) | ⬜ |
| Seed do `BlogCategorySeeder` em produção | Infra (você) | ⬜ |
| Criar conta de serviço `automacao@jornafy.com` (super_admin) em produção | Infra (você) | ⬜ |
| Importar o workflow no n8n + credential OpenAI + credential Header Auth | Infra (você) | ⬜ |
| Token Sanctum para o workflow | Infra (você) | ⬜ |
