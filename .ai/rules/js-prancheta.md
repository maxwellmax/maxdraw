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
