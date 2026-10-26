# Migração para Laravel — Funcionalidades Atuais e Plano de Recriação

&gt; Documento-base para migrar o sistema **tcm-scrapping** (Python/FastAPI) para **Laravel**.
&gt; Sistema atual: monitoramento do Diário Oficial do TCM-BA (Tribunal de Contas dos Municípios da Bahia) via busca enviesada por termos (municípios/empresas), com coleta sob demanda, enriquecimento de processos e resumo determinístico (sem LLM).

---

## 1. Visão geral da stack atual

| Componente | Atual | Alvo (Laravel) |
|---|---|---|
| Linguagem | Python ≥ 3.11 | PHP 8.3+ |
| Framework | FastAPI + Uvicorn | Laravel 11/12 |
| Banco | MySQL 8 (PyMySQL síncrono) | MySQL 8 (Eloquent / Query Builder) |
| Auth | JWT HS256 (PyJWT) + Argon2id (pwdlib) | Laravel Sanctum ou JWT (tymon/jwt-auth) + Hash::argon |
| Validação | Pydantic | Form Requests + Resources |
| Rate limit | slowapi (em memória, por IP) | Laravel RateLimiter (por rota/chave) |
| Fila/crons | **Nenhum** (coleta síncrona no request) | Opcional: Jobs/Queues (ver §10 — manter contrato síncrono na v1) |
| HTTP client | httpx (async, suporte SOCKS) | Laravel Http (Guzzle) — proxy SOCKS requer config Guzzle |
| Frontend | Vite + TypeScript estático (MPA, sem framework) | Manter como está (ou Blade/Vue depois — ver §10) |
| Testes | pytest (70 testes) + vitest | PHPUnit/Pest (recriar suite) |
| Lint | ruff | Laravel Pint |
| Deploy | Docker → Render / GitHub Actions → VPS | Reavaliar (§11) |

---

## 2. Estrutura de rotas da API (contrato HTTP — RECREAR 1:1)

&gt; O frontend consome exatamente estes paths/métodos/códigos. **Preservar o contrato** para o front continuar funcionando sem alterações.

| Método | Rota | Função | Auth | Rate limit | Respostas |
|---|---|---|---|---|---|
| GET | `/` | Página placeholder HTML | público | — | 200 |
| GET | `/api/health` | Healthcheck | público | — | 200 `{"status":"ok"}` |
| POST | `/api/auth/login` | Login | público | 10/min por IP | 200 `TokenOut` / 401 / 429 |
| GET | `/api/auth/me` | Identidade do usuário logado | Bearer | — | 200 `UsuarioAtualOut` |
| GET | `/api/termos` | Listar termos (`?somente_ativos=bool`) | Bearer | 30/min | 200 `list[TermoOut]` |
| POST | `/api/termos` | Criar termo | Bearer | 30/min | 201 / 409 |
| PUT | `/api/termos/{id}` | Atualizar termo | Bearer | 30/min | 200 / 404 / 409 |
| DELETE | `/api/termos/{id}` | Excluir termo | Bearer | 30/min | 204 / 404 |
| GET | `/api/edicao` | Edição atual (tempo real na fonte) | Bearer | 120/min | 200 `Edicao` |
| GET | `/api/edicoes` | Edições recentes (`?dias=30`, 1..1100) | Bearer | 120/min | 200 `list[Edicao]` |
| POST | `/api/buscar` | **Executar coleta** | Bearer | 10/min | 201 `BuscaOut` / 502 |
| GET | `/api/ocorrencias` | Ocorrências (leitura do banco) | Bearer | 120/min | 200 `list[OcorrenciaOut]` |
| GET | `/api/resumo` | Resumo Markdown/JSON (leitura do banco) | Bearer | 120/min | 200 `Resumo` |
| GET | `/api/processos` | Processos (leitura do banco) | Bearer | 120/min | 200 `list[ProcessoOut]` |
| GET | `/api/usuarios` | Listar contas | **superadmin** | 120/min | 200 `list[UsuarioOut]` |
| POST | `/api/usuarios` | Criar conta (sempre papel `admin`) | **superadmin** | 30/min | 201 |
| PUT | `/api/usuarios/{id}` | Alterar conta/senha | **superadmin** | 30/min | 200 |
| DELETE | `/api/usuarios/{id}` | Excluir conta | **superadmin** | 30/min | 204 |

