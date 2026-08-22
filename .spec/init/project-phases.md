# Prancheta de System Design — Project Phases

<!-- inputs: project-description.md@sha256:bed6595e6025 user-stories.md@sha256:9a633e6f2576 database-schema.md@sha256:ebd55326af55 -->

## Overview

O repositório está **vazio de código** — só há `LICENSE`, `README.md` e os artefatos de design. Nenhuma tarefa nasce marcada; o plano parte do `laravel new` e vai até a paridade completa com o protótipo. São **20 fases**, ordenadas fundação-primeiro: ambiente e starter kit (F1), banco e models relationship-completos (F2–F3), catálogo seedado (F4–F5), base de frontend com os tokens do protótipo (F6), autenticação e isolamento por usuário (F7), backend de sessões (F8). Só a partir da **F9** começam os fluxos de produto.

Da F9 em diante o build é em **fatia vertical**: cada fase leva o pedaço do motor TypeScript e a UI Vue que o consome, fechada por Vitest no motor e Pest nas rotas. O **autosave entra cedo (F10)**, logo depois do primeiro estado de canvas existir, para que tudo o que vier depois já nasça persistindo. O **MVP é a F20 inteira** — paridade total, incluindo perfil, notas, export SVG e gerenciamento de sessões; nenhuma história fica para depois.

O contrato de API vive em `routes/web.php` sob o prefixo `/api` com o guard `web` (cookie de sessão, CSRF do Inertia) — sem Sanctum. Além das 32 histórias, entram no v1 quatro comportamentos que só o protótipo descreve: **tema claro/escuro**, **zerar o cronômetro**, **prancheta livre sem problema** e **toasts de aviso**.

**Conventions:**
- `[ ]` pending · `[x]` done in the codebase.
- Phases and sub-phases are numbered (`Phase 1`, `Phase 5.3`) for reference by AI agents.
- Business-logic tasks list the **feature tests** to generate; frontend-only tasks list validatable acceptance conditions and a **Design ref**.
- Design ref padrão: `.spec/init/design/pranchetasystemdesign.html` é o **contrato de aparência e comportamento** (protótipo congelado, 1571 linhas, autocontido). `z1.png` detalha blocos/chips/selos, `z2.png` o menu de numeração, `out3.png` o export SVG com legenda.
- Todo identificador de banco em `snake_case`; convenções Eloquent do Laravel 13.

---

## Phase 1: Fundação — projeto, ambiente e ferramentas

**Goal:** Sair do repositório vazio para uma aplicação Laravel 13 + Inertia 3 + Vue 3 + TS que sobe, com toda a cadeia de qualidade configurada. · **Depends on:** nenhuma · **Covers:** Tech Stack do project-description

### Phase 1.1: Bootstrap do starter kit

- [ ] **Task:** Instalar o Laravel 13 com o starter kit oficial (Inertia + Vue 3 + TypeScript) na raiz do repositório, preservando `LICENSE`, `README.md` e `.spec/`.
  - **Versão do Inertia (decisão fechada):** este plano foi escrito quando o starter kit oficial ainda entregava Inertia 2; o starter kit do Laravel 13 entrega **Inertia 3** (`@inertiajs/vue3` ^3.0, `inertiajs/inertia-laravel` ^3.0), com layout raiz em sintaxe v3 (`<x-inertia::app />`, `<x-inertia::head>`) e o plugin `@inertiajs/vite`. Vale o que o starter kit entrega: **não faça downgrade** para Inertia 2 — isso significaria reescrever à mão o bootstrap do kit, sem ganho de produto.
  - **Execução não-interativa (obrigatória):** a sessão roda sem TTY — o installer do Laravel e o Composer **não podem** abrir prompt. Toda escolha do installer (starter kit, framework de teste, banco) vira flag; nunca responda a um prompt.
    - **Antes de instalar**, rode `laravel new --help` e leia as flags reais da versão instalada — não confie de memória nos nomes. Traduza as decisões já fechadas em flags: starter kit **Vue + Inertia**, testes **Pest**, banco **MySQL**, mais `--no-interaction`.
    - Instale em diretório temporário e mova o conteúdo (inclusive dotfiles) para a raiz: o installer exige diretório vazio e a raiz já tem `LICENSE`, `README.md`, `.spec/` e `scripts/`, que **não** podem ser sobrescritos nem apagados.

    ```bash
    export COMPOSER_ALLOW_SUPERUSER=1
    composer global require laravel/installer --no-interaction   # se o binario `laravel` nao existir
    laravel new maxdraw-skeleton --no-interaction <flags-conferidas-no---help>
    ```

    - Composer sempre com `--no-interaction` e `COMPOSER_ALLOW_SUPERUSER=1` (a sessão roda como root).
    - Se algum comando ainda assim pedir entrada, ele está errado: acrescente a flag que resolve a pergunta em vez de responder.
  - **Acceptance criteria:**
    - `composer.json` declara `laravel/framework` ^13.0 e PHP ^8.4; `php artisan --version` responde 13.x.
    - `package.json` traz `@inertiajs/vue3` 3.x (a versão do starter kit oficial do Laravel 13), `vue` 3.x, `typescript`, `vite` e `tailwindcss` 4.x.
    - `npm run build` e `npm run dev` completam sem erro; `php artisan serve` responde 200 na raiz.
    - `resources/js/app.ts` (não `.js`) é o entrypoint Inertia; `tsconfig.json` presente com `strict: true`.
    - `.gitignore` cobre `vendor/`, `node_modules/`, `.env`, `public/build/` — e **não** ignora `.spec/`.
    - `LICENSE`, `README.md`, `.spec/` e `scripts/ralph.sh` continuam intactos após o bootstrap.
    - Nenhum comando da task ficou bloqueado esperando entrada do usuário.
  - **Traces:** project-description → Tech Stack

- [ ] **Task:** Configurar as fontes do Google (Archivo para UI, IBM Plex Mono para dados/selos/cronômetro, Source Serif 4 para o enunciado) no layout raiz do Inertia.
  - **Acceptance criteria:**
    - As três famílias carregam via `<link>` para `fonts.googleapis.com` com `preconnect` para `fonts.gstatic.com`.
    - Cada família tem pilha de fallback: Archivo → `-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`; IBM Plex Mono → `ui-monospace, SFMono-Regular, Menlo, monospace`; Source Serif 4 → `Georgia, "Times New Roman", serif`.
    - Os pesos carregados cobrem o que o protótipo usa (regular e 600 para UI e mono).
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (bloco `:root`, tokens `--ui`, `--mono`, `--serif`)
  - **Traces:** project-description → Tech Stack (Fontes)

### Phase 1.2: Ambiente e banco

- [ ] **Task:** Configurar MySQL 8 como banco de desenvolvimento e SQLite em memória para os testes.
  - **Acceptance criteria:**
    - `.env.example` traz `DB_CONNECTION=mysql` com host/porta/base do dia a dia; `.env` local aponta para o MySQL da VPS.
    - `phpunit.xml` força `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:` na suíte.
    - `php artisan migrate` roda no MySQL e `php artisan test` roda no SQLite, ambos verdes com o schema vazio.
    - Nenhuma migration usa recurso indisponível no SQLite (sem `ENUM`, sem `AFTER`, sem tipo espacial).
  - **Traces:** project-description → Tech Stack (Banco dev/prod, Banco de testes)

- [ ] **Task:** Versionar o ambiente containerizado com Docker Compose / Laravel Sail para quem clonar o projeto público.
  - **Acceptance criteria:**
    - `docker-compose.yml` versionado sobe app PHP 8.4 e MySQL 8.0 com volume persistente.
    - `./vendor/bin/sail up -d` seguido de `sail artisan migrate` funciona em máquina limpa.
    - O `README.md` ganha as duas trilhas: Sail (clone público) e nativa (`php artisan serve` + `npm run dev`, sem container).
    - O compose não é exigido pelo fluxo do dia a dia — a aplicação sobe sem Docker.
  - **Traces:** project-description → Tech Stack (Ambiente containerizado, Ambiente do dia a dia)

### Phase 1.3: Ferramentas de qualidade

- [ ] **Task:** Instalar e configurar Pest (backend) e Vitest (frontend) com a estrutura de diretórios de teste.
  - **Acceptance criteria:**
    - `tests/Feature/` e `tests/Unit/` existem com `Pest.php` configurado e `RefreshDatabase` disponível.
    - `vitest.config.ts` aponta para `resources/js/canvas/**/*.test.ts` e roda em ambiente `node` (o motor não depende de DOM).
    - `composer test` e `npm run test` executam as duas suítes; ambas verdes vazias.
  - **Traces:** project-description → Tech Stack (Testes backend, Testes frontend)

- [ ] **Task:** Configurar Laravel Pint, ESLint + Prettier e Larastan/PHPStan.
  - **Acceptance criteria:**
    - `pint.json` no preset `laravel`; `./vendor/bin/pint --test` verde.
    - ESLint + Prettier cobrindo `.ts` e `.vue`; `npm run lint` verde.
    - `phpstan.neon` com Larastan habilitado; `./vendor/bin/phpstan analyse` verde no nível inicial acordado.
    - O nível inicial e a meta de subida ficam registrados no `phpstan.neon` em comentário — ver Open Questions.
  - **Traces:** project-description → Tech Stack (Análise estática, Formatação) · Open Question "Nível do PHPStan/Larastan"

- [ ] **Task:** Instalar as guidelines do Laravel Boost para os agentes que implementarão as fases seguintes.
  - **Execução não-interativa (obrigatória):** `php artisan boost:install` é um comando de prompts. Sem TTY o Laravel Prompts devolve o *default* de cada `multiselect` em vez de perguntar — mas o prompt "Which AI agents…" é `required`, e se nenhum agente for detectado ele **aborta** com `NonInteractiveValidationException: Required.`. Escreva o `boost.json` **antes** do install para fixar as escolhas e tornar a detecção irrelevante:

    ```bash
    export COMPOSER_ALLOW_SUPERUSER=1
    composer require laravel/boost --dev --no-interaction

    echo '{"agents":["claude_code"],"guidelines":true,"mcp":true,"skills":[]}' > boost.json

    php artisan boost:install --no-interaction
    ```

    - Rode `boost:install` **no host**, nunca via `sail artisan`: a detecção de agente usa `command -v claude`, que falha dentro do container.
    - O Boost anexa um bloco `<laravel-boost-guidelines>…</laravel-boost-guidelines>` ao `CLAUDE.md` preservando o resto do arquivo. Se o `CLAUDE.md` for regenerado depois por outra ferramenta, re-rode `php artisan boost:install --no-interaction` para repor o bloco.
  - **Acceptance criteria:**
    - `laravel/boost` aparece em `require-dev` do `composer.json` e em `composer.lock`.
    - `boost.json` versionado na raiz, com `"agents": ["claude_code"]` e `"guidelines": true`.
    - `CLAUDE.md` contém o bloco `<laravel-boost-guidelines>` fechado por `</laravel-boost-guidelines>`, e o conteúdo que já existia no arquivo continua presente.
    - `.mcp.json` declara o server `laravel-boost` (`php artisan boost:mcp`) e `php artisan boost:mcp --help` responde sem erro.
    - O comando terminou com exit code 0, sem nenhum prompt pendente.
    - Nenhuma guideline conflita com as decisões já fechadas em `.spec/` (lookups no lugar de enum, sem soft delete).
  - **Traces:** project-description → Tech Stack (Guidelines para agentes)

---

## Phase 2: Fundação — migrations

**Goal:** Materializar as 14 tabelas do `database-schema.md`, lookups primeiro, sem nenhuma coluna enum. · **Depends on:** Phase 1 · **Covers:** todas as tabelas do schema

### Phase 2.1: Lookups do catálogo

