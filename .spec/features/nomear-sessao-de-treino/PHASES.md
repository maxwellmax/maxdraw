# Phases: nomear-sessao-de-treino

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/nomear-sessao-de-treino/PHASES.md`.

Ambiente: host, sem Sail. Gate: `composer ci:check`. Trabalho: `php artisan test --compact --filter=...` e `npm test`.

## Phase 1: Coluna name no schema e no model

Antes de implementar, leia:
1. `.spec/features/nomear-sessao-de-treino/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nomear-sessao-de-treino/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Migration aditiva `add_name_to_training_sessions_table`
      Arquivos: `database/migrations/<timestamp>_add_name_to_training_sessions_table.php` (novo)
      Mudança: criar com `php artisan make:migration add_name_to_training_sessions_table --no-interaction` em comando isolado (nunca encadeado com `&&`). `up()`: `Schema::table('training_sessions', ...)` com `$table->string('name')->nullable()->after('problem_id')`. Sem backfill — a coluna nullable já nasce nula em toda linha preexistente. `down()`: só `dropColumn('name')`; sem FK e sem índice, portanto sem `dropForeign`. PHPDoc `/** */` em `up()`/`down()` explicando o porquê. O teto de 60 fica na FormRequest, não no varchar.
      Cobre: RF-01, RF-03, RNF-03
      Acceptance criteria: `php artisan migrate:fresh --seed --force` roda limpo; `Schema::hasColumn('training_sessions', 'name')` é true e a coluna é nullable; a migration não cria índice, FK nem coluna `enum`; `down()` contém apenas `dropColumn('name')`.
      Testes: `tests/Feature/Migrations/TrainingSessionsMigrationTest.php` — coberto por T03 e T11
- [ ] T02 — `TrainingSession`: `#[Fillable]` e `@property`
      Arquivos: `app/Models/TrainingSession.php`
      Mudança: acrescentar `'name'` ao `#[Fillable]` logo após `'problem_id'` e `@property string|null $name` no bloco PHPDoc, na mesma posição relativa. Nenhum cast, nenhum Enum, nenhum lookup, nenhuma relação — `name` é texto livre do usuário.
      Cobre: RF-01
      Acceptance criteria: `TrainingSession::create([... 'name' => 'X'])` persiste e um `fresh()` devolve `X` idêntico; `composer types:check` (Larastan nível 7, sem baseline) passa; `casts()` não ganhou entrada nova.
      Testes: `composer types:check` + cobertura em T08/T11
- [ ] T03 — TA-01: lista exata de colunas de `training_sessions`
      Arquivos: `tests/Feature/Migrations/TrainingSessionsMigrationTest.php`
      Mudança: emenda mecânica aprovada — incluir `'name'` no array de `toEqualCanonicalizing` do teste `training_sessions_table_matches_the_schema` (linha ~41) e acrescentar `->and($columns['name']['nullable'])->toBeTrue()` ao bloco de nulabilidade logo abaixo. Nenhum teste removido.
      Cobre: TA-01, RF-01
      Acceptance criteria: `php artisan test --compact --filter=TrainingSessionsMigrationTest` verde; a lista de colunas do teste contém `name`; nenhuma asserção preexistente foi apagada.
      Testes: `tests/Feature/Migrations/TrainingSessionsMigrationTest.php` — o próprio teste

## Phase 2: Contrato do servidor — Resource, validação e gravação

Antes de implementar, leia:
1. `.spec/features/nomear-sessao-de-treino/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nomear-sessao-de-treino/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T04 — `TrainingSessionResource` emite `name`
      Arquivos: `app/Http/Resources/TrainingSessionResource.php`
      Mudança: `'name' => $this->name,` imediatamente após `'problem_id'` em `toArray()` — a posição é contrato (TA-02 assere a lista ordenada). `#[PreserveKeys]` permanece na classe e `user_id` continua fora; docblock atual intacto.
      Cobre: RF-08, CT-02
      Acceptance criteria: `array_keys` da saída do Resource traz `name` na segunda posição, logo após `problem_id`; `grep -n "user_id" app/Http/Resources/TrainingSessionResource.php` retorna 0 ocorrências; o atributo `#[PreserveKeys]` continua na classe.
      Testes: `tests/Feature/Sessions/BoardPageTest.php` (T05) e `tests/Feature/Sessions/TrainingSessionCrudTest.php` (T10)