### 2.1 Parâmetros de período (`PeriodoIn`)
- Nos **GET**: query string — `?periodo=hoje|7|30` **ou** `?inicio=AAAA-MM-DD&amp;fim=AAAA-MM-DD`.
- No **POST /api/buscar**: body JSON com a mesma estrutura.
- Validações: datas ISO válidas; `fim ≥ início`; pelo menos um dos modos; máx. 1100 dias.

### 2.2 Códigos de erro do contrato
`401` (token inválido/expirado/conta desativada), `403` (`{"detail":"Acesso negado"}`), `404`, `409` (duplicidade), `422` (validação), `429` (rate limit), `502` (fonte indisponível — mensagem genérica em produção).

&gt; ⚠️ **Atenção**: o FastAPI retorna 422 no formato `{loc, msg}` (lista), que o front já traduz. Laravel usa `{message, errors: {campo: [msgs]}}` → ou ajustar o handler `fastapiIssues` no front, ou criar um `ValidationException::render` customizado que emule o formato antigo (recomendado para não tocar no front).

### 2.3 Estruturas de resposta principais (recriar como API Resources)
- **Edicao**: `id, data, numero?, paginas?, suplemento, tipo_edicao_nome?`
- **Meta**: `duracao_ms, requests, requests_tcm, avisos[], cache`
- **OcorrenciaOut**: `termo, termo_id, tipo_termo, edicao_id, data, pagina, tipo(ato|mencao), trecho, contexto, atos[], ocorrencias_na_pagina, pdf_url, processos[]`
- **BuscaOut**: `periodo, edicoes, ocorrencias, processos, resumo, meta`
- **Resumo**: `por_termo[] (TermoResumo), geral (ResumoGeral com markdown + json)`
- **ProcessoOut**: `numero, encontrado, data_entrada?, autor?, complemento?, situacao?, situacao_data?, meio?, andamento[{unidade,data,situacao,observacao}], consulta_url, consultado_em?`
- **TermoIn/Out**: `id?, termo (2–120, espaços colapsados), tipo (municipio|empresa), ativo, variacoes[] (max 10)`
- **Usuario**: `id, username (regex ^[a-zA-Z0-9._-]+$), role, ativo, criado_em` — senha 8–128, nunca retornada.

---

## 3. Banco de dados — schema MySQL (RECREAR via migrations)

&gt; Colunas `VARCHAR` de datas/timestamps e `TEXT` de JSON são o schema atual; §10 lista melhorias opcionais para Laravel.

### 3.1 `edicoes` (PK = id externo da fonte EGBA)
`id INT PK` · `data VARCHAR(10)` · `numero INT NULL` · `paginas INT NULL` · `suplemento TINYINT DEFAULT 0` · `tipo_edicao_nome VARCHAR(255) NULL`

### 3.2 `termos`
`id INT AI PK` · `termo VARCHAR(120) NOT NULL UNIQUE` · `tipo VARCHAR(20) DEFAULT 'municipio'` · `ativo TINYINT DEFAULT 1` · `variacoes TEXT NOT NULL` (JSON array) · `created_at VARCHAR(30)`

### 3.3 `ocorrencias`
`id INT AI PK` · `edicao_id INT FK→edicoes CASCADE` · `pagina INT` · `termo_id INT FK→termos CASCADE` · `trecho TEXT` · `contexto TEXT` · `atos TEXT` (JSON) · `processos TEXT` (JSON) · `tipo VARCHAR(20) DEFAULT 'mencao'` · `ocorrencias_na_pagina INT DEFAULT 1` · `pdf_url VARCHAR(500) DEFAULT ''` · `periodo_inicio VARCHAR(10)` · `periodo_fim VARCHAR(10)` · `created_at VARCHAR(30)`
- **UNIQUE** `(edicao_id, pagina, termo_id, periodo_inicio, periodo_fim)` → **idempotência por período**
- **KEY** `(periodo_inicio, periodo_fim)`

