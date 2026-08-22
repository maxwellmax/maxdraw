# Prancheta de System Design — Database Schema

<!-- inputs: project-description.md@sha256:bed6595e6025 user-stories.md@sha256:9a633e6f2576 -->

## Overview

O modelo se divide em duas metades que quase não se tocam. De um lado, o **catálogo**: imutável em runtime, populado por seeder versionado e apenas lido pela aplicação — **problemas** (14) com suas listas de requisitos, escala e tópicos, **componentes** (28) agrupados em **categorias** (6, a única fonte de cor), **tipos de ligação** (9), **fases** (5) e seus **itens de checklist** (25). Do outro lado, o dado do usuário: **usuários** e suas **sessões de treino**, sendo a sessão a única tabela que a aplicação escreve durante o uso.

A **sessão de treino** é deliberadamente gorda: `nodes`, `edges`, `checks` e `estimate` são **colunas JSON**, conforme o contrato de autosave já fechado no project-description — o diagrama é salvo inteiro, numa transação, e nunca decomposto em linhas. Não existe `session_nodes` nem `session_edges`: o motor do canvas é a autoridade sobre essa estrutura, e o banco a guarda como um bloco. A sessão corrente é derivada de `last_opened_at`, não de um ponteiro em `users`.

Convenções em vigor: **Laravel 13 / Eloquent** — tabelas plurais snake_case, `id` bigint auto-incremento, FK `<singular>_id`, `created_at`/`updated_at` em todas as tabelas de domínio. **Nenhuma coluna enum ou string categórica**: todo campo de valor predefinido (nível do problema, tipo de item, categoria, tipo de ligação, modo de numeração, duração, modo de estimativa) é FK para tabela lookup, seedada junto ao catálogo. **Sem soft delete** em nenhuma tabela — as US-1.3 e US-11.3 exigem remoção efetiva, resolvida por `on delete cascade`.

## Schema (DBML)