- [ ] T05 — TA-02: lista ordenada de chaves do Resource
      Arquivos: `tests/Feature/Sessions/BoardPageTest.php`
      Mudança: emenda mecânica aprovada — inserir `'name',` logo após `'problem_id',` no `expect(array_keys($session))->toBe([...])` do teste `the_board_payload_never_leaks_the_owner_column` (linha ~204). Nenhum teste removido.
      Cobre: TA-02, RF-08
      Acceptance criteria: `php artisan test --compact --filter=BoardPageTest` verde; a lista do teste tem `name` logo após `problem_id`; a asserção continua provando que `user_id` não aparece.
      Testes: `tests/Feature/Sessions/BoardPageTest.php` — o próprio teste
- [ ] T06 — Regra e normalização de `name` na FormRequest
      Arquivos: `app/Http/Requests/TrainingSessionUpdateRequest.php`
      Mudança: `private const MAX_SESSION_NAME = 60;` junto das demais constantes de limite. Regra `'name' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_SESSION_NAME]`. Em `prepareForValidation()`, método privado curto com PHPDoc que age SOMENTE quando a chave veio no payload (`$this->has('name')`) e o valor é string: apara com `trim()` e converte vazio em `null`. Nunca injetar a chave quando ausente. Mensagem PT-BR em `messages()`: `'name.max' => 'O nome da sessão tem no máximo '.self::inWords(self::MAX_SESSION_NAME).' caracteres.'`. `user_id` continua fora das regras.
      Cobre: RF-05, RF-06, CT-01
      Acceptance criteria: `"  Feed  "` grava `Feed`; `"   "` e `""` gravam `null`; omitir a chave não altera o nome gravado; `null` explícito apaga; 60 caracteres → 200 e 61 → 422 com mensagem PT-BR; número e array → 422; `grep -n "user_id" app/Http/Requests/TrainingSessionUpdateRequest.php` retorna 0 ocorrências.
      Testes: `tests/Feature/Sessions/SessionRenameTest.php` (T08) e `tests/Feature/Frontend/SessionSheetTest.php` (T22)
- [ ] T07 — `SessionStateWriter` grava `name`
      Arquivos: `app/Services/SessionStateWriter.php`
      Mudança: acrescentar `'name'` ao `Arr::only(...)` de `write()`. Nada mais — chaves ausentes do payload continuam intocadas, e é isso que dá a imutabilidade de RF-07 de graça. Sem apresentação, sem `request()`/`auth()` no service.
      Cobre: RF-04, RF-07, CT-01
      Acceptance criteria: um `PUT` com corpo `{"name":"X"}` grava o nome dentro da mesma `DB::transaction` e não altera nenhuma outra coluna; um autosave completo (sem `name`) continua não tocando a coluna; o `Arr::only` segue com a lista explícita de chaves.
      Testes: `tests/Feature/Sessions/SessionRenameTest.php` (T08)

## Phase 3: Cobertura Pest do rename