### 3.4 `processos` (cache da consulta-processual)
`numero VARCHAR(30) PK` · `encontrado TINYINT DEFAULT 0` · `data_entrada VARCHAR NULL` · `autor VARCHAR NULL` · `complemento TEXT` · `andamento TEXT` (JSON array) · `situacao VARCHAR(120)` · `situacao_data VARCHAR(10)` · `meio VARCHAR(60)` · `consultado_em VARCHAR(30)`

### 3.5 `termo_processos` (vínculo termo↔processo por período)
`termo_id INT FK CASCADE` · `processo_numero VARCHAR(30)` · `periodo_inicio VARCHAR(10)` · `periodo_fim VARCHAR(10)` · `created_at VARCHAR(30)`
- **PK** `(termo_id, processo_numero, periodo_inicio, periodo_fim)` · **KEY** `(periodo_inicio, periodo_fim)`

### 3.6 `buscas` (log/auditoria de cada coleta)
`id INT AI PK` · `periodo_inicio` · `periodo_fim` · `termos INT` · `requests INT` · `duracao_ms INT` · `status VARCHAR(10) DEFAULT 'ok'` (ok|parcial|erro) · `erro TEXT NULL` · `created_at VARCHAR(30)`

### 3.7 `usuarios`
`id INT AI PK` · `username VARCHAR(50) UNIQUE` · `senha VARCHAR(255)` (Argon2id) · `role VARCHAR(20) DEFAULT 'admin'` (superadmin|admin) · `ativo TINYINT DEFAULT 1` · `tentativas INT DEFAULT 0` · `bloqueado_ate VARCHAR(30) NULL` · `criado_em VARCHAR(30)`

### 3.8 Seeds
- **Termos**: inserir `termos.json` somente se a tabela estiver vazia — 11 termos (9 municípios: Maiquinique, Cândido Sales, Bom Jesus da Serra, Itarantim, Potiraguá, Boa Nova, Buerarema, Itajuípe, Santanópolis; 2 empresas: Alfacon, Plenary), todos ativos, sem variações → `DatabaseSeeder` condicional.
- **Superadmin**: criar uma única vez no boot se `TCM_SUPERADMIN_SENHA` estiver definida; nunca recriado/alterado → `seeder` + comando artisan.

### 3.9 Migrações idempotentes do sistema atual (só para referência — no Laravel, migrations normais resolvem)
Adicionar coluna `processos` em ocorrências · backfill de números de processo · estender UNIQUE de `ocorrencias` para incluir período · popular `termo_processos` a partir de ocorrências existentes.

---

## 4. Lógica de negócio a recriar

### 4.1 Cliente EGBANET (`integrations/egba`) → `app/Services/EgbanetClient.php`
API **não documentada** de `https://egbanet.egba.ba.gov.br` (`subtheme=tcm`):

| Método | Endpoint |
|---|---|
| `edicaoAtual()` | `GET /apifront/portal/edicoes/edicoes_from_data.json?subtheme=tcm` |
| `edicoesRecentes()` | `GET /apifront/portal/edicoes/ultimas_edicoes.json?subtheme=tcm` (~711 edições / 3 anos) |
| `buscarPagina(termo, inicio, fim, pagina)` | `GET /busca/busca/buscar/query/{pagina}/di:AAAA-MM-DD/df:AAAA-MM-DD/?1=1&amp;q="termo"&amp;subtheme=tcm` (10 hits/página) |
| `buscarTermo(...)` | paginação até `hits.total` ou `max_paginas` (20 = teto 200 hits) |
| `urlPdf(edicaoId, pagina)` | `{base}/{subtema}/portal/edicoes/download/{id}[/{pagina}]` (só monta URL) |

