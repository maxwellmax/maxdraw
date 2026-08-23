---
paths:
  - 'resources/js/prancheta/**'
---

# Js Prancheta

## Autosave: sujo é derivado do payload, não uma bandeira
`SessionStore` (prancheta/session.ts) é a fonte única do corpo do `PUT /api/sessions/{id}`: `isDirty` compara a assinatura do payload de agora com a do último confirmado pelo servidor. Daí saem de graça as duas regras da US-8.1 — qualquer campo persistido suja, e pan/zoom/seleção/legenda (que não entram no payload) nunca sujam. Não introduza um `dirty = true` manual, e não monte payload em componente: `bodyFromPayload`/`bodyFrom` moram só no store.

O motor do canvas trabalha sobre `store.state` (o mesmo objeto), por isso desenhar já suja. `Autosave` tem 800 ms para o `localStorage` e 3 s para o servidor, mais um teto de 15 s: sem o teto, o cronômetro sujando a sessão a cada segundo reiniciaria a inércia para sempre e nada subiria.

O envio vai pelo cliente HTTP do Inertia (`http.getClient()` em lib/sessionTransport.ts) — é ele que manda o cookie de sessão e o `X-XSRF-TOKEN`. Não há token de API. Conflito de versão é detectado pelo rascunho no navegador (outra aba com baseline mais nova) e no boot por `resolveBoot`; nos dois casos avisa por toast e não sobrescreve.

## Cronômetro: fatias derivadas do peso, relógio que para sozinho
`prancheta/clock.ts` é o motor do tempo: `bounds()` acumula o fim de cada fase a partir do peso normalizado e tira a duração da diferença entre uma fronteira e a anterior — é isso que faz a soma das cinco fatias fechar exatamente a duração e a última fase terminar no segundo final. Nenhum peso, nome de fase ou lista de durações mora no cliente: fases vêm de `catalog.phases` e os botões de `catalog.session_durations` (`durationsFrom`); `DrillClockTest.php` confere a fixture do Vitest contra o `PhaseSeeder`.

`SessionClock` nasce sempre pausado (o servidor guarda `elapsed_seconds`, nunca "rodando") e para sozinho quando o restante zera — `start()` recusa com o tempo esgotado, e só zerar ou esticar a duração reabre. Trocar a duração nunca toca no decorrido.

A gravação periódica (`CLOCK_PERSIST_MS`, 20 s) é `autosave.saveLocal()`, não envio: quem sobe ao servidor continua sendo o debounce da Phase 10, segurado pelo teto de 15 s — sem ele, o tique de 1 s reiniciaria a inércia para sempre.

## Roteiro: marcação por id do item, acordeão derivado do cronômetro
`prancheta/roteiro.ts` é o arranjo do checklist: `phaseRows()` deriva tudo (minutos da fatia, estado da fase, progresso marcados/total) das mesmas `bounds()`/`curPhase()` do cronômetro — não há segunda aritmética de tempo nem lista de itens no cliente; fases e os 25 itens vêm de `catalog.phases[].checklist_items`.

`checks` é mapa de `checklist_items.id` → `true`, e ausência é desmarcado (`isChecked`): a chave nunca é a posição (`"1:3"` do protótipo é recusada com 422 pela `TrainingSessionUpdateRequest`), e é isso que faz um item novo no meio da fase não deslocar marcação já gravada.

Qual fase está aberta é preferência de tela, não payload: o `Board.vue` guarda `phaseChoice` (`FOLLOW_CURRENT` = segue o relógio, `ALL_COLLAPSED` = nenhuma) e um `watch(phaseIndex)` a zera na virada — a escolha manual prevalece só até a próxima fase começar.

Notas: `NOTES_MAX_LENGTH` (notes.ts) espelha o `MAX_NOTES` privado da FormRequest e `DrillRoteiroTest` compara os dois. O `NotesPad` não usa `maxlength`: `acceptNotes()` devolve `blocked` para o Board avisar por toast — cortar em silêncio é o que a US-6.3 proíbe.

## Problema escolhido é campo do payload, não sessão nova
O protótipo cria uma sessão nova ao escolher um problema; aqui `problem_id` entra no `SessionBody` (prancheta/session.ts) e sobe pelo mesmo autosave — `store.setProblemId()` suja a sessão como qualquer outro campo, e o `PUT /api/sessions/{id}` o aceita (`TrainingSessionUpdateRequest` + `SessionStateWriter`). A US-2.1 pede "gravado na sessão corrente"; sessão nova é a Phase 19.

Consequências: rascunho local antigo sem a chave cai em `problem_id ?? null` no `recordFrom`/`bodyFromPayload`; a `Board.vue` fala só `problemId` em camelCase porque `CanvasNavigationTest` proíbe a string `problem_id` na página.

O gabarito (`details` em ProblemBrief.vue) não tem estado persistido de propósito: `:key="problem.id"` e a ausência de `open` são o que devolvem o bloco ao colapsado a cada carregamento e a cada troca de enunciado (US-10.1). `NoDiagramEvaluationTest` varre rotas, respostas e `app/` por vocabulário de avaliação — nada na aplicação pode julgar o desenho.