Antes de implementar, leia:
1. `.spec/features/nomear-sessao-de-treino/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nomear-sessao-de-treino/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T08 — Pest: rename ponta a ponta, validação e imutabilidade
      Arquivos: `tests/Feature/Sessions/SessionRenameTest.php` (novo, via `php artisan make:test --pest SessionRenameTest`)
      Mudança: cobrir RF-04 (round-trip `PUT` → `GET /api/sessions` devolve o nome exato; repetir substitui), RF-05 com datasets `validNames`/`invalidNames` de chaves descritivas (aparo, só-espaços → nulo, vazio → nulo, omissão preserva, `null` apaga, 60 passa, 61 dá 422 com mensagem PT-BR, número e array dão 422), RF-07 (sessão com diagrama, marcações, `elapsed_seconds = 742` e `last_opened_at` conhecido: comparação coluna a coluna após `fresh()` e ordem de `GET /api/sessions` idêntica antes e depois) e CT-01 (200/422/404). Factories para tudo; `seedCatalog()` em qualquer caso que bata em `/prancheta`.
      Cobre: RF-04, RF-05, RF-07, CT-01
      Acceptance criteria: `php artisan test --compact --filter=SessionRenameTest` verde; existem casos separados provando que só `name` e `updated_at` mudam e que a ordem da folha não muda; os dois datasets existem com duas ou mais entradas cada.
      Testes: `tests/Feature/Sessions/SessionRenameTest.php` — é o próprio teste
- [ ] T09 — Isolamento cruzado cobre o corpo do rename
      Arquivos: `tests/Feature/Sessions/CrossUserIsolationTest.php`
      Mudança: acrescentar `'name' => 'invadido'` ao corpo enviado pelo intruso em `cross_user_read_write_delete_is_blocked_on_every_session_route`. A rota `sessions.update` já está no dataset `sessionRoutes` e `sessionRowSnapshot()` compara todos os atributos, então a asserção de "nada mudou" passa a cobrir a coluna nova.
      Cobre: RF-06
      Acceptance criteria: usuário A pedindo rename de sessão de B recebe 404 (ou 403) e o `name` da sessão de B continua inalterado no banco; `php artisan test --compact --filter=CrossUserIsolationTest` verde, incluindo `no_authenticated_route_accepts_a_client_sent_user_id`.
      Testes: `tests/Feature/Sessions/CrossUserIsolationTest.php` — o próprio teste
- [ ] T10 — Pest: nasce sem nome e o nome viaja no fio
      Arquivos: `tests/Feature/Sessions/TrainingSessionCrudTest.php`
      Mudança: RF-02/CT-03 — `POST /api/sessions` com corpo vazio → 201 e `data.name` nulo; com `{"name":"X"}` → 201 e a linha gravada com `name` nulo (chave ignorada, não 422); `GET /prancheta` para usuário sem sessão cria a corrente com `name` nulo, com `seedCatalog()`. RF-08/CT-02 — `name` presente em cada item de `data[]` de `GET /api/sessions`, em `GET /api/sessions/{trainingSession}` e na resposta de `POST /api/sessions/{trainingSession}/open`; o mapa `checks` continua chaveado por `checklist_items.id`.
      Cobre: RF-02, RF-08, CT-02, CT-03
      Acceptance criteria: `php artisan test --compact --filter=TrainingSessionCrudTest` verde; `git diff app/Services/SessionCreator.php` é vazio; `TrainingSessionStoreRequest` continua com exatamente `problem_id` e `duration_minutes`.
      Testes: `tests/Feature/Sessions/TrainingSessionCrudTest.php` — é o próprio teste
- [ ] T11 — Pest: migration aditiva sobre base existente e reversível
      Arquivos: `tests/Feature/Migrations/TrainingSessionsMigrationTest.php`
      Mudança: RF-03 — inserir N sessões com diagrama pelo helper `insertTrainingSession()` que o arquivo já tem e asserir que 100% delas têm `name` nulo e que `problem_id`, `session_duration_id`, `show_connection_order`, `elapsed_seconds`, `notes`, `nodes`, `edges`, `checks`, `estimate` e `last_opened_at` continuam idênticos (JSON byte a byte). RNF-03 — reversibilidade: instanciar a migration de T01 e chamar `down()`/`up()` dentro do teste, asserindo que a coluna some e volta e que nenhuma sessão desaparece; se o ciclo se mostrar frágil no SQLite em memória, cair para asserção de fonte de que `down()` executa somente `dropColumn('name')`, registrando a escolha no corpo do teste.
      Cobre: RF-03, RNF-03
      Acceptance criteria: `php artisan test --compact --filter=TrainingSessionsMigrationTest` verde; existe caso provando N sessões preexistentes com `name` nulo e conteúdo JSON intacto; existe caso (ou asserção de fonte documentada) provando que o `down()` só remove a coluna nova.
      Testes: `tests/Feature/Migrations/TrainingSessionsMigrationTest.php` — é o próprio teste

## Phase 4: Cliente — arranjo da linha, avisos, transporte e baseline

Antes de implementar, leia:
1. `.spec/features/nomear-sessao-de-treino/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nomear-sessao-de-treino/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T12 — Arranjo da linha: `title`, `metaLabel` e o espelho do teto
      Arquivos: `resources/js/prancheta/sessions.ts`, `resources/js/prancheta/fixtures.ts`, `resources/js/types/board.ts`
      Mudança: `SessionSummary` ganha `name: string | null`; `SessionRow` ganha `title: string` e `metaLabel: string`, mantendo `problemName`, `date`, `durationLabel`, `elapsedLabel`, `blockCount`, `current`; `export const SESSION_NAME_MAX_LENGTH = 60;` com docblock, no molde de `NOTES_MAX_LENGTH`; `sessionRows()` monta `title` (nome aparado quando não vazio, senão `problemName`) e `metaLabel` (com nome: `problemName` primeiro, depois data, duração, tempo e blocos; sem nome: exatamente a cadeia de hoje). Em `fixtures.ts`, `name` nas três `SessionSummary` (com-nome, sem-nome e só-espaços) sem alterar ids, datas nem `problem_id`. Em `types/board.ts`, `name?: string | null` OPCIONAL em `SessionPayload`, para não quebrar `payloadWith()` de `prancheta/autosave.test.ts`. Nenhuma requisição neste módulo.
      Cobre: UI-01, UI-02, RF-05, RF-08
      Acceptance criteria: `npm run types:check` passa; `SESSION_NAME_MAX_LENGTH` vale 60; `sessionRows()` devolve `title` e `metaLabel` como strings prontas e `problemName` continua no tipo; nenhum `http`/`fetch`/`router` foi importado em `prancheta/sessions.ts`.
      Testes: `resources/js/prancheta/sessions.test.ts` — T13 (emenda) e T18 (casos novos)