**Regras de contrato a preservar:**
- **Um termo por requisição**; boolean/curinga não funcionam; termos com espaço **precisam de aspas** (`q="Boa Nova"`).
- `subtheme=tcm` obrigatório; insensível a acento/caixa, sem stemming cruzado.
- Retry com backoff progressivo (`0.3 * tentativa`) em 5xx/erro de rede, até `http_retries` (2); erro tipado → 502 `FonteIndisponivel`.
- Data BR `DD/MM/AAAA` → ISO.
- User-Agent configurável (`tcm-scrapping/0.1`); timeout 30s.
- Contador de `requests` alimenta `meta.requests`.

### 4.2 Período (`utils/periodo`) → `app/Services/PeriodoService.php` ou Value Object
- Presets: `hoje` = dia corrente em **`America/Bahia`**; `7` = hoje−6; `30` = hoje−29.
- Intervalo livre `inicio/fim` com validação (422 `PeriodoInvalido`).

### 4.3 Serviço de coleta (`services/coleta`) → `app/Services/ColetaService.php` — **coração do sistema**
Fluxo de `executar(pedido)`:
1. Listar termos ativos; resolver o período.
2. Filtrar `edicoesRecentes()` pelo período; se vazio, tentar `edicaoAtual()`; senão aviso "nenhuma edição publicada entre X e Y".
3. Upsert das edições; criar edição de um hit se o listamento atrasar (preserva FK).
4. Para **cada grafia** (termo + variações), buscar em paralelo (semáforo de concorrência = 3); cada hit passa pelo enriquecimento; hits sem `edicao_id` descartados; ocorrência inserida com `INSERT IGNORE` (idempotente por período) + vínculos em `termo_processos`. **Falha de um termo vira aviso (status `parcial`), não aborta.**
5. Enriquecer processos: números citados no período que **não estão em cache** na tabela `processos`, consulta em paralelo (semáforo = 3); `encontrado=False` é persistido (evita reconsulta); falha de rede vira aviso **sem** persistir (retry na próxima coleta).
6. Registrar execução em `buscas` (sempre, inclusive em erro da fonte → status `erro`), montar payload com resumo.

&gt; **Nota de concorrência**: o atual usa `asyncio.gather` + `Semaphore`. Em Laravel (síncrono), usar `Pool` do Guzzle paralelo, ou manter sequencial com controle de tempo (a busca pode durar até ~90s — timeout do front). Ver §10 sobre filas.

Função `cache(pedido)`: lê tudo do banco **sem tocar a fonte**; `meta.cache=true`.

### 4.4 Enriquecimento determinístico (`utils/enrich`) → `app/Services/EnrichService.php` (sem LLM)
1. **Normalização**: minúsculas + sem acentos (NFD) + espaços colapsados (preservar mapa de índices para recorte).
2. **Contexto**: localizar o destaque dentro do conteúdo de forma insensível a acento/caixa; recortar ± `context_chars` (400); fallback = início.
3. **Contagem**: ocorrências com boundary word na frase normalizada.
4. **Atos**: janela ± `atos_window_chars` (1500) com 5 regex — Processo/PROC nº, Acórdão nº, Pregão Eletrônico nº, Edital nº, Prefeitura/Câmara Municipal de X.
5. **Processos**: regex `\b(\d{4,6}[e-]\d{2})[A-Za-z]{0,3}\b` → base minúscula (sufixo tipo `APR` é do acórdão; só a base é consultada).
6. **Classificação**: `ato` se ≥1 ato na janela, senão `mencao`.
7. **PDF**: `{base_url}/{subtema}/portal/edicoes/download/{edicao_id}/{pagina}`.

### 4.5 Consulta-processual TCM (`integrations/tcm`) → `app/Services/TcmConsultaClient.php`
- **POST** form-urlencoded em `https://www.tcm.ba.gov.br/controle-social/consulta-processual/` com `proc=&lt;numero&gt;&amp;consulta=ok&amp;B1=Consultar` (só POST funciona; GET devolve form vazio; webservice JSON devolve `[]` — reserva).
- **Parse do HTML**: pares `&lt;label&gt;…&lt;/label&gt;&lt;span&gt;…&lt;/span&gt;` apenas do bloco **antes** de `&lt;!-- MODAL DE ANDAMENTO --&gt;`; campos: `Data de Entrada`, `Interessado/Autor`, `Complemento`, `Data`, `Meio`; andamento da tabela `#tabelaResultado` (Unidade/Data/Situação/Observação, cabeçalho descartado, mais recente 1º).
- Processo inexistente → aviso "Não foi possivel localizar o processo" → `encontrado=false` (persistido). Markup irreconhecível → 502.
- Retry em 5xx com backoff; **proxy SOCKS opcional** (`TCM_CONSULTA_PROCESSO_PROXY`, ex. Cloudflare WARP) — no Laravel/Guzzle, exigirá extensão curl com SOCKS ou proxy local.