```dbml
// ─────────────────────────────────────────────────────────────
// LOOKUPS — todos populados pelo seeder versionado do catálogo
// ─────────────────────────────────────────────────────────────

Table problem_levels {
  id bigint [pk, increment]
  name varchar(40) [not null, note: 'Base, Intermediário, Avançado']
  slug varchar(40) [unique, not null]
  position smallint [not null, note: 'ordem de dificuldade: 1..3']
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table problem_item_types {
  id bigint [pk, increment]
  name varchar(60) [not null, note: 'Requisito funcional, Escala alvo, Tópico cobrado']
  slug varchar(40) [unique, not null, note: 'requirement | scale | topic']
  description varchar(255) [null]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table component_categories {
  id bigint [pk, increment]
  name varchar(60) [not null, note: 'Cliente, Rede & Borda, Computação, Dados, Assíncrono, Operação']
  slug varchar(40) [unique, not null, note: 'client | edge | compute | data | async | ops']
  color_token varchar(40) [unique, not null, note: 'token CSS do protótipo: --c-client … — única fonte de cor do sistema']
  position smallint [not null, note: 'ordem na paleta']
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table link_types {
  id bigint [pk, increment]
  name varchar(60) [not null, note: 'rótulo no menu: HTTP / REST, gRPC, WebSocket…']
  slug varchar(20) [unique, not null, note: 'http|grpc|ws|event|query|cache|repl|batch|retry — é o edges[].kind do JSON']
  badge_label varchar(12) [not null, note: 'selo na seta: HTTP, gRPC, WS, async, query, cache, replica, batch, retry']
  dash_array varchar(20) [null, note: 'null = traço contínuo; 5 4.5 para event/repl/batch; 2 4.5 para retry']
  is_bidirectional_default boolean [not null, default: false, note: 'true só em ws']
  gloss varchar(255) [not null, note: 'linha de ensino exibida na legenda automática']
  position smallint [not null]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table sequence_modes {
  id bigint [pk, increment]
  name varchar(60) [not null, note: 'Sem números, Ordem de saída de cada bloco, Sequência do fluxo inteiro']
  slug varchar(10) [unique, not null, note: 'off | out | flow — é o seq_mode da API']
  legend_text varchar(255) [not null, note: 'texto exibido na seção Sequência da legenda']
  position smallint [not null, note: 'ordem do ciclo do botão 1→2: out → flow → off']
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table session_durations {
  id bigint [pk, increment]
  minutes smallint [unique, not null, note: '30, 45, 60 — a API continua falando duration_minutes']
  is_default boolean [not null, default: false, note: 'true apenas em 45']
  position smallint [not null]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table estimate_modes {
  id bigint [pk, increment]
  name varchar(60) [not null, note: 'Por usuários, Por volume mensal']
  slug varchar(20) [unique, not null, note: 'user | month — gravado dentro da coluna JSON estimate, validado contra este slug']
  highlighted_row varchar(60) [not null, note: 'linha destacada na saída: Escritas por dia | Escritas por mês']
  position smallint [not null]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

// ─────────────────────────────────────────────────────────────
// CATÁLOGO — domínio somente-leitura em runtime
// ─────────────────────────────────────────────────────────────

Table problems {
  id bigint [pk, increment]
  slug varchar(40) [unique, not null, note: 'url, feed, chat, video, ride, drive, rate, notif, typeahead, tickets, crawler, metrics, pay, leaderboard']
  name varchar(120) [not null]
  tag varchar(80) [not null, note: 'etiqueta de tema: Chave-valor · Cache']
  problem_level_id bigint [ref: > problem_levels.id, not null]
  context text [not null, note: 'enunciado em prosa, exibido em Source Serif 4']
  position smallint [not null, note: 'ordem no seletor']
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp

  indexes {
    (problem_level_id, position)
  }
}

Table problem_items {
  id bigint [pk, increment]
  problem_id bigint [ref: > problems.id, not null, note: 'on delete cascade']
  problem_item_type_id bigint [ref: > problem_item_types.id, not null]
  position smallint [not null, note: 'ordem dentro da própria lista']
  content text [not null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (problem_id, problem_item_type_id, position) [unique]
  }
}

Table components {
  id bigint [pk, increment]
  component_category_id bigint [ref: > component_categories.id, not null]
  slug varchar(40) [unique, not null, note: 'browser, cdn, api, sql, dlq… — é o nodes[].type do JSON']
  name varchar(80) [not null, note: 'nome longo: DLQ — fila de falhas']
  short_name varchar(60) [not null, note: 'rótulo padrão do bloco: DLQ; igual a name quando não há forma curta']
  icon_key varchar(40) [not null, note: 'chave do ícone SVG no motor do canvas']
  position smallint [not null, note: 'ordem dentro da categoria na paleta']
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp

  indexes {
    (component_category_id, position)
  }
}

Table phases {
  id bigint [pk, increment]
  slug varchar(40) [unique, not null]
  name varchar(120) [not null, note: 'Requisitos & escopo, Estimativas de capacidade…']
  weight decimal(4,3) [not null, note: '0.110, 0.110, 0.180, 0.270, 0.330 — soma exata 1.000']
  position smallint [unique, not null, note: '1..5, ordem cronológica do roteiro']
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table checklist_items {
  id bigint [pk, increment]
  phase_id bigint [ref: > phases.id, not null, note: 'on delete cascade']
  position smallint [not null, note: 'ordem dentro da fase']
  content varchar(255) [not null, note: 'afirmação verificável']
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp

  indexes {
    (phase_id, position) [unique]
  }
}

// ─────────────────────────────────────────────────────────────
// USUÁRIO E TREINO — a única metade que a aplicação escreve
// ─────────────────────────────────────────────────────────────

Table users {
  id bigint [pk, increment]
  name varchar(255) [not null]
  email varchar(255) [unique, not null]
  email_verified_at timestamp [null, note: 'coluna do starter kit; verificação não é exigida no v1 (US-1.1)']
  password varchar(255) [not null, note: 'hash bcrypt/argon — nunca texto plano']
  remember_token varchar(100) [null, note: '"Lembrar de mim" (US-1.2)']
  created_at timestamp
  updated_at timestamp
}

Table training_sessions {
  id bigint [pk, increment]
  user_id bigint [ref: > users.id, not null, note: 'on delete cascade — excluir a conta apaga as sessões (US-1.3)']
  problem_id bigint [ref: > problems.id, null, note: 'null enquanto o usuário não escolheu; on delete restrict']
  session_duration_id bigint [ref: > session_durations.id, not null, note: 'default: a duração marcada is_default (45)']
  sequence_mode_id bigint [ref: > sequence_modes.id, not null, note: 'default: out; valor inválido é normalizado para out (US-4.3)']
  elapsed_seconds int [not null, default: 0, note: 'tempo decorrido; retomado sempre pausado']
  notes text [null, note: 'máx. 5.000 caracteres, validado na FormRequest (US-6.3)']
  nodes json [not null, note: 'array de { id, type, label, x, y } — máx. 200 nós; type existe em components.slug']
  edges json [not null, note: 'array de { id, from, to, kind, label, dashed, bidir } — máx. 400 arestas; a ordem do array É a ordem de saída (US-4.4)']
  checks json [not null, note: 'mapa checklist_item_id => true; ausente equivale a desmarcado']
  estimate json [not null, note: '{ mode, dau, act, per_month, ratio, size, peak, ret } — mode ∈ estimate_modes.slug']
  last_opened_at timestamp [not null, note: 'a sessão corrente é o MAX(last_opened_at) do usuário']
  created_at timestamp
  updated_at timestamp

  indexes {
    (user_id, last_opened_at)
    (user_id, created_at)
  }
}
```