- [ ] T13 — Emendar as asserções Vitest existentes da linha
      Arquivos: `resources/js/prancheta/sessions.test.ts`
      Mudança: o `toEqual` exato de `each_row_shows_date_problem_duration_and_time_used` passa a incluir `name`, `title` e `metaLabel`; as asserções de `problemName`, ordenação e desempate ficam como estão. Nenhum caso novo aqui.
      Cobre: UI-01, UI-02
      Acceptance criteria: `npm test` verde; o teste `each_row_shows_date_problem_duration_and_time_used` continua existindo com o mesmo nome e agora assere as três chaves novas.
      Testes: `resources/js/prancheta/sessions.test.ts` — o próprio teste
- [ ] T14 — Chaves de aviso do rename
      Arquivos: `resources/js/prancheta/warnings.ts`
      Mudança: duas chaves novas em `BOARD_WARNINGS`, uma frase cada, PT-BR, no estilo das existentes (dizendo o que continua valendo): `sessionRenameFailed` (falha de requisição) e `sessionNameTooLong` (excesso de caracteres avisado, nunca cortado, mesma política de `notesLimitReached`).
      Cobre: UI-03, RF-05
      Acceptance criteria: as duas chaves existem em `BOARD_WARNINGS` com texto PT-BR; nenhuma chave existente foi renomeada ou removida; `npm run types:check` e `npm run format:check` passam.
      Testes: `tests/Feature/Frontend/SessionSheetTest.php` (T22)
- [ ] T15 — `renameSession()` no transporte
      Arquivos: `resources/js/lib/sessionTransport.ts`
      Mudança: `export async function renameSession(id: number, body: { name: string | null }): Promise<SessionAck>` ao lado de `sendSessionState()`, pelo mesmo `http.getClient()`, `method: 'put'`, `url: update.url(id)` (Wayfinder), `headers: { Accept: 'application/json' }`, reusando `ackFrom()` e `rejectionFrom()`. O corpo é só `{ name }` — `SessionBody` não é importado nem montado aqui. Nenhuma rota nova.
      Cobre: RF-04, CT-01, RNF-02
      Acceptance criteria: a função existe e usa `update.url(id)`; o arquivo continua sem a string literal `/api/sessions` e sem `Bearer`; nenhuma rota nova aparece em `php artisan route:list --path=api`.
      Testes: `resources/js/prancheta/sessions.test.ts` (T18) e `tests/Feature/Frontend/SessionSheetTest.php` (T22)
- [ ] T16 — Setter de baseline no `SessionStore`
      Arquivos: `resources/js/prancheta/session.ts`
      Mudança: método público `setServerUpdatedAt(updatedAt: string | null): void` que escreve APENAS `this.serverUpdatedAt`, com comentário explicando o porquê (reusar `markSaved()` marcaria como salvo o payload de agora e o diagrama sujo deixaria de subir). `SessionBody` e `bodyFrom()` ficam exatamente como estão — incluir `name` ali é proibido.
      Cobre: RF-07, RF-04
      Acceptance criteria: `setServerUpdatedAt()` não toca `savedSignature` e `isDirty` continua verdadeiro depois de chamá-lo com o estado sujo; `grep -n "name" resources/js/prancheta/session.ts` não mostra a chave dentro de `SessionBody` nem de `bodyFrom()`; `npm run types:check` passa.
      Testes: `tests/Feature/Frontend/SessionSheetTest.php` (T22)