### 4.6 Resumo determinístico (`utils/resumo`) → `app/Services/ResumoService.php`
- **Por termo**: páginas únicas, ocorrências, datas, itens Markdown (`- DD/MM/AAAA · p. N · ato|menção — atos; link PDF`), processos (união das ocorrências + vínculos do período), texto próprio ("nada encontrado no período").
- **Geral**: Markdown com período, edições varridas, termos com/sem ocorrência, páginas/ocorrências, lista de termos afetados, termos sem ocorrência, contagem de processos localizados e seção `### Processos envolvidos` (número, entrada, situação+data, autor, complemento, link; não localizados marcados).

### 4.7 Regras de domínio — termos
- Criação: 409 se texto já existe (UNIQUE).
- Atualização: 404 se não existe; 409 em duplicidade.
- Exclusão: cascade remove ocorrências e vínculos (tabela `processos` permanece).
- Termo inativo sai da coleta **e** dos resultados de leitura (joins filtram `ativo = 1`), histórico fica no banco.

### 4.8 Regras de domínio — usuários
- Mensagem **única** de login: "Credenciais inválidas" para usuário inexistente, senha errada e conta bloqueada.
- **Hash-sonde** anti-timing (verificar um hash falsa quando o usuário não existe).
- **Lockout**: 5 tentativas → bloqueio 15 min (`tentativas` + `bloqueado_ate`); sucesso zera.
- Criação de conta: sempre papel `admin` (nunca vem do cliente).
- Superadmin não pode se desativar nem se excluir.
- Troca de senha zera tentativas/bloqueio.
- Seed do superadmin só uma vez via env.

---

## 5. Autenticação e autorização (RECREAR)

- **JWT Bearer HS256**, claims `sub` (username), `role`, `iat`, `exp` (8h / 480 min). Token expirado → 401 (nunca 500).
  - Laravel: **Sanctum** (recomendado, tokens de API) ou `tymon/jwt-auth` se quiser JWT de verdade. Se trocar, o front não muda (só usa `Authorization: Bearer`).
- **Senha**: Argon2id → `Hash::make()` com `HASH_ARGON2ID` (config `config/hashing.php`).
- **Guards/middleware**:
  - Autenticado: valida token **e recarrega a conta do banco a cada request** (conta removida/desativada → 401 imediato).
  - Superadmin: 403 `{"detail":"Acesso negado"}` (sem dizer o papel).
  - Proteger rotas de termos/busca já no grupo middleware; `/api/usuarios` inteiro em superadmin.
- Rate limit de login: 10/min por IP **somado** ao lockout.
- Front: token em `sessionStorage` (ou `localStorage` com "lembrar"), `/api/auth/me` para identidade, handler global de 401 → redirect login.

**Implementação Laravel sugerida**:
```php
// bootstrap/app.php ou routes/api.php
Route::middleware(['auth:api', 'rate.limit:leitura'])-&gt;group(...);
Route::middleware(['auth:api', 'role:superadmin'])-&gt;prefix('api/usuarios')-&gt;group(...);
```

---

## 6. Middleware / infraestrutura HTTP (RECREAR)