- [ ] **Task:** Criar as migrations dos sete lookups: `problem_levels`, `problem_item_types`, `component_categories`, `link_types`, `sequence_modes`, `session_durations`, `estimate_modes`.
  - **Acceptance criteria:**
    - Cada tabela tem `id` bigint auto-incremento e `created_at`/`updated_at`.
    - `problem_levels`: `name`, `slug` unique, `position`, `is_active` default true.
    - `problem_item_types`: `name`, `slug` unique, `description` nullable, `is_active`.
    - `component_categories`: `name`, `slug` unique, `color_token` unique, `position`, `is_active`.
    - `link_types`: `name`, `slug` unique (20), `badge_label` (12), `dash_array` nullable (20), `is_bidirectional_default` bool default false, `gloss` (255) not null, `position`, `is_active`.
    - `sequence_modes`: `name`, `slug` unique (10), `legend_text` (255), `position`, `is_active`.
    - `session_durations`: `minutes` smallint unique, `is_default` bool default false, `position`, `is_active`.
    - `estimate_modes`: `name`, `slug` unique (20), `highlighted_row` (60), `position`, `is_active`.
    - Nenhuma coluna do tipo `enum` em nenhuma das sete tabelas.
  - **Feature tests:** `migrations_create_lookup_tables` → após `migrate`, o Schema tem as sete tabelas com as colunas e uniques declaradas, e `Schema::getColumnType()` nunca devolve `enum`.
  - **Traces:** database-schema → `problem_levels`, `problem_item_types`, `component_categories`, `link_types`, `sequence_modes`, `session_durations`, `estimate_modes`

### Phase 2.2: Tabelas de catálogo

- [ ] **Task:** Criar as migrations de `problems` e `problem_items`.
  - **Acceptance criteria:**
    - `problems`: `slug` unique (40), `name` (120), `tag` (80), `problem_level_id` FK not null, `context` text, `position`, `is_active`, timestamps; índice composto `(problem_level_id, position)`.
    - `problem_items`: `problem_id` FK `on delete cascade`, `problem_item_type_id` FK, `position` smallint, `content` text, timestamps.
    - `problem_items` tem unique composto `(problem_id, problem_item_type_id, position)`.
    - Apagar um `problems` apaga seus `problem_items` em cascata.
  - **Feature tests:** `problem_items_cascade_on_problem_delete` → apagar um problema remove seus itens; `problem_items_position_is_unique_per_type` → inserir duas linhas com o mesmo trio (problema, tipo, posição) viola a constraint.
  - **Traces:** database-schema → `problems`, `problem_items` · US-2.1

- [ ] **Task:** Criar as migrations de `component_categories` → `components`, e de `phases` → `checklist_items`.
  - **Acceptance criteria:**
    - `components`: `component_category_id` FK not null, `slug` unique (40), `name` (80), `short_name` (60), `icon_key` (40), `position`, `is_active`, timestamps; índice `(component_category_id, position)`.
    - `phases`: `slug` unique, `name` (120), `weight` decimal(4,3) not null, `position` smallint unique, `is_active`, timestamps.
    - `checklist_items`: `phase_id` FK `on delete cascade`, `position` smallint, `content` (255), `is_active`, timestamps; unique `(phase_id, position)`.
    - Apagar uma `phases` apaga seus `checklist_items` em cascata.
  - **Feature tests:** `checklist_items_cascade_on_phase_delete` → apagar a fase remove seus itens; `phase_position_is_unique` → duas fases com a mesma posição violam a constraint.
  - **Traces:** database-schema → `components`, `component_categories`, `phases`, `checklist_items` · US-3.1, US-6.2

### Phase 2.3: Usuário e sessão de treino

- [ ] **Task:** Ajustar a migration de `users` do starter kit e criar a de `training_sessions`.
  - **Acceptance criteria:**
    - `users` mantém `name`, `email` unique, `email_verified_at` nullable, `password`, `remember_token`, timestamps — **sem** `deleted_at`.
    - A tabela de treino chama-se `training_sessions`, nunca `sessions` (que pertence ao driver de sessão do Laravel).
    - `training_sessions`: `user_id` FK `on delete cascade`; `problem_id` FK nullable `on delete restrict`; `session_duration_id` FK not null; `sequence_mode_id` FK not null; `elapsed_seconds` int default 0; `notes` text nullable; `nodes`, `edges`, `checks`, `estimate` json not null; `last_opened_at` timestamp not null; timestamps.
    - Índices `(user_id, last_opened_at)` e `(user_id, created_at)`.
    - Nenhuma tabela do schema usa `deleted_at`.
  - **Feature tests:** `training_sessions_cascade_on_user_delete` → apagar o usuário apaga suas sessões; `problem_delete_is_restricted_while_referenced` → apagar um problema referenciado por sessão falha; `no_soft_deletes_anywhere` → nenhuma das 14 tabelas tem coluna `deleted_at`.
  - **Traces:** database-schema → `users`, `training_sessions` · US-1.3, US-1.4

- [ ] **Task:** Publicar as migrations de infraestrutura do Laravel (driver de sessão, cache, filas, tokens de reset).
  - **Acceptance criteria:**
    - `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` e `password_reset_tokens` criadas pelas migrations padrão.
    - A tabela `sessions` do driver e a `training_sessions` do domínio coexistem sem colisão.
    - O fluxo de recuperação de senha **não** é exposto em rota nenhuma no v1 — só a tabela existe.
  - **Traces:** database-schema → Notes & Conventions (tabelas de infraestrutura) · user-stories → Open Question "Recuperação de senha"

---

## Phase 3: Fundação — models, relacionamentos e factories

**Goal:** Todos os models saem desta fase **relationship-completos**, com casts e fillables; nenhum relacionamento é adiado para fase de feature. · **Depends on:** Phase 2 · **Covers:** as 14 tabelas do schema

### Phase 3.1: Models de lookup

- [ ] **Task:** Criar os models dos sete lookups com seus relacionamentos inversos.
  - **Acceptance criteria:**
    - `ProblemLevel` hasMany `problems`; `ProblemItemType` hasMany `problem_items`; `ComponentCategory` hasMany `components`.
    - `LinkType`, `SequenceMode`, `SessionDuration`, `EstimateMode` criados; `SequenceMode` e `SessionDuration` têm hasMany para `training_sessions`.
    - `is_active` com cast `boolean`; `position` e `minutes` com cast `integer`; `is_bidirectional_default` com cast `boolean`.
    - Cada model expõe um scope `active()` filtrando `is_active = true`, e ordenação padrão por `position` onde a coluna existe.
    - Nenhum model de lookup é gravável pela aplicação em runtime — só pelo seeder.
  - **Feature tests:** `lookup_active_scope_filters_inactive` → o scope devolve só ativos; `session_duration_default_is_unique` → existe exatamente uma duração com `is_default = true`.
  - **Traces:** database-schema → `problem_levels`, `problem_item_types`, `component_categories`, `link_types`, `sequence_modes`, `session_durations`, `estimate_modes`

### Phase 3.2: Models de catálogo

- [ ] **Task:** Criar `Problem`, `ProblemItem`, `Component`, `Phase` e `ChecklistItem` com todos os relacionamentos e acessores de conveniência.
  - **Acceptance criteria:**
    - `Problem` belongsTo `ProblemLevel`, hasMany `ProblemItem`, hasMany `TrainingSession`; expõe `requirements()`, `scaleTargets()` e `topics()` como hasMany filtrados pelo slug do tipo, ordenados por `position`.
    - `ProblemItem` belongsTo `Problem` e `ProblemItemType`.
    - `Component` belongsTo `ComponentCategory`; scope ordenado por `(component_category_id, position)`.
    - `Phase` hasMany `ChecklistItem` ordenados por `position`; `weight` com cast `decimal:3`.
    - `ChecklistItem` belongsTo `Phase`.
    - Nenhum acesso a `problem_items` sem `orderBy('position')` — a ordem é conteúdo, não acaso.
  - **Feature tests:** `problem_exposes_three_ordered_lists` → um problema com itens dos três tipos devolve cada lista separada e na ordem de `position`; `phase_weights_sum_to_one` → a soma de `weight` das cinco fases é exatamente 1.000.
  - **Traces:** database-schema → `problems`, `problem_items`, `components`, `phases`, `checklist_items` · US-2.1, US-3.1, US-6.1, US-6.2

### Phase 3.3: User, TrainingSession e factories

- [ ] **Task:** Criar o model `TrainingSession` com casts JSON e ajustar `User`.
  - **Acceptance criteria:**
    - `TrainingSession` belongsTo `User`, `Problem` (nullable), `SessionDuration`, `SequenceMode`.
    - `nodes`, `edges` e `checks` com cast `array`; `estimate` com cast `array`; `last_opened_at` com cast `datetime`; `elapsed_seconds` com cast `integer`.
    - `User` hasMany `TrainingSession`; a exclusão do usuário remove as sessões (cascade no banco).
    - `TrainingSession` expõe um accessor `duration_minutes` que lê `sessionDuration->minutes`, para o payload da API falar minutos.
    - Nenhum model do projeto usa a trait `SoftDeletes`.
  - **Feature tests:** `training_session_casts_json_columns_to_array` → gravar e reler devolve arrays PHP, não strings; `duration_minutes_accessor_reads_lookup` → o accessor devolve 45 para a duração padrão; `deleting_user_deletes_their_sessions` → US-1.3.
  - **Traces:** database-schema → `training_sessions`, `users` · US-1.3, US-2.2, US-4.3

- [ ] **Task:** Criar factories para `User` e `TrainingSession`, e estados de apoio para os testes das fases seguintes.
  - **Acceptance criteria:**
    - `UserFactory` do starter kit funcional com senha hasheada.
    - `TrainingSessionFactory` produz sessão válida: `nodes: []`, `edges: []`, `checks: {}`, `estimate` com os padrões do modo por usuários, duração 45, modo `out`, `elapsed_seconds` 0, `last_opened_at` agora.
    - Estados `withProblem()`, `withDiagram(int $nodes, int $edges)` e `atLimit()` (200 nós / 400 arestas) disponíveis.
    - A factory resolve os lookups por slug, não por id fixo — não quebra se o seeder mudar a ordem.
  - **Feature tests:** `training_session_factory_produces_valid_session` → a sessão criada passa pelas mesmas regras de validação do autosave.
  - **Traces:** database-schema → `training_sessions` · US-11.2

---

## Phase 4: Fundação — seeder do catálogo estrutural

**Goal:** Popular os lookups, as 6 categorias, os 28 componentes, as 5 fases e os 25 itens de checklist com o conteúdo literal do protótipo. · **Depends on:** Phase 3 · **Covers:** o Key Concept "Catálogo"

### Phase 4.1: Seeder dos lookups

- [ ] **Task:** Criar o `CatalogSeeder` versionado e idempotente, raiz de todo o seeding do catálogo.
  - **Acceptance criteria:**
    - `DatabaseSeeder` chama `CatalogSeeder`, que orquestra os seeders filhos na ordem de dependência.
    - Rodar o seeder duas vezes seguidas não duplica linha nenhuma (upsert por `slug`, ou por `minutes` em `session_durations`).
    - O seeder não depende de ids fixos — resolve tudo por slug.
    - Editar o catálogo é editar o seeder: nenhuma rota ou tela da aplicação escreve em tabela de catálogo.
  - **Feature tests:** `catalog_seeder_is_idempotent` → rodar duas vezes mantém as contagens; `catalog_tables_are_read_only_at_runtime` → nenhuma rota autenticada consegue criar/alterar linha de catálogo.
  - **Traces:** project-description → Key Concept "Catálogo"