## Phase 5: Cliente — orquestração pura do rename

Antes de implementar, leia:
1. `.spec/features/nomear-sessao-de-treino/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nomear-sessao-de-treino/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T17 — Orquestração pura do rename
      Arquivos: `resources/js/prancheta/sessions.ts`
      Mudança: função pura no molde de `deleteIntent()`, com o transporte injetado como parâmetro (nenhuma requisição mora neste módulo): recebe a lista de `SessionSummary`, o id, o texto cru e o transporte; apara; se o aparado passar de `SESSION_NAME_MAX_LENGTH`, devolve aviso `sessionNameTooLong` sem chamar o transporte e sem cortar; caso contrário chama o transporte UMA vez com `{ name }` (`''` → `null`); sucesso devolve a lista com o nome aplicado no id mais o `updated_at` do ack; falha devolve aviso `sessionRenameFailed` com a lista anterior intacta. Nome e forma de retorno são livres — sugestão `commitSessionRename()` com união discriminada `{ status: 'saved' | 'warned' }`.
      Cobre: UI-03, RF-05, RNF-02
      Acceptance criteria: a função não importa `http`, `fetch`, `router` nem `lib/sessionTransport`; recebe o transporte por parâmetro; chama o transporte no máximo uma vez por invocação e zero vez quando o nome excede o teto; nenhum truncamento silencioso acontece.
      Testes: `resources/js/prancheta/sessions.test.ts` (T18)
- [ ] T18 — Vitest: precedência do título, metadados e orquestração
      Arquivos: `resources/js/prancheta/sessions.test.ts`
      Mudança: casos novos em `environment: 'node'`, com zero dependência nova e zero mudança no `include` do `vitest.config.ts` — (a) `title`: com nome+problema → nome; com nome sem problema → nome; sem nome com problema → nome do problema; sem nome sem problema → `Prancheta livre`; nome só de espaços cai em "sem nome". (b) `metaLabel`: com nome e problema "Feed de rede social" começa por esse texto e o `title` não o contém; com nome e sem problema começa por `Prancheta livre`; sem nome é idêntica à linha atual. (c) orquestração com transporte falso (`vi.fn()`): sucesso atualiza a linha com o nome aparado; falha dispara exatamente um aviso e a linha volta ao nome anterior; excesso avisa sem cortar e não chama o transporte; contagem de chamadas do transporte é 1 por rename bem-sucedido. Nomes dos testes em `snake_case`.
      Cobre: UI-01, UI-02, UI-03, RNF-02
      Acceptance criteria: `npm test` verde; existem os 4 casos de `title`, os 3 de `metaLabel` e os 4 de orquestração; `git diff package.json vitest.config.ts` é vazio.
      Testes: `resources/js/prancheta/sessions.test.ts` — é o próprio teste

## Phase 6: Cliente — folha de sessões e prancheta

Antes de implementar, leia:
1. `.spec/features/nomear-sessao-de-treino/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nomear-sessao-de-treino/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T19 — `SessionList.vue`: título, metadados e afford de renomear
      Arquivos: `resources/js/components/prancheta/SessionList.vue`
      Mudança: imprimir `{{ row.title }}` em `data-testid="session-row-name"` e `{{ row.metaLabel }}` em `data-testid="session-row-meta"`, tirando a montagem de texto do template. Preservar todos os `data-testid` existentes (`session-list`, `session-row`, `session-row-name`, `session-row-meta`, `session-current-badge`, `session-open`, `session-delete`, `session-empty`) e o selo de corrente. Acrescentar `PranchetaButton` com `data-testid="session-rename"` ao lado de "Abrir"/"Apagar"; estado local de edição (id em edição + rascunho) que troca o título por `PranchetaInput` com `data-testid="session-rename-input"`, pré-preenchido com o nome atual (vazio quando não houver) e focado ao abrir. `@keydown.enter` e `@blur` confirmam emitindo `rename: [sessionId, name]`; `@keydown.esc` fecha sem emitir — forma de `beginEdit`/`commit` de `CanvasNode.vue`, sem `contenteditable`. Raiz única; nenhuma requisição, nenhuma regra de negócio, nenhum `alert()`.
      Cobre: UI-01, UI-02, UI-03
      Acceptance criteria: o arquivo contém `data-testid="session-rename"` e `data-testid="session-rename-input"` e todos os oito testids anteriores; contém `{{ row.title }}` e `{{ row.metaLabel }}`; referencia `PranchetaInput`; tem handler de Enter, de blur e caminho de cancelamento por Escape; `grep -c "alert(" ` no arquivo é 0; `npm run lint:check`, `npm run format:check` e `npm run types:check` passam.
      Testes: `tests/Feature/Frontend/SessionSheetTest.php` (T21, T22)