1. **CORS**: origins de `TCM_ALLOW_ORIGINS` (CSV ou JSON), credentials=true, métodos GET/POST/PUT/DELETE, headers `Authorization` e `Content-Type` → `config/cors.php` + pacote `fruitcake/laravel-cors` (nativo no Laravel 11).
2. **Security headers**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: deny`, `Referrer-Policy: no-referrer`; em produção: `Strict-Transport-Security` + `Permissions-Policy` → middleware próprio.
3. **Rate limit por rota** (slowapi → `RateLimiter` do Laravel):
   | Limite | Valor | Rotas |
   |---|---|---|
   | busca | 10/min | POST /api/buscar |
   | login | 10/min | POST /api/auth/login |
   | termos | 30/min | CRUD /api/termos, POST /api/usuarios |
   | leitura | 120/min | demais GET |
4. **Exception handlers de domínio** → `app/Exceptions/`:
   | Exceção | HTTP |
   |---|---|
   | `FonteIndisponivel` | 502 (mensagem genérica em produção) |
   | `TermoNaoEncontrado` / `UsuarioNaoEncontrado` | 404 |
   | `TermoJaExiste` / `UsuarioJaExiste` | 409 |
   | `PeriodoInvalido` | 422 |
   | `OperacaoNaoPermitida` | 403 |
   - Todos respondendo `{"detail": "..."}` para casar com o front.
5. **Boot/lifespan**: abortar se `TCM_SECRET_KEY &lt; 32 chars`; rodar migrations/seed; semear superadmin; (fechar clients HTTP no shutdown é automático).

---

## 7. Variáveis de ambiente (`.env` — recriar `.env.example`)

| Variável | Padrão | Descrição |
|---|---|---|
| `TCM_BASE_URL` | `https://egbanet.egba.ba.gov.br` | base EGBANET |
| `TCM_SUBTEMA` | `tcm` | subtema do diário |
| `TCM_USER_AGENT` | `tcm-scrapping/0.1 (...)` | UA das requisições |
| `TCM_HTTP_TIMEOUT` | `30.0` | timeout HTTP (s) |
| `TCM_HTTP_RETRIES` | `2` | retries em 5xx/erro |
| `TCM_MAX_PAGINAS` | `20` | teto de paginação por termo |
| `TCM_CONCORRENCIA_BUSCA` | `3` | paralelismo de buscas |
| `TCM_CONCORRENCIA_TCM` | `3` | paralelismo de consultas processuais |
| `TCM_CONTEXT_CHARS` | `400` | janela de contexto (±) |
| `TCM_ATOS_WINDOW_CHARS` | `1500` | janela de extração de atos |
| `TCM_CONSULTA_PROCESSO_URL` | URL TCM | endpoint da consulta |
| `TCM_CONSULTA_PROCESSO_PROXY` | vazio | proxy SOCKS só para o TCM |
| `TCM_DB_HOST/PORT/USER/PASSWORD/NAME` | MySQL | banco |
| `TCM_DB_SSL_CA` | vazio | caminho/PEM do CA (Aiven/TiDB) |
| `TCM_DB_CRIAR_BANCO` | `true` | `false` em MySQL gerenciado |
| `TCM_TERMOS_JSON` | `termos.json` | arquivo de seed |
| `TCM_SECRET_KEY` | — | **obrigatória, ≥32 chars** |
| `TCM_TOKEN_EXPIRA_MINUTOS` | `480` | expiração do token |
| `TCM_MAX_TENTATIVAS_LOGIN` | `5` | lockout |
| `TCM_BLOQUEIO_MINUTOS` | `15` | duração do bloqueio |
| `TCM_SUPERADMIN_USER` | `superadmin` | usuário seed |
| `TCM_SUPERADMIN_SENHA` | vazio | senha seed (nunca versionada) |
| `TCM_AMBIENTE` | `development` | `production` ativa HSTS e mascara erros |
| `TCM_ALLOW_ORIGINS` | localhost:3000,8000 | CSV ou JSON |
| `TCM_RATE_LIMIT_BUSCA/LOGIN/TERMOS/LEITURA` | 10/10/30/120 por minuto | rate limits |
| `TCM_FUSOHORARIO` | `America/Bahia` | "hoje" e datas |
| `PORT` | 8000 | injetada por PaaS |

Frontend: `VITE_API_BASE` (embutida no build; override via `?api=`).

---

## 8. Frontend — funcionalidades existentes