- [ ] **Task:** Seedar os sete lookups com os valores fixados no `database-schema.md`.
  - **Acceptance criteria:**
    - `problem_levels`: 3 linhas — `base` Base (1), `intermediate` Intermediário (2), `advanced` Avançado (3).
    - `problem_item_types`: 3 linhas — `requirement`, `scale`, `topic`.
    - `sequence_modes`: 3 linhas — `out` (position 1, padrão), `flow` (2), `off` (3), cada uma com o `legend_text` do protótipo.
    - `session_durations`: 3 linhas — 30, 45 (`is_default = true`), 60.
    - `estimate_modes`: 2 linhas — `user` destacando "Escritas por dia", `month` destacando "Escritas por mês".
    - `link_types`: 9 linhas na ordem do protótipo, com `badge_label`, `dash_array` (null para http/grpc/ws/query/cache; `5 4.5` para event/repl/batch; `2 4.5` para retry), `is_bidirectional_default` true só em `ws`, e a `gloss` literal de cada tipo.
  - **Feature tests:** `link_types_seed_matches_prototype` → os 9 slugs, selos, `dash_array` e a bidirecionalidade do `ws` batem com o protótipo; `exactly_one_default_duration` → só 45 tem `is_default`; `sequence_mode_out_is_first` → `out` tem `position = 1`.
  - **Traces:** database-schema → Lookup Table Seeds · US-4.1, US-4.3, US-2.3, US-7.2

### Phase 4.2: Categorias e componentes

- [ ] **Task:** Seedar as 6 `component_categories` com seus tokens de cor e os 28 `components` com nome longo, nome curto e ícone.
  - **Acceptance criteria:**
    - Categorias, na ordem da paleta: `client` Cliente `--c-client`, `edge` Rede & Borda `--c-edge`, `compute` Computação `--c-compute`, `data` Dados `--c-data`, `async` Assíncrono `--c-async`, `ops` Operação `--c-ops`.
    - 28 componentes distribuídos 3 / 5 / 7 / 7 / 3 / 3 nessas categorias, na ordem exata do protótipo.
    - `short_name` difere de `name` apenas onde o protótipo define forma curta (`DLQ — fila de falhas` → `DLQ`); nos demais, `short_name` repete `name`.
    - `icon_key` de cada componente casa com uma chave existente no mapa de ícones do motor do canvas.
    - `color_token` é único por categoria e é a **única** fonte de cor do sistema — nenhuma outra tabela tem coluna de cor.
  - **Feature tests:** `seeds_twenty_eight_components_in_six_categories` → contagem total e por categoria; `dlq_has_distinct_short_name` → `short_name` é `DLQ`; `every_component_icon_key_exists_in_engine` → todo `icon_key` tem ícone correspondente.
  - **Traces:** database-schema → `components`, `component_categories` · US-3.1 · project-description → Key Concepts "Componente", "Categoria"

### Phase 4.3: Fases e checklist

- [ ] **Task:** Seedar as 5 `phases` com seus pesos e os 25 `checklist_items` com o texto literal do protótipo.
  - **Acceptance criteria:**
    - Fases na ordem: Requisitos & escopo 0.110, Estimativas de capacidade 0.110, API & modelo de dados 0.180, Desenho de alto nível 0.270, Escala & trade-offs 0.330.
    - A soma dos pesos é exatamente 1.000.
    - Itens distribuídos 4 / 5 / 5 / 5 / 6, totalizando 25, com `position` sequencial dentro de cada fase.
    - O texto de cada item é o do protótipo, sem reescrita.
  - **Feature tests:** `seeds_five_phases_with_weights_summing_to_one`; `seeds_twenty_five_checklist_items_distributed_4_5_5_5_6` → contagem total e por fase.
  - **Traces:** database-schema → `phases`, `checklist_items` · US-6.1, US-6.2

---

## Phase 5: Fundação — seeder dos 14 problemas

**Goal:** Carregar os 14 enunciados com contexto, requisitos funcionais, escala alvo e tópicos do gabarito. · **Depends on:** Phase 4 · **Covers:** o Key Concept "Problema", US-2.1, US-10.1

### Phase 5.1: Infraestrutura do seeder de problemas

- [ ] **Task:** Criar o `ProblemSeeder` que lê os 14 problemas de uma fonte versionada e grava `problems` + `problem_items`.
  - **Acceptance criteria:**
    - O conteúdo dos problemas vive em arquivo(s) versionado(s) sob `database/seeders/data/`, não embutido em string gigante no seeder.
    - Cada problema grava suas três listas em `problem_items` com o `problem_item_type_id` certo e `position` na ordem original.
    - O seeder é idempotente: reexecutar substitui os itens do problema sem duplicar nem deixar órfãos.
    - `problem_level_id` é resolvido pelo slug do nível (`lv` 1→base, 2→intermediate, 3→advanced).
  - **Feature tests:** `problem_seeder_is_idempotent` → rodar duas vezes mantém a contagem de `problem_items`; `problem_items_keep_source_order` → a ordem das listas sobrevive ao round-trip.
  - **Traces:** database-schema → `problems`, `problem_items`, `problem_levels`, `problem_item_types`

### Phase 5.2: Conteúdo dos 14 problemas

- [ ] **Task:** Transcrever os 14 problemas do protótipo — slug, nome, tag, nível, contexto e as três listas.
  - **Acceptance criteria:**
    - Os 14 slugs, na ordem do protótipo: `url`, `feed`, `chat`, `video`, `ride`, `drive`, `rate`, `notif`, `typeahead`, `tickets`, `crawler`, `metrics`, `pay`, `leaderboard`.
    - Cada um traz `tag` (ex.: `Chave-valor · Cache`), nível, `context` em prosa e as três listas completas, sem resumir nem parafrasear o texto original.
    - Nenhum problema fica com lista vazia: todo problema tem ≥1 requisito, ≥1 item de escala e ≥1 tópico.
    - Os tópicos (`topic`) são o gabarito — nunca são enviados em resposta que a tela use antes do fim do treino sem estar no bloco colapsado.
  - **Feature tests:** `seeds_fourteen_problems_with_expected_slugs`; `every_problem_has_all_three_lists_non_empty`; `problem_levels_distribution_matches_prototype` → a contagem por nível bate com o protótipo.
  - **Traces:** database-schema → `problems`, `problem_items` · US-2.1, US-10.1 · project-description → Key Concept "Problema"

### Phase 5.3: Integridade do catálogo completo

- [ ] **Task:** Escrever o teste de integridade que trava o catálogo inteiro contra regressão do seeder.
  - **Acceptance criteria:**
    - Um único teste roda `db:seed` do zero e afirma todas as contagens de uma vez: 14 problemas, 28 componentes, 6 categorias, 9 tipos de ligação, 5 fases, 25 itens de checklist, 3 níveis, 3 tipos de item, 3 modos de sequência, 3 durações, 2 modos de estimativa.
    - O teste falha com mensagem legível dizendo qual contagem divergiu.
    - Nenhuma FK do catálogo aponta para linha inexistente após o seed.
  - **Feature tests:** `catalog_seed_produces_expected_counts` → as onze contagens acima; `catalog_has_no_dangling_foreign_keys` → toda FK de catálogo resolve.
  - **Traces:** database-schema → Lookup Table Seeds · project-description → Key Concept "Catálogo"

---

## Phase 6: Fundação — base de frontend

**Goal:** Portar os tokens do protótipo para o Tailwind 4, montar o shell da prancheta e os componentes compartilhados que todas as fases seguintes consomem. · **Depends on:** Phase 1 · **Covers:** Tech Stack (Estilo), tema claro/escuro, toasts

### Phase 6.1: Tokens, tema claro/escuro e tipografia

- [ ] **Task:** Portar a paleta completa do protótipo para tokens CSS do Tailwind 4, nos dois temas.
  - **Acceptance criteria:**
    - Todos os tokens de `:root` do protótipo existem: `--paper`, `--panel`, `--panel-2`, `--panel-3`, `--ink`, `--ink-2`, `--ink-3`, `--line`, `--line-2`, `--grid`, `--accent`, `--accent-2`, `--accent-ink`, `--accent-soft`, `--ok`, `--warn`, `--crit`, as seis `--c-*`, as duas `--shadow-*` e `--r`.
    - Tema claro definido em `:root` puro; tema escuro redefinido tanto em `@media (prefers-color-scheme: dark)` sob `:root:not([data-theme="light"])` quanto em `:root[data-theme="dark"]`, exatamente como o protótipo.
    - As seis cores de categoria têm valores distintos nos dois temas e batem com os hex do protótipo.
    - Nenhuma cor é definida apenas dentro de um bloco de media query.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (blocos `:root`, `@media (prefers-color-scheme: dark)`, `:root[data-theme="dark"]`)
  - **Traces:** project-description → Tech Stack (Estilo) · Key Concept "Categoria"

- [ ] **Task:** Implementar o alternador de tema com persistência no navegador e reação à preferência do sistema.
  - **Acceptance criteria:**
    - O botão de tema cicla: sem preferência → oposto do sistema → o outro → e assim por diante, gravando em `localStorage['sd-theme']`.
    - Sem valor gravado, o tema segue `prefers-color-scheme`; mudança na preferência do sistema é refletida sem recarregar.
    - Ao trocar o tema, as variáveis CSS são relidas e o SVG das arestas é redesenhado — as setas nunca ficam com a cor do tema anterior.
    - A preferência de tema é do navegador, não da sessão: não vai para o servidor.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`setTheme`, `readVars`, `#btnTheme`)
  - **Traces:** project-description → Tech Stack (tema claro/escuro) · escopo v1 confirmado pelo desenvolvedor

### Phase 6.2: Shell da prancheta

- [ ] **Task:** Montar o layout base da aplicação: barra superior, trilho de componentes à esquerda, palco central e painel de treino à direita.
  - **Acceptance criteria:**
    - A barra superior tem: seletor de problema (com o nome do problema corrente), botão Sessões, espaçador, indicador de salvamento, botão de tema, botão SVG e botão Salvar.
    - O trilho esquerdo tem cabeçalho "Componentes", a dica de uso e a área da paleta.
    - O palco central contém grade, mundo transformável (SVG de arestas + camada de nós + camada de rótulos), estado vazio, barra de zoom e o painel de legenda.
    - O painel direito tem o relógio, as quatro abas (Roteiro, Enunciado, Estimativas, Notas) e os painéis correspondentes.
    - O layout ocupa a viewport inteira sem rolagem horizontal do body; cada painel rola internamente.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (markup do `<body>`: `#rail`, `#stage`, `#drill`, `#zoombar`, `#legend`, `#tabs`)
  - **Traces:** project-description → Core Workflows 1–7

- [ ] **Task:** Implementar a navegação por abas do painel de treino.
  - **Acceptance criteria:**
    - Clicar numa aba ativa o painel correspondente e desativa os demais; a aba ativa recebe destaque visual.
    - "Roteiro" é a aba ativa ao carregar.
    - Trocar de aba não perde o estado dos painéis (texto digitado nas notas, campos da calculadora, seção de fase aberta).
    - As abas são navegáveis por teclado.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#tabs`, `.pane`)
  - **Traces:** project-description → Core Workflows 6, 7

### Phase 6.3: Componentes compartilhados

- [ ] **Task:** Construir os componentes reutilizáveis: botão, chip de estado, folha modal com overlay e o toast de aviso.
  - **Acceptance criteria:**
    - Botão com as variações do protótipo: padrão, `primary` e `icon`.
    - Folha modal (`overlay` + `sheet`) abre e fecha por clique fora e por `Esc`, e devolve o foco ao gatilho ao fechar.
    - Toast exibe mensagem temporária por ~2600 ms, empilha sem quebrar o layout e é acessível a leitores de tela (`role="status"`).
    - O toast é o canal único dos avisos previstos nas histórias: limite de nós/arestas atingido, export com canvas vazio e versão do servidor mais nova.
    - O chip de estado aceita os três estados do indicador de salvamento e é reutilizado pela Phase 10.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`.btn`, `#ov`/`#sheet`, `#toast`, `.savechip`)
  - **Traces:** US-3.1, US-3.3, US-8.2, US-8.3, US-9.1 · escopo v1 confirmado pelo desenvolvedor (toasts)

---

## Phase 7: Autenticação e isolamento por usuário

**Goal:** Registro, login, perfil e a garantia de que nenhum caminho da aplicação expõe treino de terceiro. · **Depends on:** Phase 3, Phase 6 · **Covers:** US-1.1, US-1.2, US-1.3, US-1.4

### Phase 7.1: Registro, login e logout

