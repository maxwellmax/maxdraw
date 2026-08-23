# Phases: ordem-explicita-de-conexoes

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/ordem-explicita-de-conexoes/PHASES.md`.

Ambiente: **host nativo, sem Sail**. Nunca prefixe comando com `./vendor/bin/sail`.

## Phase 1: Motor — campo `order` e densificação

Antes de implementar, leia:
1. `.spec/features/ordem-explicita-de-conexoes/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/ordem-explicita-de-conexoes/PLAN.md` — decomposição completa, dependências e riscos
3. `.ai/rules/canvas.md` — `resources/js/canvas/**` é TypeScript puro: sem Vue/Inertia, sem `document`/`window`

### Gate

Comando que fecha a fase: `npm test && npm run types:check && npm run lint:check && npm run build && php artisan test --compact`

### Tasks

- [ ] T01 — `Edge.order` no tipo do motor
      Arquivos: `resources/js/canvas/types.ts`, `tests/Feature/Frontend/CanvasSequenceTest.php`
      Mudança: acrescentar `order: number | null` ao tipo `Edge`, depois de `bidir`, documentando que a posição no array deixou de ter significado semântico e que `null` é "fora da sequência". Não tocar em `DiagramSnapshot` nem em `SessionState`. Atualizar no mesmo passo o regex de `CanvasSequenceTest.php` que trava a forma de `Edge` sem `order` — é esta task que o quebra de propósito.
      Cobre: RF-01
      Acceptance criteria: `resources/js/canvas/types.ts` declara `order: number | null` dentro de `export type Edge`; `php artisan test --compact --filter=CanvasSequence` passa com o regex exigindo o campo novo.
      Testes: `tests/Feature/Frontend/CanvasSequenceTest.php` — regex da forma de `Edge` com `order`
- [ ] T02 — Motor de ordem: `canvas/order.ts`
      Arquivos: `resources/js/canvas/order.ts` (novo), `resources/js/canvas/index.ts`
      Mudança: módulo TypeScript puro exportando `densify(edges)` (varre `edges` inteiro, órfã inclusa, ordena os não nulos por valor com desempate pela posição no array e reescreve `1..N`), `numberedCount(edges)`, `setOrder(edges, id, k)` (empurra `+1` quem ocupava `>= k` e redensifica) e `clearOrder(edges, id)` (grava `null` e redensifica). Aresta com `order === null` não conta para `N`, não ocupa posição e não desloca ninguém. A densificação é o único ponto de saída de toda mutação de ordem. Reexportar em `index.ts`.
      Cobre: RF-03, RF-04, RF-05, RF-06, RF-08, RF-09, RF-16
      Acceptance criteria: para qualquer sequência de operações, `sort(orders.filter(nonNull))` é `[1..N]` — nunca `[1,2,4,5]` nem duplicata; `setOrder(C,1)` sobre `[A=1,B=2,C=3]` produz `[C=1,A=2,B=3]` e `setOrder(A,3)` produz `[B=1,C=2,A=3]`; `clearOrder(B)` produz `A=1, B=null, C=2`; o arquivo não importa Vue/Inertia e não cita `document`/`window`.
      Testes: `resources/js/canvas/order.test.ts` (novo) — densificação, empurrão, remoção da sequência, 2 numeradas + 5 nulas ⇒ `N = 2`
- [ ] T03 — Mutações do diagrama gravam e redensificam
      Arquivos: `resources/js/canvas/diagram.ts`
      Mudança: `addEdge()` cria a aresta com `order: null` e a aresta pré-existente devolvida sem inserção fica intocada; `removeEdge()` e `removeNode()` chamam `densify()` uma única vez ao final da remoção; `snapshot()`/`restore()` continuam copiando `edges` inteiras e NÃO densificam.
      Cobre: RF-02, RF-03, RF-06, RF-07
      Acceptance criteria: ligação recém-criada tem `order === null` e o conjunto de `order` não nulos do diagrama fica inalterado; remover `B` de `[A=1,B=2,C=3]` deixa `[A=1,C=2]`; remover um bloco com 3 arestas numeradas de um diagrama com 6 numeradas deixa exatamente `[1,2,3]`; `restore()` de um snapshot esparso preserva os valores sem densificar.
      Testes: `resources/js/canvas/diagram.test.ts` — criação, remoção de aresta, remoção de bloco, restore sem densificação
- [ ] T04 — `CanvasEngine`: definir e limpar a ordem
      Arquivos: `resources/js/canvas/engine.ts`
      Mudança: `setEdgeOrder(id, k)` e `clearEdgeOrder(id)` passando por `mutateEdge()`, um passo de desfazer por commit; getter `numberedCount` para o clamp da UI e `orderOf(edge)` para os renderizadores. Não alterar ainda `seqMap`/`outSeq`/`moveSeq`/`setSequenceMode`.
      Cobre: RF-04, RF-09, RF-15
      Acceptance criteria: `setEdgeOrder` aumenta `undoDepth` em exatamente 1 e um desfazer restaura o mapa `id → order` de todas as arestas afetadas pelo empurrão; `clearEdgeOrder` idem; `numberedCount` devolve o `N` corrente.
      Testes: `resources/js/canvas/engine.test.ts` — desfazer de `setEdgeOrder` e de `clearEdgeOrder`
- [ ] T05 — Fixtures e cobertura Vitest do campo `order`
      Arquivos: `resources/js/canvas/fixtures.ts`, `resources/js/canvas/*.test.ts`, `resources/js/prancheta/fixtures.ts`, `tests/Feature/Frontend/CanvasSequenceTest.php`
      Mudança: `edgeFixture()` aceita `order` (default `null`) e todo objeto `Edge` montado à mão nos testes ganha o campo; o dataset "cobre no Vitest cada teste que a fase pede" de `CanvasSequenceTest.php` recebe os nomes dos testes de `order.test.ts` (os nomes de `outSeq_*`/`flowSeq_*`/`moveSeq_*` continuam por enquanto).
      Cobre: RF-01, RF-02, RF-03, RF-04, RF-06, RF-08, RF-09, RF-16
      Acceptance criteria: `npm run types:check` sem erro de propriedade faltante em `Edge`; `npm test` verde; `php artisan test --compact` verde; o dataset de nomes cita cada teste novo de `order.test.ts`.
      Testes: `resources/js/canvas/order.test.ts` + `tests/Feature/Frontend/CanvasSequenceTest.php` — cobertura por nome

## Phase 2: Motor — numeração automática por BFS

Antes de implementar, leia:
1. `.spec/features/ordem-explicita-de-conexoes/SPEC.md` — requisitos RIGID que esta fase cobre (RF-10, RF-17, RNF-01, RNF-02)
2. `.spec/features/ordem-explicita-de-conexoes/PLAN.md` — decomposição completa, dependências e riscos
3. `.ai/rules/canvas.md` — determinismo e pureza do motor

### Gate

Comando que fecha a fase: `npm test && npm run types:check && npm run lint:check && npm run build && php artisan test --compact`

### Tasks

- [ ] T06 — Numeração automática por BFS
      Arquivos: `resources/js/canvas/order.ts`
      Mudança: `autoNumber(edges, nodes, index)` numera todas as arestas vivas (`liveEdges`) por BFS determinística, sobrescrevendo qualquer `order` anterior, inclusive `null`. Raízes com o fallback de três degraus de `sequenceRoots()`: (1) nós de categoria cliente sem aresta de entrada (`isClientComponent`/`CLIENT_CATEGORY`), (2) demais nós sem entrada, (3) todos os nós. A travessia segue somente `from → to`, e `bidir` não conta como entrada em `from`. Desempate, dentro do degrau e na expansão, é a ordem do array `edges`; cada aresta é visitada uma vez. REGRA DA ÓRFÃ (decisão fechada, não reabrir): a aresta órfã não é visitada — mantém o `order` que tinha e é ranqueada DEPOIS de todas as vivas, preservando a ordem relativa entre as órfãs numeradas; órfã com `order === null` continua `null`; a `densify()` final fecha `[1..N]` com `N` incluindo as órfãs numeradas.
      Cobre: RF-10, RF-17, RF-03
      Acceptance criteria: depois de `autoNumber`, nenhuma aresta viva permanece com `order === null` e o conjunto de `order` não nulos é exatamente `[1..N]`; um diagrama com ciclo termina sem laço infinito; uma aresta `bidir` ocupa exatamente um `order` e um nó cuja única aresta incidente é uma `bidir` que dele parte continua elegível como raiz; num diagrama com 3 arestas vivas e 1 órfã numerada, as vivas ficam `1,2,3` na ordem da BFS, a órfã fica `4` e o conjunto é `[1..4]` com `N = 4`.
      Testes: `resources/js/canvas/order.test.ts` — raízes, ciclo, `bidir`, cobertura de toda aresta viva, órfã numerada ranqueada ao fim (e duas órfãs preservando a ordem relativa)
- [ ] T07 — `CanvasEngine.autoNumberOrder()`
      Arquivos: `resources/js/canvas/engine.ts`
      Mudança: método que empilha um único snapshot antes de chamar `autoNumber()` sobre o estado e devolve `boolean`.
      Cobre: RF-10, RF-15
      Acceptance criteria: um clique em auto-numerar aumenta `undoDepth` em exatamente 1; um desfazer restaura o mapa `id → order` de todas as arestas do diagrama de uma vez.
      Testes: `resources/js/canvas/engine.test.ts` — auto-numerar = 1 passo de desfazer
- [ ] T08 — Determinismo e desempenho do motor de ordem
      Arquivos: `resources/js/canvas/order.test.ts`, `tests/Feature/Frontend/CanvasSequenceTest.php`
      Mudança: testes de contrato não-funcional — 50 execuções consecutivas de `autoNumber` sobre o mesmo diagrama com 0 divergências no mapa `id → order`; `densify` + `autoNumber` sobre 200 nós / 400 arestas com mediana de 20 execuções abaixo de 16 ms. Somar os nomes dos novos testes ao dataset de `CanvasSequenceTest.php`.
      Cobre: RNF-01, RNF-02, RF-17
      Acceptance criteria: o teste de determinismo executa 50 vezes e compara mapas idênticos; o teste de desempenho mede a mediana (não a média) de 20 execuções no limite de payload e falha acima de 16 ms; `php artisan test --compact --filter=CanvasSequence` confere os dois nomes.
      Testes: `resources/js/canvas/order.test.ts` — determinismo (RNF-02) e desempenho (RNF-01)

## Phase 3: Cliente — badge, SVG e legenda

Antes de implementar, leia:
1. `.spec/features/ordem-explicita-de-conexoes/SPEC.md` — requisitos RIGID que esta fase cobre (UI-01, UI-05, UI-06, UI-07, RF-12, RF-15a)
2. `.spec/features/ordem-explicita-de-conexoes/PLAN.md` — decomposição completa, dependências e riscos
3. `.ai/rules/canvas.md`, `.ai/rules/prancheta.md` e `.ai/rules/js-prancheta.md` — motor sem DOM, `data-testid` como contrato, lógica fora do `.vue`

Nesta fase o menu de modo do toolbar fica **inerte** de propósito (não altera mais a numeração exibida); ele é removido na Phase 4.

### Gate

Comando que fecha a fase: `npm test && npm run types:check && npm run lint:check && npm run build && php artisan test --compact`

### Tasks

- [ ] T09 — Flag `showConnectionOrder` no estado do motor
      Arquivos: `resources/js/canvas/types.ts`, `resources/js/canvas/engine.ts`, `resources/js/prancheta/session.ts`, `resources/js/prancheta/fixtures.ts`
      Mudança: a flag vive em `SessionState` (`canvas/types.ts`), no lugar que `seqMode` ocupa hoje — decisão fechada, não reabrir. `SessionState` ganha `showConnectionOrder: boolean` ao lado de `seqMode` (que só sai na Phase 4); `CanvasEngine` expõe o getter e `setShowConnectionOrder(value)`, que NÃO empilha desfazer; `DiagramSnapshot` continua sem a flag. `recordFrom()` e `sessionRecordFixture()` preenchem a flag com `true`; o `SessionBody` ainda não muda.
      Cobre: RF-12, RF-15, RF-15a
      Acceptance criteria: alternar a flag N vezes não altera `undoDepth`; o tipo `DiagramSnapshot` não declara a flag e `serialize` continua gravando só `nodes` e `edges`; desfazer após alternar o toggle não reverte a flag.
      Testes: `resources/js/canvas/engine.test.ts` e `resources/js/canvas/undo.test.ts`
- [ ] T10 — Legenda: `ORDER_NAME` / `ORDER_GLOSS` no lugar do catálogo
      Arquivos: `resources/js/canvas/legend.ts`, `resources/js/canvas/engine.ts`
      Mudança: exportar `ORDER_NAME` e `ORDER_GLOSS` ao lado de `UNTYPED_NAME`/`UNTYPED_GLOSS`; `LegendSequence` perde o campo `mode`; `legendSequence()` deixa de chamar `seqMap()` e passa a existir quando houver pelo menos uma aresta viva com `order` não nulo e `showConnectionOrder === true`; `legendData()` perde o parâmetro `modes` e `CanvasEngine.legendData()` para de repassar `sequenceModeOptions`.
      Cobre: UI-07, RF-13
      Acceptance criteria: com ≥1 aresta numerada e a flag ligada, a legenda mostra a seção de sequência com o texto de `ORDER_GLOSS`; com a flag desligada ou sem aresta numerada, a seção não aparece; `LegendSequence` não declara `mode`; nenhuma string de legenda de sequência vem do servidor.
      Testes: `resources/js/canvas/legend.test.ts` — presença/ausência da seção e origem do texto
- [ ] T11 — SVG: pill sólido e `SEQ_LEAD` por dígitos
      Arquivos: `resources/js/canvas/svg.ts`
      Mudança: `seqDotMarkup()` vira pill sólido (fundo na cor da aresta, texto na cor do papel, cantos totalmente arredondados, largura mínima e padding horizontal crescendo com a contagem de dígitos); `SEQ_LEAD` deixa de ser fixo e passa a depender da contagem de dígitos; `buildSVG()` lê `edge.order` em vez de `seqMap()` e respeita `state.showConnectionOrder`; `SvgContext` perde `modes`; `seqSampleMarkup()` desenha o mesmo pill e a linha da legenda usa `ORDER_NAME`/`ORDER_GLOSS`.
      Cobre: UI-01, UI-06, UI-07
      Acceptance criteria: `order` de 1, 2 e 3 dígitos gera pill de largura crescente, legível e sem corte; com a flag desligada o markup não contém nenhum badge e os valores de `order` permanecem intactos no estado; aresta com `order === null` ou não desenhada não emite badge; a geometria do arquivo confere com a da tela.
      Testes: `resources/js/canvas/svg.test.ts` — dígitos, flag desligada, `order` nulo, paridade tela↔SVG
- [ ] T12 — Badge no chip e legenda da tela
      Arquivos: `resources/js/components/prancheta/EdgeChip.vue`, `resources/js/components/prancheta/LegendContent.vue`
      Mudança: `EdgeChip.vue` troca a prop `seq?: SequenceNumber | null` por `order?: number | null`; o badge `edge-chip-seq` vira pill sólido (`background: var(--ec)`, texto na cor do papel, `rounded-full`, `min-w` e padding por contagem de dígitos) e continua ANTES do label no DOM; `order === null` não renderiza nó de badge e o chip sem selo e sem label continua `bare`. `LegendContent.vue` importa `ORDER_NAME`/`ORDER_GLOSS`, remove o atributo `data-mode` do nó `legend-sequence` e usa o mesmo pill sólido.
      Cobre: UI-01, UI-05, UI-06, UI-07
      Acceptance criteria: no template do chip, o nó `edge-chip-seq` precede `edge-chip-badge` e `edge-chip-label`; o badge tem fundo preenchido e o label não (distinção por forma + preenchimento, não só por cor); aresta recém-criada não tem nó de badge no DOM; nenhum nó da legenda expõe `data-mode`; tela e SVG consomem a mesma constante (0 duplicação de string).
      Testes: `tests/Feature/Frontend/CanvasSequenceTest.php` e `tests/Feature/Frontend/CanvasLegendTest.php` — ordem no DOM, preenchimento, ausência de `data-mode`
- [ ] T13 — `Board.vue` desenha a partir de `edge.order`
      Arquivos: `resources/js/pages/Board.vue`
      Mudança: o computed `wires` deixa de chamar `engine.seqMap()` e passa a expor `order: engine.showConnectionOrder ? edge.order : null`, repassado ao `EdgeChip` como `:order="wire.order"`. O `edgeBar` continua com `seq: engine.outSeq()[edge.id] ?? null` até a Phase 4. Nenhuma regra de ordem mora na página.
      Cobre: UI-05, UI-06
      Acceptance criteria: `pages/Board.vue` não contém mais `engine.seqMap()`; o chip recebe `order`; desligar a flag remove todos os badges do DOM e religar restaura exatamente os mesmos números, sem renumeração.
      Testes: `tests/Feature/Frontend/CanvasSequenceTest.php` — a página passa `order` ao chip
- [ ] T14 — Guardas Pest da exibição
      Arquivos: `tests/Feature/Frontend/CanvasLegendTest.php`, `tests/Feature/Frontend/CanvasExportTest.php`, `tests/Feature/Frontend/CanvasSequenceTest.php`
      Mudança: reescrever as asserções que travam a forma antiga — assinatura de `legendData()` sem `sequenceModeOptions`, texto da legenda vindo de `ORDER_GLOSS` (com checagem de 0 duplicação entre `LegendContent.vue` e `svg.ts`), `buildSVG` lendo `edge.order`, pill sólido nos três lugares e paridade tela↔SVG. Manter, por enquanto, a asserção Inertia sobre `catalog.sequence_modes` — o servidor só muda na Phase 5.
      Cobre: UI-01, UI-06, UI-07
      Acceptance criteria: `php artisan test --compact` verde; `npm test` verde; `npm run build` sem erro; nenhuma asserção citando `seqMap(state.seqMode, …)` sobra nos três arquivos.
      Testes: `tests/Feature/Frontend/{CanvasLegendTest,CanvasExportTest,CanvasSequenceTest}.php`

## Phase 4: Cliente — controles, contrato do payload e remoção do modo

Antes de implementar, leia:
1. `.spec/features/ordem-explicita-de-conexoes/SPEC.md` — requisitos RIGID que esta fase cobre (UI-02, UI-03, UI-04, RF-03a, RF-09, RF-13, RF-18, CT-01)
2. `.spec/features/ordem-explicita-de-conexoes/PLAN.md` — decomposição completa, dependências e riscos
3. `.ai/rules/js-prancheta.md` e `.ai/rules/prancheta.md` — `SessionBody` é a fonte única do payload; `data-testid` é contrato entre fases

### Gate

Comando que fecha a fase: `npm test && npm run types:check && npm run lint:check && npm run build && php artisan test --compact`

### Tasks

- [ ] T15 — Toolbar: toggle de exibição e "numerar automaticamente"
      Arquivos: `resources/js/components/prancheta/ZoomBar.vue`, `resources/js/components/prancheta/StageCanvas.vue`
      Mudança: remover o botão `sequence-mode` e o `<SequenceMenu>`; acrescentar `order-toggle` (alterna `showConnectionOrder`) e `order-auto` (dispara a numeração automática), ambos com `data-testid` estável. `StageCanvas.vue` troca as props `sequenceMode`/`sequenceModes` e o emit `pick-sequence` por `showConnectionOrder` + emits `toggle-order` / `auto-order`.
      Cobre: UI-02, UI-04
      Acceptance criteria: os dois botões existem no toolbar do palco com `data-testid` estável; o estado ligado/desligado do toggle é legível por atributo (p.ex. `aria-pressed`), não só por classe de cor; após o clique em `order-auto` todas as arestas válidas exibem badge `1..N`.
      Testes: `tests/Feature/Frontend/CanvasSequenceTest.php` — `data-testid` novos, estado por atributo, ausência de `SequenceMenu`
- [ ] T16 — Painel da conexão: input de ordem e "remover da sequência"
      Arquivos: `resources/js/components/prancheta/EdgeFloatBar.vue`, `resources/js/canvas/order.ts`
      Mudança: substituir `edge-seq-back`/`edge-seq-position`/`edge-seq-forward` por um `input type="number"` (`edge-order-input`) com o `order` corrente e um botão `edge-order-clear`. O clamp mora no motor: exportar `clampOrderInput(value, numberedCount, isNumbered)` de `canvas/order.ts` e o componente só liga. Commit em `change`/Enter/blur; campo vazio é no-op.
      Cobre: UI-03, RF-09
      Acceptance criteria: digitar `999` numa aresta não numerada de um diagrama com `N = 3` resulta em `order = 4`; digitar `999` numa já numerada resulta em `order = 3`; esvaziar o campo e sair dele deixa o `order` anterior intacto; o botão `edge-order-clear` produz `A=1, B=null, C=2` a partir de `[A=1,B=2,C=3]`; os três `data-testid` antigos não existem mais.
      Testes: `resources/js/canvas/order.test.ts` (clamp) + `tests/Feature/Frontend/CanvasSequenceTest.php` (`data-testid`)
- [ ] T17 — `Board.vue` liga os novos controles
      Arquivos: `resources/js/pages/Board.vue`
      Mudança: ligar `toggle-order` → `engine.setShowConnectionOrder(!engine.showConnectionOrder)`, `auto-order` → `engine.autoNumberOrder()`, `set-order` → `engine.setEdgeOrder(id, value)` e `clear-order` → `engine.clearEdgeOrder(id)`; `edgeBar` passa a expor `order` e `numberedCount`; remover `engine.seqMap()`, `engine.outSeq()`, `engine.moveSeq()`, `engine.setSequenceMode()`, `engine.sequenceModes` e o `watch` de `props.catalog.sequence_modes`.
      Cobre: UI-02, UI-03, UI-04, RF-09, RF-10
      Acceptance criteria: `pages/Board.vue` não menciona `seqMap`, `outSeq`, `moveSeq`, `setSequenceMode` nem `sequence_modes`; os quatro handlers estão ligados; `npm run types:check` verde.
      Testes: `tests/Feature/Frontend/CanvasSequenceTest.php` — handlers ligados e ausência das chamadas antigas
- [ ] T18 — Contrato do cliente: `SessionBody`
      Arquivos: `resources/js/prancheta/session.ts`, `resources/js/types/board.ts`
      Mudança: `SessionBody` perde `seq_mode` e ganha `show_connection_order: boolean`; `bodyFrom()`, `bodyFromPayload()`, `recordFrom()` e `SessionStore.restore()` acompanham, e o store ganha `setShowConnectionOrder()`. Em `types/board.ts`, `SessionPayload.seq_mode` sai e entra `show_connection_order?: boolean` (opcional nesta fase, com fallback `?? true`, porque o servidor só passa a mandá-lo na Phase 5); `BoardCatalog.sequence_modes` sai.
      Cobre: CT-01, RF-12, RF-13
      Acceptance criteria: o corpo montado por `bodyFrom()` não tem `seq_mode`, tem `show_connection_order` e cada item de `edges` carrega `order`; alternar a flag suja a sessão (`isDirty === true`); pan, zoom, seleção e legenda continuam não sujando.
      Testes: `resources/js/prancheta/session.test.ts` — forma do corpo e `isDirty`
- [ ] T19 — Boot densificado e `SESSION_CACHE_VERSION = 2`
      Arquivos: `resources/js/prancheta/resume.ts`, `resources/js/composables/useAutosave.ts`
      Mudança: `SESSION_CACHE_VERSION` vai a `2`, de modo que todo rascunho v1 seja descartado no boot por `isEntry()` e o estado do servidor vença. Em `createSessionStore()`, densificar tanto o corpo que vence o boot quanto o `server.body` usado como assinatura salva.
      Cobre: RF-18, RF-03a
      Acceptance criteria: `SESSION_CACHE_VERSION === 2`; um rascunho gravado na versão 1 é ignorado no boot e o estado renderizado é o do servidor; um rascunho v2 continua sendo reidratado; carregar uma sessão com `edges[].order` esparso (`[1,3,7]`) resulta em `[1,2,3]` no estado, `isDirty === false` logo após o boot e 0 requisições `PUT` sem interação do usuário.
      Testes: `resources/js/prancheta/resume.test.ts` e `resources/js/prancheta/autosave.test.ts`
- [ ] T20 — Remoção do modo de numeração do cliente
      Arquivos: `resources/js/canvas/sequence.ts` (remover), `resources/js/canvas/sequence.test.ts` (remover), `resources/js/components/prancheta/SequenceMenu.vue` (remover), `resources/js/canvas/{types.ts,diagram.ts,engine.ts,index.ts,fixtures.ts}`, `README.md`, `tests/Feature/DeliveryContractTest.php`
      Mudança: apagar `sequence.ts` (`outSeq`, `flowSeq`, `seqMap`, `sequenceModeOf`, `SEQUENCE_MENU`, `sequenceMenu`, `SequenceModeOption`, `SequenceNumber`, `SequenceMap`), `sequence.test.ts` e `SequenceMenu.vue`; remover `moveSeq()` de `diagram.ts`, o tipo `SequenceMode` e `SessionState.seqMode` de `types.ts`, `sequenceModesFixture()`/`seqMode` dos fixtures, e do `CanvasEngine` os membros `seqMode`, `sequenceModes`, `setSequenceModes`, `seqMap`, `outSeq`, `moveSeq`, `setSequenceMode`; tirar `sequence.ts` de `index.ts`. Atualizar a linha do Core Workflow 4 do `README.md` e o `workflowSources()` de `DeliveryContractTest.php`, que assere `toBeFile()` em cada caminho.
      Cobre: RF-13
      Acceptance criteria: `resources/js/canvas/sequence.ts` e `resources/js/components/prancheta/SequenceMenu.vue` não existem mais; `grep -rn 'outSeq\|flowSeq\|seqMap\|moveSeq\|seqMode' resources/js/` retorna 0 ocorrências; `php artisan test --compact --filter=DeliveryContract` verde; `npm run types:check` sem referência órfã.
      Testes: `tests/Feature/DeliveryContractTest.php` — cada fonte do Core Workflow 4 existe no disco
- [ ] T21 — Reescrita das guardas Pest do cliente
      Arquivos: `tests/Feature/Frontend/CanvasSequenceTest.php`, `tests/Feature/Frontend/CanvasEngineTest.php`, `resources/js/canvas/engine.test.ts`
      Mudança: `CanvasSequenceTest.php` passa a ser o teste da ordem explícita — forma do tipo `Edge` com `order`, existência de `canvas/order.ts` com `densify`/`setOrder`/`clearOrder`/`autoNumber`, os quatro `data-testid` novos, ausência dos antigos, e o dataset de nomes Vitest inteiramente renovado. Em `CanvasEngineTest.php`, `changing_sequence_mode_does_not_push_undo` vira `toggling_connection_order_does_not_push_undo`, renomeando também o teste Vitest correspondente.
      Cobre: RF-13, RF-15, UI-02, UI-03, UI-04
      Acceptance criteria: nenhum dos três arquivos cita `outSeq`, `flowSeq`, `seqMap`, `moveSeq` ou `sequence_modes`; `npm test`, `npm run types:check`, `npm run lint:check`, `npm run build` e `php artisan test --compact` verdes.
      Testes: `tests/Feature/Frontend/{CanvasSequenceTest,CanvasEngineTest}.php` + `resources/js/canvas/engine.test.ts`

## Phase 5: Persistência — migrations, request, serviços e Pest

Antes de implementar, leia:
1. `.spec/features/ordem-explicita-de-conexoes/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01, RF-05, RF-11 a RF-14a, CT-01 a CT-04, RNF-03)
2. `.spec/features/ordem-explicita-de-conexoes/PLAN.md` — decomposição completa, dependências e riscos (drop de FK no SQLite, backfill de JSON)
3. `.ai/rules/requests.md`, `.ai/rules/resources.md`, `.ai/rules/services.md`, `.ai/rules/feature.md` — camadas Controller → FormRequest → Service → Model → Resource e `seedCatalog()` nos testes

Cada `php artisan make:migration` roda sozinho, nunca encadeado com `&&` — timestamps idênticos.

### Gate

Comando que fecha a fase: `vendor/bin/pint --dirty --format agent && php artisan test --compact`

### Tasks

- [ ] T22 — Migration 1: `show_connection_order` + backfill de `order`
      Arquivos: `database/migrations/<ts>_add_show_connection_order_to_training_sessions_table.php` (novo), `tests/Feature/Migrations/TrainingSessionsMigrationTest.php`
      Mudança: `up()` adiciona `boolean('show_connection_order')->default(true)` em `training_sessions` e faz o backfill do JSON `edges` por `chunkById`: toda aresta de toda sessão recebe `order = null`, sem ler `seq_mode` nem reescrever qualquer outra chave. `down()` remove a coluna e deixa o diagrama intacto.
      Cobre: RF-12, RF-14, RF-14a, RNF-03
      Acceptance criteria: após `php artisan migrate` sobre uma base semeada, as N sessões existem, o conteúdo decodificado de `nodes`/`edges` é idêntico ao anterior salvo a adição da chave `order`, 0 arestas têm `order` diferente de `null` e 0 sessões têm `show_connection_order` diferente de `true`; `down()` está implementado e não apaga diagrama.
      Testes: `tests/Feature/Migrations/TrainingSessionsMigrationTest.php` — coluna, default, backfill e reversibilidade
- [ ] T23 — Migration 2: drop da FK e do lookup
      Arquivos: `database/migrations/<ts>_drop_sequence_modes_table.php` (novo), `tests/Feature/Migrations/LookupTablesMigrationTest.php`
      Mudança: `up()` derruba a FK e a coluna `training_sessions.sequence_mode_id` e então a tabela `sequence_modes`. Envolver o `dropForeign` num teste de driver (`Schema::getConnection()->getDriverName()`), deixando o SQLite dos testes cair direto no `dropColumn`. `down()` recria `sequence_modes` vazia com a mesma estrutura e a coluna `sequence_mode_id` nullable, sem apagar diagrama.
      Cobre: RF-13, RF-14, RF-14a, RNF-03
      Acceptance criteria: `Schema::hasTable('sequence_modes')` é falso e `sequence_mode_id` não aparece em `Schema::getColumnListing('training_sessions')`; `php artisan migrate` roda limpo em SQLite e o `down()` recria a estrutura preservando `nodes`/`edges` de todas as sessões.
      Testes: `tests/Feature/Migrations/{TrainingSessionsMigrationTest,LookupTablesMigrationTest}.php`
- [ ] T24 — Model `TrainingSession`
      Arquivos: `app/Models/TrainingSession.php`
      Mudança: acrescentar `'show_connection_order'` ao atributo `#[Fillable([...])]` e o cast `'show_connection_order' => 'boolean'`; remover `'sequence_mode_id'` do `#[Fillable]`, a relação `sequenceMode()` e as linhas `@property`/`@property-read` correspondentes.
      Cobre: RF-12, CT-02, RF-13
      Acceptance criteria: `$session->show_connection_order` é `bool` (`true`/`false`, nunca `1`/`0`) sob SQLite; `method_exists(TrainingSession::class, 'sequenceMode')` é falso; `php artisan test --compact --filter=TrainingSession` verde.
      Testes: `tests/Feature/Models/TrainingSessionTest.php` — cast booleano e ausência da relação
- [ ] T25 — `TrainingSessionUpdateRequest`
      Arquivos: `app/Http/Requests/TrainingSessionUpdateRequest.php`
      Mudança: entra `'edges.*.order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_EDGES, $this->orderIsUniqueInTheDiagram()]`, com a `Closure` privada falhando no próprio índice quando o valor se repete em outro item; entra `'show_connection_order' => ['sometimes', 'boolean']`. Sai a regra `seq_mode`, o `merge` de `normalizedSeqMode()` no `prepareForValidation()`, o método privado e o `use App\Models\SequenceMode`. Mensagens PT-BR novas em `messages()`. O servidor não valida densidade.
      Cobre: RF-11, RF-05, CT-01, CT-04, RF-13
      Acceptance criteria: `order` não inteiro, `order = 0`, negativo, `401` ou duplicado retornam 422 com `errors` chaveado por `edges.{i}.order` e mensagem em PT-BR; `order = null` e `order` inteiro entre 1 e 400 retornam 200; um payload esparso (`[1,3,7]`) retorna 200 e é gravado como veio; o arquivo não cita mais `seq_mode`.
      Testes: `tests/Feature/Sessions/SessionPayloadValidationTest.php` — dataset de faixa, unicidade e esparsidade
- [ ] T26 — Serviços, Resource, catálogo e factory
      Arquivos: `app/Http/Resources/TrainingSessionResource.php`, `app/Services/{SessionStateWriter,SessionCreator,CatalogService}.php`, `database/factories/TrainingSessionFactory.php`, `database/seeders/CatalogSeeder.php`, `app/Models/SequenceMode.php` (remover), `app/Http/Resources/SequenceModeResource.php` (remover), `database/seeders/SequenceModeSeeder.php` (remover), `database/factories/SequenceModeFactory.php` (remover)
      Mudança: o Resource remove `seq_mode`, acrescenta `show_connection_order` e devolve `edges` com `order`, mantendo `#[PreserveKeys]` e a ausência de `user_id`; `SessionStateWriter::write()` acrescenta `'show_connection_order'` ao `Arr::only(...)` e perde o bloco de `seq_mode`; `SessionCreator::create()` grava `'show_connection_order' => true` e perde `sequence_mode_id`/`defaultSequenceModeId()`; `CatalogService::payload()` deixa de emitir a coleção `sequence_modes`; `TrainingSessionFactory` grava `'show_connection_order' => true` e `'order' => null` em cada aresta de `diagramEdges()`; `CatalogSeeder` perde a chamada a `SequenceModeSeeder`; os quatro arquivos de `SequenceMode` são apagados.
      Cobre: CT-02, CT-03, RF-12, RF-12a, RF-13
      Acceptance criteria: a prop `catalog` de `GET /prancheta` traz exatamente 6 coleções, sem `sequence_modes`, e as outras seis inalteradas; `POST /api/sessions` com corpo vazio retorna 201 com `show_connection_order: true`; a resposta do Resource não tem `seq_mode` e cada item de `edges` carrega `order`; nenhum arquivo de `app/` ou `database/` referencia `SequenceMode`.
      Testes: `tests/Feature/Sessions/{BoardPageTest,TrainingSessionCrudTest,SessionManagementTest}.php`
- [ ] T27 — Pest do contrato de sessão
      Arquivos: `tests/Feature/Sessions/{SessionPayloadValidationTest,SessionAutosaveTest,BoardPageTest,TrainingSessionCrudTest,SessionManagementTest}.php`, `tests/Feature/Frontend/CanvasLegendTest.php`
      Mudança: cobrir o contrato novo e apagar o antigo — round-trip de `edges[].order`, `show_connection_order` persistido e devolvido, sessão nova nascendo com `true`, `catalog` sem `sequence_modes` via `AssertableInertia`, dataset de 422 de `order` com mensagem PT-BR e payload esparso aceito. Remover `update_normalizes_invalid_seq_mode_to_out` e toda asserção sobre `sequenceMode->slug`; retirar de `CanvasLegendTest.php` a asserção Inertia sobre `catalog.sequence_modes.0.legend_text`.
      Cobre: RF-01, RF-05, RF-11, RF-12, RF-12a, CT-01, CT-02, CT-03, CT-04
      Acceptance criteria: um `PUT` com `edges[].order` grava o valor e um `GET` subsequente o devolve idêntico; `PUT` com `show_connection_order: false` seguido de `GET /prancheta` traz `session.show_connection_order === false`; nenhum teste de `tests/Feature/Sessions/**` cita `seq_mode` ou `sequenceMode`.
      Testes: os próprios arquivos — `php artisan test --compact --filter='Session|BoardPage'`
- [ ] T28 — Pest de schema, catálogo e helpers
      Arquivos: `tests/Pest.php`, `tests/Feature/Migrations/{TrainingSessionsMigrationTest,LookupTablesMigrationTest}.php`, `tests/Feature/Models/{LookupModelsTest,TrainingSessionTest,TrainingSessionFactoryTest}.php`, `tests/Feature/Seeders/{LookupSeedTest,CatalogSeederTest,CatalogIntegrityTest}.php`
      Mudança: em `tests/Pest.php`, `autosaveBody()` perde `seq_mode` e ganha `show_connection_order` + `order` nas arestas; `domainTables()` e `catalogTables()` perdem `sequence_modes`; `catalogModels()` e o dataset `lookupModels` perdem `SequenceMode`; `insertTrainingSession()` perde a inserção no lookup e ganha `show_connection_order`. Remover `sequence_mode_out_is_first`, `sequence_modes_carry_the_prototype_legend_text`, a contagem `'sequence_modes' => 3` e as asserções de relação `sequenceMode`. Acrescentar os testes de RF-14/RF-14a/RNF-03 sobre base semeada.
      Cobre: RF-13, RF-14, RF-14a, RNF-03
      Acceptance criteria: `php artisan test --compact` verde na suíte inteira; `php artisan migrate:fresh --seed --force` roda limpo; `grep -rn 'sequence_mode\|seq_mode' tests/` retorna 0 ocorrências; `vendor/bin/pint --dirty --format agent` sem pendência.
      Testes: os próprios arquivos + `php artisan migrate:fresh --seed --force`

## Phase 6: Referências de arquitetura, regras e fechamento

Antes de implementar, leia:
1. `.spec/features/ordem-explicita-de-conexoes/SPEC.md` — requisitos RIGID que esta fase cobre (RF-13, RF-19, RNF-04, RNF-05)
2. `.spec/features/ordem-explicita-de-conexoes/PLAN.md` — decomposição completa, dependências e riscos
3. `AGENTS.md` — `.ai/rules/**` é hand-written e autoritativo: edição cirúrgica, nunca reescrita em bloco

### Gate

Comando que fecha a fase: `composer ci:check && php artisan migrate:fresh --seed --force`

### Tasks

- [ ] T29 — Referências de arquitetura em `docs/agents/`
      Arquivos: `docs/agents/{api_contracts,domain_rules,data_model,coding_guidelines,project_overview}.md`
      Mudança: reescrever os trechos que descrevem o desenho revogado — exemplos de payload e de resposta com `edges[].order` e `show_connection_order` no lugar de `seq_mode`, a lista de normalizações do `prepareForValidation`, as 6 coleções do `catalog`, a tabela de defaults e a de slugs de modo, a linha `sequence_mode_id` e a linha da tabela `sequence_modes` mais a coluna nova, a menção a `SequenceMode::DEFAULT_SLUG` e o macro-fluxo "45 min, `seq_mode` `out`" / "7 catalog collections". Documentar `integer|min:1|max:400` + unicidade sem densidade e que a densidade é responsabilidade do cliente.
      Cobre: RF-19, RF-13
      Acceptance criteria: `grep -rn 'sequence_mode\|seq_mode\|outSeq\|flowSeq' docs/agents/` retorna 0 ocorrências; os cinco arquivos descrevem o campo `order` e a flag `show_connection_order`.
      Testes: teste de varredura de T31 — `php artisan test --compact --filter=OrderContract`
- [ ] T30 — Regras do repositório
      Arquivos: `.ai/rules/canvas.md`, `.ai/rules/requests.md`
      Mudança: substituir a seção "Numeração: a ordem das saídas é a posição no array edges" de `.ai/rules/canvas.md` pela regra nova — uma sequência por diagrama, `order` explícito por aresta, densificação como único ponto de saída da mutação, BFS determinística com o fallback de três degraus, `bidir` sem semântica de grafo, órfã que mantém número e não desenha badge, toggle que não empilha desfazer — e acrescentar a frase de limite: constante única no cliente sim, catálogo paralelo no cliente não, valendo para `ORDER_NAME`/`ORDER_GLOSS`. Em `.ai/rules/requests.md`, remover a normalização de `seq_mode` e registrar a regra de `order` (faixa + unicidade, sem densidade). Caminho recomendado: `record-rule`; sem a ferramenta na sessão, a edição cirúrgica só dessas seções é fallback aceito — nunca reescrita em bloco.
      Cobre: RF-19
      Acceptance criteria: `.ai/rules/canvas.md` contém a nova regra de numeração explícita e a frase de limite da constante de legenda, e nenhum dos dois arquivos cita `outSeq`, `flowSeq`, `seq_mode` ou `sequence_modes`; nenhuma outra seção dos dois arquivos foi alterada.
      Testes: teste de varredura de T31 — `php artisan test --compact --filter=OrderContract`
- [ ] T31 — Varredura RF-13 e limites RNF-05
      Arquivos: `tests/Feature/OrderContractTest.php` (novo)
      Mudança: teste Pest que varre `app/`, `database/`, `resources/js/`, `routes/`, `tests/`, `docs/agents/` e `.ai/rules/` por `sequence_mode`, `seq_mode`, `outSeq` e `flowSeq` exigindo 0 ocorrências (ignorando o próprio arquivo do teste), confere que o schema não tem a tabela `sequence_modes` nem a coluna `training_sessions.sequence_mode_id`, e assere RNF-05: `MAX_NODES = 200`, `MAX_EDGES = 400`, `MAX_LABEL = 60`, `MAX_NOTES = 5000` inalterados nos dois lados, mais a presença da nova regra em `.ai/rules/canvas.md`.
      Cobre: RF-13, RF-19, RNF-05
      Acceptance criteria: o teste passa com 0 ocorrências nos sete diretórios; `Schema::hasTable('sequence_modes')` falso e `Schema::hasColumn('training_sessions', 'sequence_mode_id')` falso; os quatro limites conferidos entre `resources/js/canvas/limits.ts` e `app/Http/Requests/TrainingSessionUpdateRequest.php`.
      Testes: `tests/Feature/OrderContractTest.php` — `php artisan test --compact --filter=OrderContract`
- [ ] T32 — Fechamento do gate
      Arquivos: nenhum arquivo de aplicação — corrigir apenas o que a cadeia acusar
      Mudança: rodar no host `vendor/bin/pint --dirty --format agent`, `npm run build`, `composer ci:check` (lint + format + types + Vitest + Pest) e `php artisan migrate:fresh --seed --force`; corrigir só o que ficar vermelho, sem introduzir comportamento novo. Conferir que `resources/js/canvas/**` continua livre de Vue/Inertia e de `document`/`window`.
      Cobre: RNF-04, RF-13
      Acceptance criteria: `composer ci:check` termina verde; `php artisan migrate:fresh --seed --force` roda limpo; `tests/Feature/Frontend/CanvasEngineTest.php` confirma 0 ocorrências de Vue/Inertia/DOM em `resources/js/canvas/**`.
      Testes: `composer ci:check` (cadeia completa) + `php artisan migrate:fresh --seed --force`