## Relationships

- Um **usuário** tem muitas **sessões de treino**; excluir o usuário apaga suas sessões em cascata.
- Uma **sessão de treino** pertence a um **usuário** (obrigatório) e, opcionalmente, a um **problema** — nasce sem problema até o seletor ser usado.
- Uma **sessão de treino** pertence a uma **duração de sessão** e a um **modo de numeração**.
- Um **problema** pertence a um **nível de problema** e tem muitos **itens de problema**.
- Um **item de problema** pertence a um **problema** e a um **tipo de item** — as três listas (requisitos, escala, tópicos) vivem na mesma tabela, separadas pelo tipo e ordenadas por `position`.
- Um **componente** pertence a uma **categoria de componente**; a categoria é a única fonte de cor do bloco.
- Uma **fase** tem muitos **itens de checklist**; cada item pertence a exatamente uma fase.
- **Tipos de ligação**, **modos de numeração**, **durações** e **modos de estimativa** são catálogos de referência: os dois primeiros são referenciados por slug de dentro do JSON (`edges[].kind`) ou por FK (`sequence_mode_id`); os dois últimos por FK e por slug dentro de `estimate`.
- Não há nenhuma relação muitos-para-muitos no v1 — **nenhuma tabela pivô existe**. O checklist, que seria o candidato natural a pivô `checklist_item_training_session`, é gravado como a coluna JSON `checks` por decisão explícita do contrato de autosave.

## Lookup Table Seeds

**problem_levels** (3): `base` Base (1) · `intermediate` Intermediário (2) · `advanced` Avançado (3)

**problem_item_types** (3): `requirement` Requisito funcional · `scale` Escala alvo · `topic` Tópico cobrado (o gabarito — só se abre depois de terminar)

**component_categories** (6): `client` Cliente `--c-client` · `edge` Rede & Borda `--c-edge` · `compute` Computação `--c-compute` · `data` Dados `--c-data` · `async` Assíncrono `--c-async` · `ops` Operação `--c-ops`

**link_types** (9) — `slug` · selo · `dash_array` · mão dupla · glosa:
- `http` · HTTP · null · não · "requisição e resposta; o chamador fica esperando"
- `grpc` · gRPC · null · não · "RPC binário entre serviços, com contrato tipado"
- `ws` · WS · null · **sim** · "canal aberto nos dois sentidos; o servidor empurra"
- `event` · async · `5 4.5` · não · "o produtor não espera resposta; entrega desacoplada"
- `query` · query · null · não · "leitura ou escrita no banco; conta no QPS dele"
- `cache` · cache · null · não · "consulta antes do banco; pode dar miss"
- `repl` · replica · `5 4.5` · não · "cópia do dado para outro nó; há atraso de replicação"
- `batch` · batch · `5 4.5` · não · "volume grande em janelas; fora do caminho do usuário"
- `retry` · retry · `2 4.5` · não · "estourou as tentativas e foi para a DLQ; ninguém consumiu"