- [ ] **Task:** Habilitar o registro do starter kit com nome, e-mail e senha confirmada, sem verificação de e-mail obrigatória.
  - **Acceptance criteria:**
    - O formulário pede nome, e-mail, senha e confirmação de senha.
    - E-mail inválido ou já cadastrado devolve erro de validação **no campo**, sem criar registro duplicado e sem revelar a senha.
    - Senha exige mínimo de 8 caracteres e precisa bater com a confirmação.
    - A senha é gravada com hash; a coluna `password` nunca contém texto plano.
    - Registro bem-sucedido autentica a pessoa e redireciona direto para a prancheta.
    - Nenhuma rota exige e-mail verificado no v1.
  - **Feature tests:** `registration_creates_hashed_user_and_authenticates` → usuário criado, `Hash::check` verdadeiro, sessão autenticada; `registration_rejects_duplicate_email` → 422/erro no campo e contagem de usuários inalterada; `registration_requires_password_confirmation` → senha sem confirmação é rejeitada; `registration_does_not_require_email_verification` → a rota da prancheta responde 200 logo após registrar.
  - **Traces:** US-1.1

- [ ] **Task:** Habilitar login, "lembrar de mim", logout e o throttle de tentativas.
  - **Acceptance criteria:**
    - Credenciais corretas autenticam e levam à sessão corrente do usuário.
    - Credenciais erradas devolvem mensagem genérica de credenciais inválidas, sem distinguir e-mail inexistente de senha errada.
    - Tentativas repetidas são limitadas pelo throttle padrão do Laravel.
    - "Lembrar de mim" mantém a autenticação entre visitas quando marcado.
    - Logout encerra a sessão, invalida o token e devolve ao login.
    - Rota de treino acessada sem autenticação redireciona para o login.
  - **Feature tests:** `login_with_valid_credentials_authenticates`; `login_error_message_is_generic_for_unknown_email_and_wrong_password` → a mesma mensagem nos dois casos; `login_is_throttled_after_repeated_failures`; `remember_me_sets_recaller_cookie`; `logout_invalidates_session`; `guest_is_redirected_from_board_route`.
  - **Traces:** US-1.2

- [ ] **Task:** Construir as telas de registro e login com os tokens e a tipografia da prancheta.
  - **Acceptance criteria:**
    - As duas telas usam os tokens de cor, as fontes e os componentes de botão/campo definidos na Phase 6.
    - Erros de validação aparecem junto ao campo correspondente.
    - As telas funcionam nos temas claro e escuro.
    - Há navegação recíproca entre registro e login.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (tokens, `.btn`, tipografia)
  - **Traces:** US-1.1, US-1.2

### Phase 7.2: Perfil e exclusão de conta

- [ ] **Task:** Implementar edição de nome/e-mail, troca de senha e exclusão de conta.
  - **Acceptance criteria:**
    - Nome e e-mail editáveis com a mesma validação do registro (e-mail único, formato válido).
    - Trocar a senha exige a senha atual e a nova confirmada.
    - Excluir a conta exige confirmação com a senha atual.
    - Excluir a conta apaga **todas** as sessões de treino daquele usuário em cascata.
    - Após excluir, o usuário é deslogado e a conta não permite mais login.
  - **Feature tests:** `profile_update_validates_unique_email`; `password_change_requires_current_password` → senha atual errada é rejeitada; `account_deletion_requires_current_password`; `account_deletion_cascades_training_sessions` → contagem de sessões daquele usuário vai a zero; `deleted_account_cannot_login`.
  - **Traces:** US-1.3

### Phase 7.3: Escopo por usuário

- [ ] **Task:** Criar a `TrainingSessionPolicy` e aplicá-la a toda leitura, escrita e exclusão de sessão.
  - **Acceptance criteria:**
    - Toda consulta de sessão é escopada ao usuário autenticado, por policy e por escopo de query — nunca só por um dos dois.
    - Requisição a sessão de outro usuário devolve 403 ou 404, jamais o conteúdo.
    - Requisição sem autenticação a qualquer rota de sessão devolve 401 ou redireciona, nunca dado.
    - A listagem de sessões contém apenas sessões do usuário autenticado.
    - Nenhuma rota aceita `user_id` vindo do cliente.
  - **Feature tests:** `user_cannot_read_another_users_session` → 403/404; `user_cannot_update_another_users_session`; `user_cannot_delete_another_users_session`; `guest_gets_no_session_data_from_any_session_route`; `session_list_contains_only_own_sessions` → dois usuários com sessões, cada um vê só as suas.
  - **Traces:** US-1.4, US-11.1, US-11.3

---

## Phase 8: Backend de sessões — Inertia, CRUD e autosave

**Goal:** As rotas que criam, carregam, salvam e excluem sessão, com a validação completa do payload. · **Depends on:** Phase 5, Phase 7 · **Covers:** US-2.1, US-2.2, US-2.3, US-8.1, US-11.1, US-11.2, US-11.3

### Phase 8.1: Carregamento inicial

- [ ] **Task:** Implementar a rota Inertia da prancheta, que entrega sessão corrente + catálogo completo numa única resposta.
  - **Acceptance criteria:**
    - A resposta traz a sessão corrente do usuário — a de maior `last_opened_at` — com diagrama, checklist, notas, estimativas, tempo decorrido e modo de numeração.
    - A mesma resposta traz o catálogo: problemas (com as três listas), componentes agrupados por categoria, tipos de ligação, fases com seus itens de checklist, modos de sequência, durações e modos de estimativa.
    - Usuário sem nenhuma sessão recebe uma sessão vazia **criada na hora**, com duração 45, modo `out`, estimativa padrão do modo por usuários e `problem_id` nulo.
    - O cronômetro chega ao cliente **pausado**, no `elapsed_seconds` gravado — o servidor nunca envia estado "rodando".
    - O catálogo é servido apenas com linhas `is_active`, exceto as referenciadas pelo diagrama da sessão carregada.
    - O payload é montado por Resources, sem vazar colunas internas nem expor sessão de terceiro.
  - **Feature tests:** `board_returns_current_session_and_catalog`; `user_without_session_gets_a_fresh_empty_one` → sessão criada com os padrões; `current_session_is_the_most_recently_opened` → com três sessões, vem a de maior `last_opened_at`; `catalog_payload_contains_expected_counts` → 14/28/6/9/5/25/3/3/2.
  - **Traces:** US-2.2, US-11.2 · project-description → Core Workflow 1

### Phase 8.2: Criar, abrir e excluir sessão

- [ ] **Task:** Implementar `POST /api/sessions` (criar), a ação de abrir uma sessão e `DELETE /api/sessions/{id}`.
  - **Acceptance criteria:**
    - As rotas vivem em `routes/web.php` sob `prefix('api')` com middleware `auth` e guard `web`; sem Sanctum.
    - `POST` aceita `problem_id` e `duration_minutes`, resolve a duração pelo lookup e devolve 201 com a sessão completa no formato do contrato do project-description.
    - `duration_minutes` só aceita 30, 45 ou 60; qualquer outro valor devolve 422. Padrão 45 quando ausente.
    - Criar sessão nova salva o estado da corrente antes de trocar; a nova nasce vazia (sem blocos, marcações ou notas, tempo zerado, duração 45, modo `out`, estimativa padrão) e vira a corrente.
    - Abrir uma sessão atualiza `last_opened_at`, tornando-a a corrente, e devolve o estado completo restaurado.
    - `DELETE` remove a sessão e todo o conteúdo dela; excluir a corrente promove a mais recente restante; excluir a última existente cria uma sessão vazia no lugar.
    - Nenhuma dessas ações alcança sessão de outro usuário.
  - **Feature tests:** `store_rejects_duration_outside_30_45_60` → 422; `store_creates_empty_session_with_defaults`; `store_makes_new_session_current`; `open_session_updates_last_opened_at`; `delete_promotes_most_recent_remaining_to_current`; `deleting_last_session_creates_an_empty_one`; `delete_of_foreign_session_is_forbidden`.
  - **Traces:** US-2.1, US-2.3, US-11.1, US-11.2, US-11.3

### Phase 8.3: Autosave e validação do payload

- [ ] **Task:** Implementar `PUT /api/sessions/{id}` gravando o estado inteiro numa transação.
  - **Acceptance criteria:**
    - Grava `nodes`, `edges`, `checks` e `estimate` nas colunas JSON e `notes`, `elapsed_seconds`, `duration_minutes` (via lookup) e `seq_mode` (via lookup) nas colunas próprias — tudo numa transação única.
    - Devolve 200 com `id` e `updated_at`, conforme o contrato do project-description.
    - `seq_mode` ausente ou inválido é normalizado para `out` em vez de recusar a gravação.
    - `checks` é chaveado por `checklist_items.id`; chave que não corresponda a item existente é rejeitada.
    - A resposta nunca contém dado de outro usuário e a rota respeita a policy da Phase 7.3.
  - **Feature tests:** `update_persists_all_columns_in_one_transaction`; `update_normalizes_invalid_seq_mode_to_out`; `update_rejects_unknown_checklist_item_key` → 422; `update_returns_id_and_updated_at`; `update_of_foreign_session_is_forbidden`.
  - **Traces:** US-8.1, US-4.3, US-6.2, US-1.4

- [ ] **Task:** Escrever a FormRequest que valida integralmente o payload do autosave.
  - **Acceptance criteria:**
    - `nodes[].type` precisa existir em `components.slug`; `edges[].kind` precisa existir em `link_types.slug` ou ser vazio ("sem tipo").
    - `edges[].from` e `edges[].to` precisam referenciar `nodes[].id` **presentes no mesmo payload**, e `from` não pode ser igual a `to`.
    - Limites rejeitados com 422 e mensagem por campo: máximo 200 nós, 400 arestas, 60 caracteres em rótulo de bloco e de aresta, 5.000 caracteres em notas.
    - `elapsed_seconds` é inteiro ≥ 0; `estimate.mode` precisa existir em `estimate_modes.slug`; valores numéricos negativos na estimativa são normalizados para zero.
    - `nodes[].x` e `nodes[].y` são numéricos; `dashed` e `bidir` são booleanos.
    - Payload válido no limite exato (200 nós, 400 arestas, rótulo de 60, notas de 5.000) é **aceito** — o limite é inclusivo.
  - **Feature tests:** `rejects_unknown_component_type` → 422; `rejects_unknown_link_kind`; `rejects_edge_pointing_to_absent_node`; `rejects_self_referencing_edge`; `rejects_more_than_200_nodes`; `rejects_more_than_400_edges`; `rejects_label_longer_than_60`; `rejects_notes_longer_than_5000`; `accepts_payload_exactly_at_every_limit`; `normalizes_negative_estimate_values_to_zero`; `validation_errors_are_keyed_by_field`.
  - **Traces:** US-8.1, US-3.1, US-3.3, US-3.2, US-4.2, US-6.3

---

## Phase 9: Motor + canvas — geometria e blocos

**Goal:** O núcleo do motor TypeScript (modelo, geometria, colisão, snap) e a primeira tela que desenha: paleta, blocos, navegação e undo. · **Depends on:** Phase 6, Phase 8 · **Covers:** US-3.1, US-3.2, US-3.4, US-3.5

### Phase 9.1: Motor — modelo e geometria

- [ ] **Task:** Criar o pacote `resources/js/canvas/` com os tipos do diagrama e as funções de geometria, sem dependência de framework nem de DOM.
  - **Acceptance criteria:**
    - Tipos `Node`, `Edge`, `SessionState`, `View` exportados; o pacote não importa Vue, Inertia nem toca em `document`.
    - `clipPt(cx, cy, w, h, tx, ty, pad)` recorta o ponto na borda do retângulo: escala pelo menor entre `(w/2+pad)/|dx|`, `(h/2+pad)/|dy|` e 1; centro coincidente devolve o próprio centro.
    - `bez(edge, nodes)` produz a curva centro a centro, recortada com `pad` 7 na origem e 10 no destino, com eixo dominante `|dx| >= |dy|` e controle `k = clamp(max(|dx|,|dy|) * 0.45, 26, 110)`.
    - `ptAt(geom, t)` avalia o bezier cúbico; `t = 0.5` devolve o ponto médio usado pelos chips.
    - `head(x, y, fx, fy)` produz o triângulo da ponta com L 9.5 e W 3.9, orientado pela tangente.
    - Largura do bloco fixa em 132; altura padrão 86.
  - **Feature tests:** *(Vitest)* `clipPt_lands_on_rectangle_border`; `clipPt_returns_center_when_target_equals_center`; `bez_uses_horizontal_control_when_dx_dominates` e a recíproca vertical; `bez_clamps_control_between_26_and_110` → nos dois extremos; `ptAt_at_zero_and_one_matches_endpoints`; `head_points_along_the_tangent`.
  - **Traces:** US-3.3 · project-description → Key Concept "Motor do canvas", Core Workflow 3