- [ ] T20 — `Board.vue`: fio do rename e baseline da sessão corrente
      Arquivos: `resources/js/pages/Board.vue`
      Mudança: handler de `@rename` no `<SessionList>` que chama a orquestração de T17 passando `renameSession` (T15) e `savedSessions.value`. Sucesso: atualiza `savedSessions.value` no lugar (zero `router.visit`, zero `loadSessions()`, zero navegação Inertia) e, somente quando `sessionId === store.id`, chama `store.setServerUpdatedAt(ack.updated_at)` seguido de `autosave.saveLocal()`. Falha ou excesso: `warn('sessionRenameFailed')` / `warn('sessionNameTooLong')`. `startNewSession()` fica intocado: `await saveNow()` → `await createSession()` → `reloadBoard()`, sem diálogo e sem passo intermediário.
      Cobre: UI-03, UI-04, RF-07, RNF-02
      Acceptance criteria: renomear a sessão corrente com diagrama sujo e recarregar `/prancheta` preserva o rascunho local e não dispara `serverVersionIsNewer`; o caminho do rename não contém `router.visit` nem `loadSessions`; a realimentação do baseline está guardada por comparação com `store.id`; o fluxo de `new-session` continua `saveNow()` → `createSession()` → `router.visit(board.url(), { preserveState: false, preserveScroll: true })`; `npm run build` roda limpo.
      Testes: `tests/Feature/Frontend/SessionSheetTest.php` (T22)

## Phase 7: Contrato de fonte em PHP — TA-03, gesto e par de constantes