&gt; Front é **estático (Vite + TS, MPA de 4 páginas, sem framework UI)**. **Recomendação: manter como está na v1** — ele só consome a API JSON; não precisa mudar se o contrato HTTP for preservado. Depois, opcional: reescrever em Blade/Livewire/Vue (§10).

Páginas e features:
- **`index.html` (home)**: status da última edição (publicada hoje/última/erro); seletor de período (chips hoje/7/30 + intervalo livre, máx 1100 dias, datas ≤ hoje Bahia); busca com barra de progresso animada (3 etapas + aviso após 10s); strip de meta (duração, requests, avisos); resumo geral em Markdown saneado (DOMPurify + `rel=noopener`); cartões por termo com badge município/empresa; diálogo por termo (resumo, ocorrências com `&lt;mark&gt;`, processos vinculados com âncoras); processos órfãos em cartões próprios com timeline de andamento; links PDF seguros; histórico automático do cache ao carregar; estados loading/erro/vazio/parcial.
- **`login.html`**: validação Zod, "lembrar" (localStorage), redirect se autenticado.
- **`admin.html`**: abas Termos (CRUD + filtros + variações em textarea, uma por linha, diálogo de exclusão, toasts) e Contas (só superadmin, escondida para admin: filtro, ativar/desativar, redefinir senha, excluir — nunca a própria conta/superadmin, criação como admin).
- **`terms.html`**: redirect para `admin.html#termos`.
- **Compartilhado**: tema claro/escuro persistido (`integra-theme`), pill de saúde da API, logout, erros de formulário ancorados por campo, tradução dos erros FastAPI (422 → campo:mensagem).

---

## 9. Testes a recriar

### Backend (pytest atual: 70 testes) → PHPUnit/Pest
- **Fixtures/golden files**: manter `tests/fixtures/` (respostas JSON/HTML reais da EGBANET e do TCM) como base — reaproveitáveis.
- Cobertura mínima esperada no Laravel:
  - API: health, edição(s), CRUD termos, buscar, inativação, período inválido, rotas exigem token.
  - Auth: resposta mínima, mensagem única, lockout, rate limit, `/me`, 401≠500, criação de admin, papel não vem do cliente, autorizações, desativação imediata.
  - Coleta: coleta do dia, idempotência por período, vínculos, período sem publicação, falhas parciais, leitura cache, registro em `buscas`.
  - Migrações/seeds: schema, backfill, seed condicional, TLS/CA.
  - Clients EGBA/TCM: contrato com fixtures, retries, erros tipados.
  - Enrich: contexto, atos, processos, classificação.
  - Resumo: formas Markdown/JSON.
- Mock das fontes: substituir `httpx.MockTransport` por `Http::fake()` (Laravel) ou injeção de interface nos clients.

### Frontend (vitest)
- `contract.test.ts` (schemas Zod vs respostas gravadas) e `forms.test.ts` — **manter** se o front for mantido.

---

## 10. Decisões de design para o Laravel (recomendadas)

| Tema | Situação atual | Recomendação Laravel |
|---|---|---|
| **Coleta** | Síncrona no POST (até ~90s) | **v1: manter síncrona** (contrato é resposta única). v2: Job + endpoint assíncrono com polling |
| **Paralelismo** | asyncio + semáforos | `Http::pool()` do Guzzle (substitui os semáforos 3/3) |
| **Datas em VARCHAR** | ISO em strings | Migrar para colunas `DATE`/`DATETIME` (rever comparação lexicográfica de `bloqueado_ate`) |
| **JSON em TEXT** | `variacoes`, `atos`, `processos`, `andamento` | Colunas `JSON` nativas + casts Eloquent |
| **Timestamps** | `created_at VARCHAR(30)` | `timestamps()` nativos do Laravel |
| **Fuso** | `America/Bahia` hardcoded nos cálculos | `config/app.php` timezone + helper `todayInBahia()` |
| **Seed dupla** | env superadmin + termos.json | `DatabaseSeeder` condicional + comando `app:seed-superadmin` |
| **Formato 422** | FastAPI `{loc,msg}` | Middleware/exception handler que emule o formato **ou** ajustar o front |
| **Front** | Vite/TS estático | **Manter na v1**; Blade/Livewire só se houver decisão explícita |
| **Scraping frágil** | contrato não oficial | Manter clients isolados (`app/Services/Clients/`) + fixtures como golden files |
| **Proxy SOCKS** | httpx[socks] | Guzzle/cURL com `CURLOPT_PROXY` SOCKS5 ou proxy local — validar viabilidade |
| **CLI** | nenhum | Opcional: `php artisan tcm:coletar {periodo}` para testes/ops |