- [ ] **Task:** Implementar no motor o posicionamento sem colisão, o snap de 4 px e os limites do diagrama.
  - **Acceptance criteria:**
    - `freeSpot(x, y, nodes)` varre os deslocamentos do protótipo em ordem e devolve o primeiro livre; colisão é `|dx| < 122` **e** `|dy| < 80`.
    - Sem posição livre na varredura, devolve deslocamento aleatório dentro da faixa do protótipo — nunca sobrepõe silenciosamente.
    - `snap(v)` arredonda para múltiplo de 4; aplicado a toda criação e movimentação de bloco.
    - `addNode` recusa a inserção quando o diagrama já tem 200 nós e sinaliza o motivo ao chamador; `addEdge` recusa em 400 arestas.
    - O rótulo inicial de um bloco novo é o `short_name` do componente.
  - **Feature tests:** *(Vitest)* `freeSpot_avoids_overlap_with_existing_nodes`; `freeSpot_returns_first_free_offset_in_order`; `snap_rounds_to_multiples_of_four`; `addNode_refuses_beyond_200_nodes`; `addEdge_refuses_beyond_400_edges`; `new_node_label_defaults_to_component_short_name`.
  - **Traces:** US-3.1, US-3.2, US-3.3

- [ ] **Task:** Implementar a pilha de desfazer/refazer no motor.
  - **Acceptance criteria:**
    - A pilha guarda apenas `nodes` e `edges` — nunca view, seleção ou modo de numeração.
    - Limite de 60 estados: o 61º empurra o mais antigo para fora.
    - Uma nova ação depois de desfazer limpa a pilha de refazer.
    - Trocar o modo de numeração **não** empilha; pan e zoom também não.
    - Mover, renomear, criar, apagar, tipar e reordenar aresta empilham.
  - **Feature tests:** *(Vitest)* `undo_stack_caps_at_60_states`; `undo_restores_previous_nodes_and_edges`; `redo_stack_is_cleared_by_new_action`; `changing_sequence_mode_does_not_push_undo`; `pan_and_zoom_do_not_push_undo`.
  - **Traces:** US-3.5, US-4.3

### Phase 9.2: Paleta e blocos

- [ ] **Task:** Construir a paleta de componentes agrupada por categoria, com ícone e cor.
  - **Acceptance criteria:**
    - Os 28 componentes aparecem agrupados nas 6 categorias, na ordem do catálogo, cada um com seu ícone e a cor da categoria.
    - Um clique posiciona o bloco na área visível, sem sobrepor blocos existentes, e abre o campo de nome já focado para digitação imediata.
    - O rótulo inicial é o `short_name` (`DLQ`, não `DLQ — fila de falhas`).
    - Ao atingir 200 nós, a paleta informa por toast e não adiciona o bloco.
    - A cor do bloco vem da categoria e não é editável pelo usuário.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#rail`, `#palette`, `.pitem`), `.spec/init/design/z1.png`
  - **Traces:** US-3.1

- [ ] **Task:** Renderizar os blocos no palco com ícone, rótulo, tipo e as 4 bolinhas de conexão.
  - **Acceptance criteria:**
    - Cada bloco mostra ícone na cor da categoria, rótulo em Archivo semibold e o nome curto do tipo em IBM Plex Mono maiúsculo.
    - As 4 bolinhas de conexão aparecem nas bordas ao passar o mouse ou com o bloco selecionado.
    - O bloco selecionado recebe destaque visual distinto do estado de hover.
    - A altura do bloco acompanha rótulos de mais de uma linha, e as arestas se reajustam à altura real.
  - **Design ref:** `.spec/init/design/z1.png`, `.spec/init/design/pranchetasystemdesign.html` (`.node`, `.nico`, `.nlabel`, `.ntype`, `.h`)
  - **Traces:** US-3.1, US-3.2

- [ ] **Task:** Implementar arrastar para mover e duplo clique para renomear, com snap e limite de rótulo.
  - **Acceptance criteria:**
    - Arrastar move o bloco com snap de 4 px; as arestas ligadas redesenham durante o arrasto.
    - Duplo clique abre a edição do rótulo; Enter confirma, Esc cancela sem gravar.
    - O rótulo aceita no máximo 60 caracteres — o excedente é bloqueado na digitação, não truncado depois.
    - Rótulo esvaziado volta ao `short_name` do componente.
    - Mover e renomear entram na pilha de desfazer; um arrasto que não moveu nada não empilha.
  - **Feature tests:** *(Vitest, sobre o motor)* `label_is_capped_at_60_characters`; `empty_label_falls_back_to_short_name`; `move_snaps_to_4px_grid`.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`editLabel`, handler de `pointermove`)
  - **Traces:** US-3.2

### Phase 9.3: Navegação e exclusão

- [ ] **Task:** Implementar pan, zoom ancorado no cursor, enquadrar tudo e a grade.
  - **Acceptance criteria:**
    - Arrastar no vazio faz pan; a roda do mouse dá zoom ancorado na posição do cursor.
    - A barra de zoom mostra a escala em porcentagem e tem botões de mais, menos e enquadrar.
    - "Enquadrar tudo" ajusta escala e posição para todo o diagrama caber, **reservando a largura da legenda** quando ela está expandida.
    - A grade acompanha pan e zoom (passo de 26 px multiplicado pela escala).
    - Pan e zoom não entram na pilha de desfazer.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#zoombar`, `applyView`, `fit`, `zoomAt`), `.spec/init/design/z2.png`
  - **Traces:** US-3.4

- [ ] **Task:** Implementar exclusão do item selecionado e os atalhos de teclado.
  - **Acceptance criteria:**
    - `Del` apaga o item selecionado; apagar um bloco apaga também todas as arestas ligadas a ele.
    - `Ctrl+Z` desfaz e `Ctrl+Shift+Z` refaz.
    - Os atalhos não disparam enquanto o foco está num campo de texto ou num rótulo em edição.
    - Apagar limpa a seleção e redesenha o diagrama.
  - **Feature tests:** *(Vitest)* `deleting_a_node_removes_its_incident_edges` → arestas de entrada e de saída; `delete_clears_selection`.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`delSel`, handler de `keydown`)
  - **Traces:** US-3.5

- [ ] **Task:** Implementar o estado vazio do palco e a prancheta livre.
  - **Acceptance criteria:**
    - Com o canvas vazio, o palco mostra o cartão de estado vazio explicando paleta, ligação, `Del` e duplo clique.
    - O estado vazio some assim que o primeiro bloco entra.
    - Sessão sem problema e sem blocos abre o seletor de problemas automaticamente ao carregar (Phase 16), mas o usuário pode fechá-lo e desenhar assim mesmo — a prancheta livre é um caminho suportado.
    - Desenhar sem problema escolhido não bloqueia autosave, export nem cronômetro.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#empty`)
  - **Traces:** US-2.1, US-3.1 · escopo v1 confirmado pelo desenvolvedor (prancheta livre)

---

## Phase 10: Autosave no cliente

**Goal:** A partir daqui todo estado desenhado persiste sozinho; as fases seguintes nascem salvas. · **Depends on:** Phase 8, Phase 9 · **Covers:** US-8.1, US-8.2, US-8.3

### Phase 10.1: Store da sessão e estado sujo

- [ ] **Task:** Criar o store da sessão no cliente, com a marcação de estado sujo em toda alteração.
  - **Acceptance criteria:**
    - O store guarda o estado inteiro da sessão: nós, arestas, checklist, notas, estimativas, tempo decorrido, duração e modo de numeração.
    - Qualquer alteração em qualquer um desses campos marca a sessão como suja.
    - O store é a fonte única de verdade do payload enviado ao servidor — nenhum componente monta payload por conta própria.
    - Alterações de view (pan, zoom), seleção e estado da legenda **não** sujam a sessão.
  - **Feature tests:** *(Vitest)* `any_state_change_marks_session_dirty` → um caso por campo persistido; `view_and_selection_changes_do_not_mark_dirty`.
  - **Traces:** US-8.1

- [ ] **Task:** Implementar o duplo debounce: gravação local após 800 ms de inércia e envio ao servidor após 3 s.
  - **Acceptance criteria:**
    - Após 800 ms sem alteração, o estado é gravado no `localStorage` do navegador.
    - Após 3 s sem alteração, o estado é enviado ao servidor por `PUT /api/sessions/{id}`.
    - Alterações em rajada reiniciam os dois temporizadores — não há envio por tecla digitada.
    - Fechar a aba dispara uma última gravação local.
    - O envio carrega o token CSRF do Inertia e usa o cookie de sessão; não há token de API.
  - **Feature tests:** *(Vitest, com temporizadores falsos)* `local_save_fires_after_800ms_of_quiet`; `server_save_fires_after_3s_of_quiet`; `burst_of_changes_resets_both_timers`; `unload_triggers_a_final_local_save`.
  - **Traces:** US-8.1

### Phase 10.2: Indicador e resiliência

- [ ] **Task:** Implementar o indicador de salvamento com os três estados e o botão de salvar agora.
  - **Acceptance criteria:**
    - Os três estados visíveis: "não salvo", "salvando…" e "salvo".
    - O indicador vai para "não salvo" no instante da alteração, antes de qualquer temporizador.
    - O botão Salvar força o envio imediato, sem esperar a inércia.
    - Após envio bem-sucedido, o indicador mostra "salvo".
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`.savechip`, `#btnSave`, `chip()`)
  - **Traces:** US-8.2

- [ ] **Task:** Tratar falha de rede, rate limit e conflito de versão sem perder trabalho.
  - **Acceptance criteria:**
    - Falha no envio mantém o indicador em "não salvo" e agenda nova tentativa com recuo.
    - O trabalho continua utilizável durante a falha: nada é bloqueado, revertido ou descartado.
    - O estado local persiste no navegador e é reaplicado se a página recarregar antes do envio.
    - Erro de rate limit (429) espera e tenta de novo, sem perder alterações.
    - Quando a versão do servidor for mais nova que a local, o usuário é **avisado** por toast em vez de sobrescrever silenciosamente.
    - Ao carregar, se o estado local for mais novo que o do servidor, o mais novo prevalece.
  - **Feature tests:** *(Vitest)* `failed_save_keeps_dirty_and_schedules_retry`; `rate_limited_save_backs_off_and_retries`; `newer_server_version_warns_instead_of_overwriting`; `local_state_is_reapplied_after_reload`; `newer_local_state_wins_on_boot`.
  - **Traces:** US-8.3

---

## Phase 11: Ligações e tipos

**Goal:** Criar setas arrastando, tipá-las com os 9 tipos, rotulá-las e forçar traço, mão dupla e inversão. · **Depends on:** Phase 9, Phase 10 · **Covers:** US-3.3, US-4.1, US-4.2

### Phase 11.1: Motor — arestas e tipos