**sequence_modes** (3): `out` Ordem de saída de cada bloco (padrão, position 1) · `flow` Sequência do fluxo inteiro (2) · `off` Sem números (3)

**session_durations** (3): 30 · **45 (`is_default = true`)** · 60

**estimate_modes** (2): `user` Por usuários → destaca "Escritas por dia" · `month` Por volume mensal → destaca "Escritas por mês"

**problems** (14): `url` Encurtador de URL · `feed` Feed de rede social · `chat` Mensageria em tempo real · `video` Streaming de vídeo · `ride` Carona sob demanda · `drive` Armazenamento de arquivos · `rate` Rate limiter distribuído · `notif` Sistema de notificações · `typeahead` Autocomplete de busca · `tickets` Venda de ingressos · `crawler` Web crawler · `metrics` Métricas e monitoramento · `pay` Carteira e pagamentos · `leaderboard` Ranking em tempo real — cada um com `context`, `tag`, nível, e seus `problem_items` das três listas.

**components** (28): *client* browser, mobile, thirdparty · *edge* dns, cdn, waf, lb, gateway · *compute* api, graphql, grpc, service, worker, cron, ws · *data* sql, replica, nosql, cache, blob, search, warehouse · *async* queue, stream, dlq (`DLQ — fila de falhas` / curto `DLQ`) · *ops* auth, monitor, config

**phases** (5) e **checklist_items** (25 = 4+5+5+5+6): Requisitos & escopo 0.110 (4) · Estimativas de capacidade 0.110 (5) · API & modelo de dados 0.180 (5) · Desenho de alto nível 0.270 (5) · Escala & trade-offs 0.330 (6) — conteúdo literal do protótipo `docs/design/pranchetasystemdesign.html`.

## Notes & Conventions