---

## 11. Deploy e infra

- **Atual**: Dockerfile (uv/uvicorn) → Render (free) + Aiven/TiDB/TiDB free (MySQL) + Cloudflare Pages (front) — ver `DEPLOY.md`; GitHub Actions `deploy.yml` dá SSH na VPS e roda `/usr/local/bin/integra-deploy.sh`.
- **Laravel**: reavaliar — Laravel precisa de `storage/` e `bootstrap/cache` graváveis; opções: VPS com nginx+php-fpm (mesmo pipeline SSH), Forge, ou Railway/Render com build `composer install`. O `render.yaml` e o workflow de deploy precisarão ser reescritos.

---

## 12. Checklist de implementação (ordem sugerida)

- [ ] Projeto Laravel (11/12), PHP 8.3, MySQL 8; Pint + PHPUnit/Pest.
- [ ] `.env.example` completo com todas as variáveis `TCM_*` (§7) e config/publicação delas.
- [ ] **Migrations + models** das 7 tabelas (§3), com UNIQUE/PK compostas exatas; casts JSON.
- [ ] **Seeders**: `termos.json` (condicional) + superadmin via env.
- [ ] **Exceções de domínio** + handlers respondendo `{"detail"}` com códigos da §2.2.
- [ ] **Auth**: login (mensagem única, hash-sonde, lockout 5/15), `/me`, middleware de role, token 8h.
- [ ] **Rate limiters** por rota (busca 10, login 10, termos 30, leitura 120).
- [ ] **Middleware** CORS + security headers + format 422 compatível com o front.
- [ ] **Clients**: `EgbanetClient` (3 endpoints, retry/backoff, UA) e `TcmConsultaClient` (POST+parse HTML, proxy opcional) — copiar fixtures de teste.
- [ ] **PeriodoService** (presets America/Bahia, intervalo, validações).
- [ ] **EnrichService** (normalização, contexto ±400, 5 regex de atos, processos, classificação, pdf_url) — portar testes.
- [ ] **ColetaService** (fluxo da §4.3 com `Http::pool`, idempotência, status ok/parcial/erro, log em `buscas`).
- [ ] **ResumoService** (Markdown determinístico por termo + geral).
- [ ] **Rotas + FormRequests + Resources** exatamente na tabela da §2.
- [ ] **Testes** portados da suite pytest (§9) com `Http::fake()`.
- [ ] Validar **contrato HTTP** rodando o front atual apontando para a API Laravel (`?api=`).
- [ ] Reescrever deploy (Dockerfile/comandos, `render.yaml`, workflow).
- [ ] (Opcional v2) fila/jobs para coleta assíncrona, Blade/Vue no front, comandos artisan.

---

## Apêndice — Fluxo de dados

```
POST /api/buscar {periodo|inicio,fim}
  → resolver período (America/Bahia)
  → GET EGBANET ultimas_edicoes.json → filtra período → upsert edicoes
  → para cada grafia (termo+variações, só ativos), paralelo (3):
      GET EGBANET /busca/.../query/{p}/ (até 20 páginas)
      → enrich (contexto ±400, atos ±1500, processos, tipo, pdf_url)
      → INSERT IGNORE ocorrencias + termo_processos
  → para cada processo novo, paralelo (3):
      POST TCM consulta-processual → parse HTML → upsert processos
  → INSERT buscas (log)
  → gerar resumo → BuscaOut {periodo, edicoes, ocorrencias, processos, resumo, meta}

GET /api/ocorrencias|resumo|processos → mesmos dados lidos do MySQL (meta.cache=true)
```