- [ ] **Task:** Implementar no motor as regras de aresta e a resolução do tipo de ligação.
  - **Acceptance criteria:**
    - `edgeOk(edge)` é verdadeiro só quando origem ≠ destino e ambos os nós existem no estado.
    - `liveEdges()` filtra as arestas válidas; arestas órfãs (nó apagado) são ignoradas no desenho sem serem removidas do estado.
    - `dashOf(edge)` devolve o `dash_array` do tipo: nulo (contínuo) para http/grpc/ws/query/cache, `5 4.5` para event/repl/batch e `2 4.5` para retry; a flag manual `dashed` sobrepõe o padrão do tipo.
    - `edgeColor(edge)` devolve a cor da categoria do nó de **origem** — nunca uma cor derivada do tipo.
    - Escolher o tipo `ws` liga a mão dupla; a flag manual continua alternável depois.
    - Não é possível ligar um bloco a ele mesmo.
  - **Feature tests:** *(Vitest)* `edgeOk_rejects_self_loops_and_dangling_edges`; `dashOf_matches_each_of_the_nine_types`; `manual_dashed_overrides_type_default`; `edgeColor_follows_source_category`; `choosing_ws_sets_bidirectional`; `no_link_type_introduces_a_new_color` → o conjunto de cores usadas é subconjunto das 6 cores de categoria.
  - **Traces:** US-4.1, US-3.3

### Phase 11.2: Criar a ligação

- [ ] **Task:** Implementar o arrasto de bolinha até outro bloco, com fantasma e alvo destacado.
  - **Acceptance criteria:**
    - Arrastar de uma das 4 bolinhas desenha uma curva fantasma que segue o cursor.
    - O bloco sob o cursor recebe destaque de alvo, exceto o próprio bloco de origem.
    - Soltar sobre um bloco válido cria a aresta; soltar fora de qualquer bloco cancela sem criar nada.
    - Soltar sobre o próprio bloco de origem não cria aresta.
    - Ao atingir 400 arestas, a criação é recusada com aviso por toast.
    - Mover qualquer um dos blocos redesenha a seta imediatamente.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`drawGhost`, `.linktarget`, `.h`), `.spec/init/design/z1.png`
  - **Traces:** US-3.3

### Phase 11.3: Barra flutuante — tipo, rótulo e flags

- [ ] **Task:** Construir a barra flutuante da aresta selecionada com o menu dos 9 tipos + "Sem tipo".
  - **Acceptance criteria:**
    - Selecionar uma seta abre a barra flutuante posicionada junto a ela, acompanhando pan e zoom.
    - O menu lista os 9 tipos mais "Sem tipo", cada opção mostrando o **padrão de traço real** na amostra (contínuo, tracejado, pontilhado).
    - Escolher o tipo aplica traço, selo e — no caso do WebSocket — a mão dupla.
    - O selo é colorido pela categoria da **origem**; nenhum tipo introduz cor nova.
    - "Sem tipo" limpa o selo e devolve o traço contínuo.
  - **Design ref:** `.spec/init/design/z1.png`, `.spec/init/design/pranchetasystemdesign.html` (`floatbar`, `openKindMenu`)
  - **Traces:** US-4.1

- [ ] **Task:** Implementar o rótulo livre da aresta e as flags manuais de tracejado, mão dupla e inversão.
  - **Acceptance criteria:**
    - O rótulo livre convive com o selo e aparece ao lado, separado por um ponto médio: `async · GET /feed`.
    - O rótulo aceita no máximo 60 caracteres.
    - Tracejado, mão dupla e inversão de sentido são alternáveis manualmente, independentes do tipo escolhido.
    - Inverter troca origem e destino, e o selo passa a herdar a cor da nova origem.
    - Sem tipo e sem rótulo, o chip da seta perde borda e fundo.
    - Todas essas alterações entram na pilha de desfazer.
  - **Feature tests:** *(Vitest)* `edge_label_is_capped_at_60_characters`; `reversing_edge_swaps_endpoints_and_recolors_badge`; `bare_edge_renders_no_chip_background`; `type_and_flag_changes_push_undo`.
  - **Design ref:** `.spec/init/design/z1.png` (chips `HTTP | PUT recibo`, `retry | falhou 3x`)
  - **Traces:** US-4.2

---

## Phase 12: Numeração de sequência

**Goal:** Os três modos de numeração derivada do estado, o botão que os alterna e a reordenação das saídas. · **Depends on:** Phase 11 · **Covers:** US-4.3, US-4.4

### Phase 12.1: Motor — outSeq, flowSeq e reordenação

- [ ] **Task:** Implementar `outSeq()` — a numeração das saídas de cada bloco.
  - **Acceptance criteria:**
    - Só recebem número as arestas cujo bloco de origem tem **2 ou mais** saídas válidas; bloco com saída única não numera.
    - Cada aresta numerada recebe o índice dentro do bloco e o total daquele bloco.
    - A ordem é a posição da aresta no array `edges` — não existe campo de ordem por aresta.
    - Arestas inválidas (auto-ligação, nó ausente) não contam nem para o índice nem para o total.
  - **Feature tests:** *(Vitest)* `outSeq_skips_blocks_with_a_single_output`; `outSeq_numbers_by_array_order`; `outSeq_ignores_dangling_edges`; `outSeq_reports_correct_total_per_block`.
  - **Traces:** US-4.3, US-4.4

- [ ] **Task:** Implementar `flowSeq()` — a numeração do fluxo inteiro por busca em profundidade.
  - **Acceptance criteria:**
    - As raízes são, nesta ordem: nós **cliente** sem aresta de entrada, depois os demais nós sem entrada, depois todos os nós restantes.
    - A travessia é em profundidade seguindo a ordem de saída de cada bloco, numerando 1..N.
    - Cada aresta é visitada uma única vez — diagramas com ciclo terminam sem laço infinito.
    - Nós órfãos e componentes desconexos são cobertos ao final, sem ficar sem número.
    - Todas as arestas numeradas recebem o mesmo total N.
  - **Feature tests:** *(Vitest)* `flowSeq_starts_from_client_nodes_without_inputs`; `flowSeq_follows_output_order_depth_first`; `flowSeq_terminates_on_cyclic_graphs`; `flowSeq_covers_orphan_components_at_the_end`; `flowSeq_numbers_every_live_edge_exactly_once`.
  - **Traces:** US-4.3

- [ ] **Task:** Implementar `seqMap()`, a normalização do modo e `moveSeq()`.
  - **Acceptance criteria:**
    - `seqMap()` devolve mapa vazio em `off`, o resultado de `flowSeq` em `flow` e o de `outSeq` em `out`.
    - Modo ausente, nulo ou desconhecido é normalizado para `out`.
    - `moveSeq(edge, ±1)` troca a aresta de posição com a vizinha **de mesma origem** no array `edges`, e empilha undo.
    - Na primeira saída, mover para trás é recusado; na última, mover para frente também.
    - Reordenar altera a numeração de `out` e a travessia de `flow` de forma coerente.
  - **Feature tests:** *(Vitest)* `seqMap_returns_empty_when_off`; `invalid_mode_normalizes_to_out`; `moveSeq_swaps_with_sibling_output_only`; `moveSeq_refuses_at_both_ends`; `reordering_changes_flow_traversal_order`.
  - **Traces:** US-4.3, US-4.4

### Phase 12.2: UI da numeração

- [ ] **Task:** Renderizar o número dentro do chip da seta, na cor da categoria da origem.
  - **Acceptance criteria:**
    - O número aparece **dentro** do chip da aresta, num círculo à esquerda do texto.
    - O número herda a cor da categoria do bloco de origem.
    - Sem tipo e sem rótulo, sobra apenas o círculo com o número.
    - Com número, o retângulo do chip alarga para caber o círculo.
    - Em modo `off` nenhum número é desenhado.
  - **Design ref:** `.spec/init/design/z1.png` (`① async`, `② HTTP | PUT recibo`, `③ retry | falhou 3x`)
  - **Traces:** US-4.3

- [ ] **Task:** Implementar o botão `1→2` da barra de zoom com o menu dos três modos.
  - **Acceptance criteria:**
    - O botão abre o menu "NUMERAÇÃO DAS SETAS" com as três opções: Sem números, Ordem de saída de cada bloco, Sequência do fluxo inteiro.
    - A opção ativa é destacada; escolher aplica na hora e fecha o menu.
    - O botão fica em estado ligado enquanto o modo não for `off`.
    - O modo é gravado na sessão (`seq_mode`) e restaurado ao reabrir.
    - Trocar de modo **não** entra na pilha de desfazer — é ajuste de visualização.
  - **Design ref:** `.spec/init/design/z2.png`, `.spec/init/design/pranchetasystemdesign.html` (`#zSeq`, `openSeqMenu`)
  - **Traces:** US-4.3

- [ ] **Task:** Implementar o controle `‹ n/total ›` de reordenação na barra flutuante.
  - **Acceptance criteria:**
    - Com uma seta selecionada, a barra mostra `‹ n/total ›` referente às saídas do bloco de origem daquela seta.
    - As setas antecipam ou adiam a aresta uma posição na ordem de saída.
    - Nas pontas (primeira ou última saída), o controle correspondente fica desabilitado.
    - Bloco com saída única não exibe o controle.
    - A reordenação reflete imediatamente na numeração desenhada.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`floatbar`, controles `‹ ›`)
  - **Traces:** US-4.4

---

## Phase 13: Legenda automática

**Goal:** A legenda que se monta a partir do desenho, ensina o vocabulário e some quando não há nada. · **Depends on:** Phase 12 · **Covers:** US-5.1, US-5.2

### Phase 13.1: Motor — legendData

- [ ] **Task:** Implementar `legendData()` no motor, derivando a legenda inteira do estado do diagrama.
  - **Acceptance criteria:**
    - Devolve as **categorias presentes** com a contagem de blocos de cada uma, na ordem do catálogo — categorias ausentes não aparecem.
    - Devolve os **tipos usados** por arestas válidas, na ordem do catálogo de tipos — tipos não usados não aparecem.
    - Sinaliza a existência de arestas **sem tipo**.
    - Sinaliza se há numeração ativa, para a seção de sequência.
    - Diagrama vazio produz estrutura vazia em todos os campos.
    - Só arestas válidas contam: aresta órfã não faz seu tipo aparecer na legenda.
  - **Feature tests:** *(Vitest)* `legendData_lists_only_present_categories_with_counts`; `legendData_lists_only_used_link_types_in_catalog_order`; `legendData_flags_untyped_edges`; `legendData_flags_sequence_only_when_numbering_is_on`; `legendData_is_empty_for_empty_diagram`; `dangling_edges_do_not_appear_in_legend`.
  - **Traces:** US-5.1

### Phase 13.2: Painel da legenda

- [ ] **Task:** Construir o painel da legenda no canto inferior direito do palco.
  - **Acceptance criteria:**
    - Seção *Blocos*: uma linha por categoria presente — quadradinho na cor da categoria, nome e contagem.
    - Seção *Ligações*: uma linha por tipo usado — amostra do traço real **em tom neutro**, selo, nome e a glosa de uma linha vinda do catálogo.
    - Arestas sem tipo produzem a linha `sem tipo — clique na seta e escolha o protocolo`.
    - Seção *Sequência*: aparece só quando há numeração, com o texto do modo ativo.
    - A legenda some por completo quando não há nada desenhado.
    - As amostras de traço são neutras: só os quadradinhos de categoria têm cor.
  - **Design ref:** `.spec/init/design/out3.png` (bloco LEGENDA), `.spec/init/design/pranchetasystemdesign.html` (`#legend`, `renderLegend`, `lineSample`)
  - **Traces:** US-5.1

- [ ] **Task:** Implementar o recolhimento da legenda com persistência local e isolamento de eventos.
  - **Acceptance criteria:**
    - Um controle recolhe e expande o painel.
    - O estado recolhido persiste no navegador entre recarregamentos.
    - O estado da legenda é preferência do **navegador**, não da sessão — não vai para o servidor nem suja a sessão.
    - Recolhida, a legenda deixa de reservar largura no "enquadrar tudo".
    - `pointerdown` e `wheel` dentro do painel não fazem pan nem zoom no palco.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#lgTog`, `.closed`, guarda `closest('#legend')`)
  - **Traces:** US-5.2, US-3.4

---

## Phase 14: Roteiro — cronômetro e fases