- **Sem soft delete em lugar nenhum.** US-1.3 manda a exclusão da conta apagar as sessões em cascata e US-11.3 manda a exclusão da sessão remover todo o conteúdo dela; `on delete cascade` em `training_sessions.user_id` cumpre as duas ao pé da letra, sem linha fantasma na lista de sessões.
- **`training_sessions` é o nome da tabela de treino, não `sessions`.** `sessions` é a tabela do driver de sessão do Laravel — colidir seria um erro de migration no primeiro `php artisan session:table`.
- **Tabelas de infraestrutura do starter kit não são modeladas aqui**: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` e `password_reset_tokens` vêm das migrations padrão do Laravel 13. Recuperação de senha está fora do v1 (a tabela existe, o fluxo não é exposto).
- **Nenhuma coluna enum ou string categórica.** Onde o protótipo usava string (`lv`, `kind`, `seqMode`, `est.mode`, `dur`), o schema usa FK para lookup. As duas exceções são slugs **dentro** de colunas JSON — `edges[].kind` e `estimate.mode` —, que não são colunas e por isso não podem levar FK; a integridade delas é garantida por validação na FormRequest contra `link_types.slug` e `estimate_modes.slug` (US-8.1: "tipos de componente e de ligação precisam existir no catálogo").
- **`nodes` / `edges` / `checks` / `estimate` são JSON por contrato**, não por comodidade: o project-description fixa o autosave gravando o estado inteiro numa transação. MySQL 8 tem `json` nativo; o SQLite dos testes guarda como texto e o cast `array` do Eloquent cobre os dois.
- **A ordem das arestas é posicional.** US-4.4 é explícita: "não existe campo de ordem por aresta" — a posição no array `edges` é a ordem de saída, e é ela que governa a travessia do modo `flow`. Nenhuma coluna `order` foi criada para isso.
- **`checks` é chaveado por `checklist_items.id`**, não pelo par `fase:índice` do protótipo — assim reordenar o seeder não reinterpreta marcações já gravadas.
- **Limites do payload são validação, não coluna**: 200 nós, 400 arestas, 60 caracteres de rótulo (bloco e seta) e 5.000 de notas são rejeitados com 422 na FormRequest (US-3.1, US-3.3, US-3.2, US-4.2, US-6.3). `notes` é `text` justamente para o limite ser regra de aplicação, revisável sem migration.
- **Sessão corrente = `MAX(last_opened_at)` por usuário.** Não há ponteiro em `users`: excluir a corrente promove a mais recente restante sem nenhum update de manutenção, e não existe estado onde o ponteiro aponta para linha morta. O índice `(user_id, last_opened_at)` serve tanto essa consulta quanto a lista ordenada da US-11.1.
- **`problem_id` é nullable e `on delete restrict`.** Sessão nova nasce sem problema (US-11.2) e o seletor abre sozinho; e nenhum problema do catálogo pode sumir por baixo de uma sessão que o referencia.
- **`is_active` nos lookups** existe para aposentar item de catálogo sem apagar linha referenciada por sessão antiga — desativado some da paleta/menu, mas continua renderizando o que já foi desenhado.
- **Nenhum upload de arquivo no v1**, portanto nenhuma coluna `_path` e nenhuma tabela de anexos: o export SVG é gerado no cliente a partir do mesmo estado da tela e baixado direto, sem passar pelo servidor (US-9.1).
- **Estado da legenda não vai para o banco.** US-5.2 é explícita: recolhida/expandida é preferência do navegador, guardada em `localStorage`, não na sessão.
- **Cor vem sempre de `component_categories.color_token`.** Nenhuma outra tabela tem coluna de cor — `link_types` define traço e selo, nunca cor; o selo herda a cor da categoria do nó de origem.

### Cobertura dos Key Concepts

| Key Concept | Table(s) |
|-------------|----------|
| Sessão de treino | `training_sessions` |
| Problema | `problems`, `problem_items`, `problem_levels`, `problem_item_types` |
| Componente (bloco) | `components` |
| Categoria | `component_categories` |
| Ligação (aresta) | `training_sessions.edges` (coluna JSON, por contrato de autosave) |
| Tipo de ligação | `link_types` |
| Numeração de sequência | `sequence_modes` + `training_sessions.sequence_mode_id` |
| Legenda automática | — não persistida: derivada em runtime do diagrama (`nodes`/`edges`) mais `component_categories` e `link_types`; o único estado dela (recolhida) é preferência de navegador |
| Fase | `phases` |
| Item de checklist | `checklist_items` + `training_sessions.checks` |
| Estimativa de capacidade | `estimate_modes` + `training_sessions.estimate` |
| Duração | `session_durations` + `training_sessions.session_duration_id` |
| Export SVG | — não persistido: gerado no cliente e baixado; nenhum arquivo trafega ou é armazenado |
| Catálogo | `problems`, `problem_items`, `components`, `component_categories`, `phases`, `checklist_items`, `link_types`, `problem_levels`, `problem_item_types`, `sequence_modes`, `session_durations`, `estimate_modes` |
| Motor do canvas | — não persistido: módulo TypeScript no cliente; produz e consome as colunas JSON da sessão |
| Hospedagem do projeto público | — não persistido: pendência de infraestrutura, não é entidade |
| Importação do legado | — não persistido: pendência em aberto; se aprovada, exige coluna de origem em `training_sessions` |
| Nível do PHPStan/Larastan | — não persistido: configuração de ferramenta |
| Redis | — não persistido: fora do v1; entraria como driver de cache/fila, sem tabela de domínio |

## Open Questions

- **Importação do legado.** Se as sessões do artifact antigo (`localStorage['sd-prancheta-v1']`) forem importáveis, `training_sessions` precisa distinguir origem — uma FK para um lookup `session_origins` (`app` / `imported`) mais, possivelmente, `legacy_id varchar` para idempotência da importação. Sem a decisão, nenhuma das duas colunas foi criada.
- **Recuperação de senha.** Fora do v1 por decisão explícita. A tabela `password_reset_tokens` do starter kit existirá de qualquer forma; o que falta decidir é se o fluxo é exposto antes do projeto público ir ao ar — sem impacto de schema.
- **Retenção de sessões.** Nada no spec limita quantas sessões um usuário acumula. Se um dia houver expurgo automático, o índice `(user_id, created_at)` já serve; nenhuma coluna de política foi criada.