Antes de implementar, leia:
1. `.spec/features/nomear-sessao-de-treino/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nomear-sessao-de-treino/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T21 — TA-03: strings literais do template da linha
      Arquivos: `tests/Feature/Frontend/SessionSheetTest.php`
      Mudança: emenda mecânica aprovada no teste `escreve data, problema, duração escolhida e tempo usado em cada linha` (linhas 23-33): as asserções sobre `SessionList.vue` passam a ser `{{ row.title }}` e `{{ row.metaLabel }}`; as asserções sobre `prancheta/sessions.ts` (`date: formatSessionDate(...)`, `durationLabel:`, `elapsedLabel:`) continuam, acrescidas das linhas que montam `title` e `metaLabel`. Nenhum teste removido.
      Cobre: TA-03, UI-01, UI-02
      Acceptance criteria: `php artisan test --compact --filter=SessionSheetTest` verde; o teste continua existindo com o mesmo nome e assere as strings novas do template.
      Testes: `tests/Feature/Frontend/SessionSheetTest.php` — o próprio teste
- [ ] T22 — Contrato de fonte do rename e par de constantes travado
      Arquivos: `tests/Feature/Frontend/SessionSheetTest.php`
      Mudança: acrescentar ao dataset de `data-testid` as entradas `session-rename` e `session-rename-input` para `components/prancheta/SessionList.vue`, mantendo as nove existentes. Testes novos por asserção de fonte: gesto (handlers de Enter e blur, caminho de cancelamento por Escape, referência a `PranchetaInput`, ausência de `alert(`); avisos (`sessionRenameFailed` e `sessionNameTooLong` em `warnings.ts`, usados via `warn('...')` em `Board.vue`); transporte (`renameSession` em `lib/sessionTransport.ts` com `update.url(id)`, sem `/api/sessions` literal); RNF-02 (o caminho do rename em `Board.vue` não chama `router.visit` nem `loadSessions`, e o baseline é condicionado a `store.id`); restrição do `SessionBody` (`prancheta/session.ts` sem `name` em `SessionBody`/`bodyFrom`); custo zero de ferramental (`package.json` sem `@vue/test-utils` e sem `happy-dom`; `vitest.config.ts` com o `include` e o `environment` atuais); par travado `MAX_SESSION_NAME` × `SESSION_NAME_MAX_LENGTH` = `'60'` por regex, no molde de `DrillRoteiroTest`. Acrescentar ao dataset de `pranchetaTestNames()` os nomes `snake_case` dos testes Vitest de T18.
      Cobre: UI-03, UI-04, RF-04, RF-05, RNF-02
      Acceptance criteria: `php artisan test --compact --filter=SessionSheetTest` verde; o par de constantes é comparado e vale 60 dos dois lados; os dois testids novos estão no dataset e os antigos continuam; existe asserção de que `package.json` e `vitest.config.ts` não ganharam ferramental novo.
      Testes: `tests/Feature/Frontend/SessionSheetTest.php` — é o próprio teste

## Phase 8: Documentos de arquitetura e regra durável

Antes de implementar, leia:
1. `.spec/features/nomear-sessao-de-treino/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nomear-sessao-de-treino/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T23 — Documentos de arquitetura com o campo novo
      Arquivos: `docs/agents/api_contracts.md`, `docs/agents/data_model.md`, `docs/agents/domain_rules.md`
      Mudança: api_contracts — `"name"` logo após `"problem_id"` no exemplo de `GET /api/sessions` e no 201 de `POST /api/sessions` (valor `null`); na seção do `PUT`, linha do campo na tabela de regras (`sometimes`, `nullable`, `string`, `max:60`), a normalização nova no parágrafo de `prepareForValidation`, o teto na lista de causas de 422, e nota de que o rename usa envio dedicado (`renameSession()`) e não integra o `SessionBody`. data_model — linha `name` na tabela de colunas de `training_sessions` (string nullable, teto de 60 na FormRequest, texto livre, sem lookup) e atualização da contagem de migrations do parágrafo "Schema location". domain_rules — a tabela "A session is born exactly one way" ganha `name` = `null` at birth, com a nota de que a coluna nullable ausente do `create()` já nasce nula e que `SessionCreator` não muda; registrar que renomear não promove a corrente.
      Cobre: RNF-04
      Acceptance criteria: nenhum exemplo de payload de sessão nos três arquivos fica sem o campo `name` (verificável por `grep` nos blocos JSON); a tabela de colunas de `data_model.md` lista `name`; a tabela de nascimento de `domain_rules.md` lista `name`; `composer ci:check` termina com exit code 0.
      Testes: `php artisan test --compact --filter=OrderContractTest` (varre `docs/agents`) + `composer ci:check`
- [ ] T24 — Registrar como regra durável a proibição de `name` no `SessionBody`
      Arquivos: `.ai/rules/js-prancheta.md` (escrito pela ferramenta `record-rule` do Laravel Boost — não editar à mão)
      Mudança: chamar `record-rule` com `glob: resources/js/prancheta/**`, título curto (sugestão: "Nome da sessão não entra no SessionBody") e nota de poucas linhas explicando o porquê: o nome trafega pelo envio dedicado `renameSession()` de `lib/sessionTransport.ts`; incluí-lo em `SessionBody`/`bodyFrom()` o tornaria campo sujo ("sujo é derivado do payload") e reintroduziria o clobber entre abas; o `updated_at` da resposta realimenta `store.serverUpdatedAt` por `setServerUpdatedAt()` seguido de `saveLocal()` somente quando o id renomeado é o da corrente. Nunca usar memória nativa — só `.ai/rules` é compartilhado com o time e versionado.
      Cobre: RF-04, RF-07
      Acceptance criteria: `.ai/rules/js-prancheta.md` contém a nota nova citando `SessionBody` e `renameSession()`; o arquivo foi escrito por `record-rule` com o glob `resources/js/prancheta/**` e nenhuma nota preexistente foi apagada; `php artisan test --compact --filter=OrderContractTest` continua verde.
      Testes: `php artisan test --compact --filter=OrderContractTest` (varre `.ai/rules`) + `grep -n "SessionBody" .ai/rules/js-prancheta.md`