**Goal:** O relógio que divide a duração pelos pesos, abre a fase corrente sozinho e sobrevive ao fechamento do navegador. · **Depends on:** Phase 10 · **Covers:** US-6.1, US-2.3

### Phase 14.1: Motor do tempo

- [ ] **Task:** Implementar no motor o cálculo das fronteiras de fase e da fase corrente.
  - **Acceptance criteria:**
    - `bounds(durationMinutes, phases)` devolve duração e fim acumulado de cada fase, aplicando o peso sobre a duração total em segundos.
    - `curPhase(elapsed)` devolve a primeira fase cujo fim ultrapassa o decorrido; passado o total, devolve a última.
    - Em 45 minutos as cinco fases resultam em aproximadamente 5, 5, 8, 12 e 15 minutos.
    - Mudar a duração recalcula as fatias preservando o tempo já decorrido.
    - A soma das durações de fase é sempre igual à duração total.
  - **Feature tests:** *(Vitest)* `bounds_for_45_minutes_matches_5_5_8_12_15`; `bounds_sum_equals_total_duration` → para 30, 45 e 60; `curPhase_advances_at_each_boundary`; `curPhase_clamps_to_last_phase_after_total`; `changing_duration_preserves_elapsed`.
  - **Traces:** US-6.1, US-2.3

### Phase 14.2: Relógio e barra de fases

- [ ] **Task:** Construir o relógio com play/pausa, zerar, fase corrente e barra proporcional.
  - **Acceptance criteria:**
    - Mostra o tempo decorrido em `mm:ss` e o total da sessão.
    - Play inicia a contagem, pausa interrompe, e o tempo decorrido é preservado no estado.
    - O botão de zerar para o relógio, zera o decorrido e reabre a primeira fase.
    - O número e o nome da fase corrente ficam visíveis; a barra mostra as cinco fatias com largura proporcional ao peso, marcando as concluídas e o progresso dentro da corrente.
    - O relógio muda de aparência ao ultrapassar 85% do tempo e novamente ao estourar o total.
    - Ao zerar o tempo restante, o cronômetro para e sinaliza o fim, sem apagar nada.
    - Ao carregar a sessão, o cronômetro retoma **pausado** no tempo gravado — nunca contando sozinho.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#clock`, `#tBig`, `#tPlay`, `#tReset`, `#bar`, `renderClock`)
  - **Traces:** US-6.1, US-2.2 · escopo v1 confirmado pelo desenvolvedor (zerar o cronômetro)

- [ ] **Task:** Implementar a persistência periódica do tempo decorrido.
  - **Acceptance criteria:**
    - Com o cronômetro rodando, o tempo decorrido é gravado periodicamente, sobrevivendo ao fechamento do navegador.
    - A gravação periódica não dispara envio ao servidor a cada tique — respeita o debounce da Phase 10.
    - Reabrir a aplicação restaura o decorrido gravado, pausado.
  - **Feature tests:** *(Vitest, temporizadores falsos)* `running_clock_persists_elapsed_periodically`; `clock_resumes_paused_at_persisted_elapsed`.
  - **Traces:** US-6.1, US-2.2

- [ ] **Task:** Construir o seletor de duração de 30, 45 e 60 minutos.
  - **Acceptance criteria:**
    - Os três botões vêm do catálogo `session_durations`, não de lista fixa no código.
    - A duração corrente fica destacada; a padrão de uma sessão nova é 45.
    - Trocar a duração recalcula relógio e fatias de fase e persiste na sessão.
    - Mudar a duração com o cronômetro rodando preserva o tempo já decorrido.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#durpick`, `renderDur`)
  - **Traces:** US-2.3

---

## Phase 15: Roteiro — checklist e notas

**Goal:** Os 25 itens marcáveis por fase e o bloco de notas da sessão. · **Depends on:** Phase 14 · **Covers:** US-6.2, US-6.3

### Phase 15.1: Checklist por fase

- [ ] **Task:** Construir o acordeão de fases com os itens de checklist marcáveis.
  - **Acceptance criteria:**
    - As cinco fases aparecem com número, nome e a duração em minutos da fatia atual.
    - Cada fase mostra o progresso (marcados / total) e os 25 itens totais estão distribuídos 4 / 5 / 5 / 5 / 6.
    - Cada item alterna marcado/desmarcado com um clique.
    - A fase corrente **abre sozinha** quando o tempo cruza a fronteira dela; as outras ficam colapsadas.
    - O usuário pode abrir manualmente qualquer fase, e essa escolha prevalece até a próxima virada de fase.
    - Itens de fases já passadas continuam marcáveis a qualquer momento.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#phases`, `.ph`, `.chk`, `renderPhases`)
  - **Traces:** US-6.2, US-6.1

- [ ] **Task:** Persistir o estado do checklist por sessão, chaveado por item do catálogo.
  - **Acceptance criteria:**
    - `checks` é um mapa de `checklist_items.id` para verdadeiro; ausência equivale a desmarcado.
    - Marcar ou desmarcar suja a sessão e entra no autosave.
    - O estado é restaurado integralmente ao reabrir a sessão.
    - Reordenar o seeder do catálogo não reinterpreta marcações já gravadas.
  - **Feature tests:** *(Pest)* `checks_are_keyed_by_checklist_item_id`; `checks_survive_round_trip_through_autosave`; `unknown_checklist_key_is_rejected`. *(Vitest)* `toggling_an_item_marks_session_dirty`.
  - **Traces:** US-6.2, US-8.1

### Phase 15.2: Notas

- [ ] **Task:** Construir o bloco de notas da sessão.
  - **Acceptance criteria:**
    - Campo livre, multilinha, com limite de 5.000 caracteres.
    - As notas pertencem à sessão e são restauradas ao reabrir.
    - Digitar marca a sessão como suja e dispara o autosave, sem envio por tecla.
    - Ao atingir o limite, a digitação é bloqueada com aviso — o texto não é truncado sem avisar.
  - **Feature tests:** *(Pest)* `notes_longer_than_5000_are_rejected_with_422`. *(Vitest)* `typing_notes_marks_dirty_and_debounces`.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#notes`, `#pane-notas`)
  - **Traces:** US-6.3, US-8.1

---

## Phase 16: Enunciado, seletor de problemas e gabarito

**Goal:** Escolher o problema, ler o enunciado durante todo o treino e abrir o gabarito só no fim. · **Depends on:** Phase 8, Phase 10 · **Covers:** US-2.1, US-10.1

### Phase 16.1: Seletor de problemas

- [ ] **Task:** Construir a folha do seletor com os 14 problemas.
  - **Acceptance criteria:**
    - A lista mostra os 14 problemas com nome, etiqueta de tema e nível.
    - Escolher um problema grava-o na sessão corrente e fecha a folha.
    - Sessão sem problema **e** sem blocos abre o seletor automaticamente ao carregar, com o atraso do protótipo (~450 ms).
    - O seletor pode ser fechado sem escolher — a prancheta livre continua disponível.
    - A barra superior mostra o nome do problema corrente, ou "Escolher problema" quando não há.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#btnProblem`, `#probName`, `openSheet`)
  - **Traces:** US-2.1

### Phase 16.2: Painel do enunciado e gabarito

- [ ] **Task:** Construir o painel do enunciado com contexto, requisitos funcionais e escala alvo.
  - **Acceptance criteria:**
    - O contexto em prosa é renderizado em Source Serif 4; as listas em Archivo.
    - "Requisitos funcionais" e "Escala e restrições" aparecem como listas separadas, na ordem gravada em `problem_items`.
    - O nível do problema aparece junto da etiqueta de tema.
    - Sem problema escolhido, o painel explica como carregar um enunciado e menciona a prancheta livre.
    - O enunciado permanece acessível durante todo o treino, na sua aba.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#enunciado`, `.prob-title`, `.prob-ctx`, `.req`, `renderProb`)
  - **Traces:** US-2.1

- [ ] **Task:** Implementar o bloco colapsado "Tópicos que este problema cobra".
  - **Acceptance criteria:**
    - O bloco nasce **colapsado** em toda sessão, rotulado "abra só depois de terminar".
    - O conteúdo é a lista estática de tópicos do problema — sem nota, sem pontuação, sem avaliação automática.
    - Abrir o bloco é ação explícita do usuário e não altera cronômetro nem checklist.
    - Recarregar a página devolve o bloco ao estado colapsado.
    - A ferramenta em nenhum momento julga o diagrama desenhado: não existe rota, cálculo ou tela que compare o desenho com os tópicos.
  - **Feature tests:** *(Pest)* `no_route_scores_or_evaluates_a_diagram` → nenhuma rota da aplicação devolve nota, acerto ou avaliação de sessão.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`details.reveal`, `summary`)
  - **Traces:** US-10.1

---

## Phase 17: Estimativas de capacidade

**Goal:** A calculadora com os dois modos, dez linhas de saída, formatação pt-BR e a frase de conclusão. · **Depends on:** Phase 10 · **Covers:** US-7.1, US-7.2, US-7.3

### Phase 17.1: Motor de cálculo

- [ ] **Task:** Implementar o cálculo das dez linhas de saída, idêntico nos dois modos.
  - **Acceptance criteria:**
    - Escritas por dia: `perMonth / 30` no modo mensal, `dau × ações` no modo por usuários. **Mês = 30 dias.**
    - Leituras por dia = escritas por dia × leituras por escrita.
    - QPS de escrita e de leitura = valor diário / 86400.
    - Total por segundo no pico = `(wqps + rqps) × fator de pico`.
    - Dados novos por dia = escritas por dia × tamanho do registro; por ano = × 365; armazenamento na retenção = × anos.
    - Banda de saída no pico = `rqps × fator × tamanho`.
    - As dez linhas são as mesmas nos dois modos, na mesma ordem.
    - Valores negativos são normalizados para zero; campo vazio não quebra a saída; divisão por zero não produz `NaN` na tela.
  - **Feature tests:** *(Vitest)* `both_modes_produce_identical_output_for_equivalent_inputs` → `dau × act` = `perMonth / 30`; `month_is_thirty_days`; `qps_divides_by_86400`; `peak_applies_factor_to_combined_qps`; `outbound_bandwidth_uses_read_qps_peak_and_size`; `negative_inputs_are_normalized_to_zero`; `empty_and_zero_inputs_do_not_produce_NaN`.
  - **Traces:** US-7.1, US-7.2

- [ ] **Task:** Implementar a formatação pt-BR de números e de bytes.
  - **Acceptance criteria:**
    - Números: sufixo `mil` a partir de 10³, `mi` a partir de 10⁶, `bi` a partir de 10⁹, com no máximo uma casa decimal e separadores pt-BR.
    - Abaixo de mil, uma casa decimal para valores menores que 10 e nenhuma para os demais.
    - Bytes: escala de B até PB, partindo de KB, com no máximo uma casa decimal.
    - Valor não finito é exibido como travessão, nunca como `NaN` ou `Infinity`.
  - **Feature tests:** *(Vitest)* `formats_thousands_millions_and_billions_in_ptbr`; `formats_bytes_from_B_to_PB`; `non_finite_values_render_as_dash`; `sub_ten_values_keep_one_decimal`.
  - **Traces:** US-7.1

- [ ] **Task:** Implementar a frase de conclusão por ordem de grandeza do pico.
  - **Acceptance criteria:**
    - Pico ≥ 50.000 qps: "esse pico exige cache e particionamento, diga isso em voz alta".
    - Pico ≥ 5.000 e < 50.000 qps: "dá para servir com réplicas de leitura e cache".
    - Pico < 5.000 qps: "uma instância bem dimensionada aguenta, não invente complexidade".
    - A frase acompanha o aviso "Mês = 30 dias" e a orientação de arredondar.
    - A frase muda em tempo real ao alterar qualquer parâmetro.
  - **Feature tests:** *(Vitest)* `conclusion_thresholds_at_50k_and_5k` → inclusive nas duas fronteiras exatas; `conclusion_updates_with_any_parameter_change`.
  - **Traces:** US-7.3

### Phase 17.2: UI da calculadora

- [ ] **Task:** Construir o painel de estimativas com o seletor de modo, os campos e as dez linhas de saída.
  - **Acceptance criteria:**
    - O seletor alterna entre "Por usuários" e "Por volume mensal", e o modo é gravado na sessão.
    - Modo por usuários: campos de usuários ativos por dia e ações de escrita por usuário/dia. Modo mensal: apenas escritas por mês.
    - Campos comuns nos dois modos: leituras por escrita, tamanho médio do registro (KB), fator de pico, retenção em anos.
    - Trocar de modo preserva os valores dos campos comuns.
    - A linha destacada muda com o modo: "Escritas por dia" no modo por usuários, "Escritas por mês" no mensal; "Total por segundo no pico" e o armazenamento na retenção ficam sempre destacados.
    - Recalcula a cada tecla **sem perder o foco nem a posição do cursor** no campo em edição.
    - Os modos e as linhas destacadas vêm do catálogo `estimate_modes`, não de lista fixa no código.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#calc`, `.seg`, `.calc`, `.out`, `renderCalc`)
  - **Traces:** US-7.1, US-7.2, US-7.3

---

## Phase 18: Export SVG

**Goal:** O diagrama inteiro em SVG, gerado no cliente, com selos, traços, números e a legenda num bloco abaixo. · **Depends on:** Phase 13 · **Covers:** US-9.1

### Phase 18.1: Motor — construção do SVG

- [ ] **Task:** Implementar `buildSVG()` reproduzindo o diagrama a partir do mesmo estado que a tela desenha.
  - **Acceptance criteria:**
    - O enquadramento cobre todos os nós com margem de 40 px, e a largura acomoda a legenda quando ela é mais larga que o desenho.
    - Ordem de desenho: arestas e blocos primeiro, **chips das setas depois dos blocos** — bloco vizinho nunca cobre rótulo no arquivo exportado.
    - Cada aresta sai com a cor da categoria da origem, o padrão de traço do seu tipo e a ponta orientada pela tangente; mão dupla desenha as duas pontas.
    - O chip traz selo e rótulo separados por ponto médio; com número, o retângulo alarga 20 px e o círculo fica à esquerda; sem tipo e sem rótulo, sobra só o círculo.
    - Cada bloco sai com moldura, ícone na cor da categoria, rótulo quebrado em até 3 linhas e o nome curto do tipo em maiúsculas.
    - As cores saem resolvidas do tema ativo — o arquivo não depende de variáveis CSS do documento.
  - **Feature tests:** *(Vitest)* `svg_chips_are_emitted_after_node_groups` → a posição dos elementos na string; `svg_viewbox_covers_all_nodes_with_padding`; `svg_width_accommodates_a_wider_legend`; `svg_edge_stroke_matches_source_category_color`; `svg_bidirectional_edge_has_two_arrowheads`; `svg_chip_with_number_widens_by_20px`; `svg_bare_edge_with_number_renders_only_the_circle`; `svg_labels_wrap_to_at_most_three_lines`.
  - **Traces:** US-9.1

- [ ] **Task:** Implementar `svgLegend()` — a legenda num bloco abaixo do diagrama.
  - **Acceptance criteria:**
    - A legenda sai **abaixo** do desenho, com o mesmo conteúdo da tela: blocos por categoria com contagem, tipos usados com amostra de traço, selo, nome e glosa, e a linha de sequência quando há numeração.
    - As amostras de traço são neutras; só os quadradinhos de categoria têm cor.
    - Arestas sem tipo produzem a linha "sem tipo".
    - Diagrama sem nada desenhado não produz bloco de legenda.
    - A altura e a largura calculadas da legenda são as usadas no enquadramento do SVG.
  - **Feature tests:** *(Vitest)* `svg_legend_matches_screen_legend_content`; `svg_legend_samples_are_neutral_colored`; `svg_legend_is_absent_for_empty_diagram`; `svg_legend_dimensions_feed_the_viewbox`.
  - **Design ref:** `.spec/init/design/out3.png` (bloco LEGENDA sob o diagrama)
  - **Traces:** US-9.1, US-5.1

### Phase 18.2: Botão e download

- [ ] **Task:** Implementar o botão SVG da barra superior e o download no cliente.
  - **Acceptance criteria:**
    - O arquivo é gerado no cliente, a partir do estado corrente — nada trafega pelo servidor.
    - O nome do arquivo é o slug do problema escolhido, ou `prancheta` quando não há problema.
    - Diagrama vazio **não** gera arquivo: o botão avisa por toast que não há nada a exportar.
    - O arquivo baixado abre em visualizador de SVG e num navegador sem erro de parse.
  - **Design ref:** `.spec/init/design/out3.png`, `.spec/init/design/pranchetasystemdesign.html` (`#btnExport`)
  - **Traces:** US-9.1

---

## Phase 19: Gerenciar sessões

**Goal:** Listar, abrir, criar e excluir sessões, sem nunca ficar sem sessão corrente. · **Depends on:** Phase 8, Phase 10 · **Covers:** US-11.1, US-11.2, US-11.3

### Phase 19.1: Listagem e abertura

- [ ] **Task:** Construir a folha de sessões com a lista do usuário.
  - **Acceptance criteria:**
    - A lista mostra data, problema, duração escolhida e tempo usado de cada sessão.
    - A ordenação é da mais recente para a mais antiga.
    - A sessão corrente é identificada visualmente na lista.
    - A lista contém apenas sessões do usuário autenticado.
    - Abrir uma sessão a torna a corrente e restaura diagrama, checklist, notas, estimativas, tempo e modo de numeração.
    - Antes de trocar de sessão, a corrente é salva.
  - **Feature tests:** *(Pest)* `session_list_is_ordered_by_recency`; `opening_a_session_restores_full_state`; `switching_sessions_saves_the_current_one_first`.
  - **Design ref:** `.spec/init/design/pranchetasystemdesign.html` (`#btnSessions`, `openSheet`)
  - **Traces:** US-11.1, US-2.2

### Phase 19.2: Criar e excluir

- [ ] **Task:** Implementar a criação de sessão nova pela interface.
  - **Acceptance criteria:**
    - Criar sessão nova salva a corrente antes de trocar.
    - A nova nasce vazia: sem blocos, sem marcações, sem notas, tempo zerado, duração 45, modo de numeração `out`.
    - As estimativas nascem com os valores padrão do modo por usuários.
    - A nova vira a corrente e o seletor de problemas abre automaticamente.
    - Sessões anteriores não são apagadas nem sobrescritas.
  - **Feature tests:** *(Pest)* `new_session_starts_empty_with_defaults`; `creating_a_session_preserves_previous_ones`; `new_session_becomes_current`.
  - **Traces:** US-11.2

- [ ] **Task:** Implementar a exclusão de sessão com confirmação e promoção da corrente.
  - **Acceptance criteria:**
    - Excluir pede confirmação explícita antes de qualquer requisição.
    - A exclusão remove a sessão e todo o conteúdo dela.
    - Excluir a sessão corrente promove a mais recente restante a corrente.
    - Excluir a última sessão existente cria uma sessão vazia no lugar — o usuário nunca fica sem corrente.
    - Não é possível excluir sessão de outro usuário.
  - **Feature tests:** *(Pest)* `deleting_current_promotes_most_recent_remaining`; `deleting_the_only_session_creates_an_empty_one`; `deleting_foreign_session_is_forbidden`. *(Vitest)* `delete_requires_explicit_confirmation`.
  - **Traces:** US-11.3, US-1.4

---

## Phase 20: Fechamento — paridade, qualidade e entrega

**Goal:** Fechar o MVP: cobertura cruzada, estática verde e o repositório pronto para quem clonar. · **Depends on:** Phase 19 · **Covers:** todas as histórias

### Phase 20.1: Verificação de paridade

- [ ] **Task:** Conferir a paridade funcional com o protótipo, item a item, e registrar as divergências deliberadas.
  - **Acceptance criteria:**
    - Cada Core Workflow do project-description (1 a 11) é percorrido na aplicação e confere com o protótipo.
    - As divergências deliberadas ficam registradas: catálogo em banco no lugar de constantes, sessões por usuário no lugar de `localStorage`, autosave com debounce de 800 ms/3 s no lugar dos 250 ms/7 s do protótipo, `checks` chaveado por id de item no lugar de `fase:índice`.
    - Nenhuma funcionalidade do protótipo fica sem equivalente ou sem justificativa escrita.
  - **Traces:** project-description → Core Workflows 1–11 · US-9.1, US-5.1

- [ ] **Task:** Fechar a cobertura de testes de isolamento entre usuários, ponta a ponta.
  - **Acceptance criteria:**
    - Existe teste de feature cobrindo leitura, escrita e exclusão cruzadas entre dois usuários, em **todas** as rotas de sessão.
    - Nenhuma rota autenticada aceita `user_id` do cliente.
    - Nenhuma resposta da aplicação contém sessão de terceiro em nenhum caminho, incluindo o carregamento inicial via Inertia.
  - **Feature tests:** *(Pest)* `cross_user_read_write_delete_is_blocked_on_every_session_route` → uma asserção por rota; `inertia_boot_never_leaks_foreign_sessions`.
  - **Traces:** US-1.4

### Phase 20.2: Qualidade e entrega

- [ ] **Task:** Deixar a cadeia de qualidade verde e automatizada.
  - **Acceptance criteria:**
    - `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, `npm run lint`, `php artisan test` e `npm run test` todos verdes.
    - A suíte roda do zero num banco limpo (`migrate:fresh --seed`) sem depender de estado prévio.
    - As duas suítes rodam em CI a cada push, com o mesmo conjunto de comandos.
  - **Traces:** project-description → Tech Stack (Testes, Análise estática, Formatação)

- [ ] **Task:** Escrever o `README.md` de quem clona o projeto público.
  - **Acceptance criteria:**
    - Documenta as duas trilhas de ambiente: Sail (container) e nativa (`php artisan serve` + `npm run dev`).
    - Explica que o catálogo é seedado e que editá-lo é editar o seeder, nunca a interface.
    - Aponta `.spec/init/design/pranchetasystemdesign.html` como referência visual e comportamental congelada.
    - Registra o que ficou fora do v1: modo apresentação, templates de partida, histórico entre sessões, autolayout, BFF, Redis e recuperação de senha.
  - **Traces:** project-description → Overview, Tech Stack · user-stories → Overview

## Open Questions

- **Caminho da referência de design.** Os artefatos foram movidos de `docs/design/` para `.spec/init/design/`, e o diretório `docs/` ficou vazio. O `project-description.md` ainda aponta para `docs/design/pranchetasystemdesign.html` em dois lugares (Overview e Tech Stack). Vale corrigir o project-description — ou manter uma cópia em `docs/` — antes que um agente siga o caminho morto.
- **Divergência no formato de `checks`.** O exemplo de payload do `project-description.md` usa `{"3:1": true}` (fase:índice, herdado do protótipo); o `database-schema.md`, mais recente, fixou o chaveamento por `checklist_items.id`. O plano segue o schema. Confirmar que o exemplo do project-description está superado.
- **Nível inicial do PHPStan/Larastan.** A Phase 1.3 configura a ferramenta, mas o nível de partida e a meta de subida continuam indefinidos.
- **~~Versão do Inertia~~ (resolvida).** O plano dizia Inertia 2, mas o starter kit oficial do Laravel 13 entrega Inertia 3. Decisão: seguir o starter kit (Inertia 3). Phase 1.1 e o `project-description.md` já refletem isso.
- **Importação do legado.** Se as sessões do artifact antigo forem importáveis, entra uma fase nova: `session_origins` como lookup, `legacy_id` em `training_sessions` para idempotência, e o parser do `localStorage['sd-prancheta-v1']`. Nada disso foi planejado.
- **Hospedagem e domínio.** Sem definição, a Phase 20.2 fecha em CI e README — não há fase de deploy.
- **Recuperação de senha.** Fora do v1 por decisão explícita. Sem ela, quem esquecer a senha perde a conta e todas as sessões, e o projeto é público. Definir se entra logo depois do v1.
