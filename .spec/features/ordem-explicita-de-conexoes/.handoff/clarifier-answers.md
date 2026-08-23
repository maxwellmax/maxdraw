# Respostas do desenvolvedor — ordem-explicita-de-conexoes

Todas as respostas abaixo são decisões fechadas. Aplique-as in-place no SPEC,
incremente a versão, e remova os marcadores `[NEEDS CLARIFICATION]` cobertos.

## Q1 — Autoridade sobre a densidade (RF-03 / RF-05 / RF-11 / CT-01)
**(a)** Servidor valida só unicidade + `≥1`. O cliente densifica no boot **e**
chama `markSaved()` com a assinatura já densificada, para não acender o chip
"não salvo" nem disparar `PUT` ao simplesmente abrir a prancheta.

## Q2 — Backfill (RF-14)
**(a)** `order = null` em todas as arestas de todas as sessões;
`show_connection_order = true` fixo para todas. `down()` não-destrutivo.

## Q3 — Rascunho local (RF-14 / CT-01)
**(a)** Bumpar `SESSION_CACHE_VERSION` para `2`. Rascunho v1 é descartado no
boot pelo `isEntry()` e o servidor vence. Acrescente RF explícito no SPEC
registrando a consequência aceita: trabalho não sincronizado no rascunho antigo
se perde.

## Q4 — Teto e semântica do input (RF-11 / UI-03)
**(a)** Servidor: `integer|min:1|max:400` reusando `MAX_EDGES`. Cliente: clampa
para `N` se a aresta já é numerada e `N+1` se não é. Campo vazio = **no-op** —
quem remove da sequência é o botão de RF-09.

## Q5 — Alcance da BFS (RF-10 / RNF-02)
**(a)** A BFS cobre **todas** as arestas vivas. Raízes = clientes sem entrada →
demais nós sem entrada → todos os nós (mesmo fallback de `sequenceRoots()`).
Sobrescreve qualquer `order` anterior.

## Q6 — `bidir` (RF-17)
**(a)** Uma aresta = um `order`, sempre. A BFS atravessa **só** `from → to`, e
`bidir` não conta como entrada em `from`. `bidir` permanece bandeira de desenho.

## Q7 — Aresta órfã (RF-16)
**(a)** A densificação varre `state.edges` inteiro, órfã inclusa: ela mantém
`order`, conta para `N` e não é desenhada. O estado transitório pode exibir
`1, 3, 4` na tela. Preserva a AC de RF-15 sem densificar no `undo`/`redo`.

## Q8 — Granularidade do desfazer (RF-15 / UI-02 / UI-03)
**(a)** Numeração automática = **um** passo de desfazer para o diagrama todo.
Toggle **não** empilha (é visualização, como `setSequenceMode` hoje) mas
persiste — `showConnectionOrder` NÃO entra em `DiagramSnapshot` (US-3.5).
Input commita em `change`/Enter/blur, um passo por commit.

## Q9 — Texto da legenda (UI-07)
**(a)** `name` e `text` viram constantes exportadas de `canvas/legend.ts`
(ex.: `ORDER_NAME`/`ORDER_GLOSS`), ao lado de `UNTYPED_*`, importadas pelo
`LegendContent.vue` e pelo `svg.ts`. `LegendSequence.mode` e o atributo
`data-mode` são removidos. Inclua no escopo a atualização de
`.ai/rules/canvas.md` explicitando o limite: constante única sim, catálogo
paralelo no cliente não.

## Q10 — Badge (UI-01 / UI-06)
**(a)** Pill sólido nos **três** lugares — chip da tela, `svg.ts::seqDotMarkup`
e amostra da legenda. Fundo `var(--ec)`, texto na cor do papel, `rounded-full`
com `min-w` e padding horizontal que cresce com os dígitos. `SEQ_LEAD` passa a
depender do número de dígitos. Paridade tela↔SVG mantida.

## Correções dos achados adicionais (aplicar todas)
1. **RNF-05**: corrija o teto de crescimento de payload de 4 KB para ~6 KB
   (13 bytes × 400 arestas ≈ 5,2 KB, mais `show_connection_order`, menos
   `seq_mode`).
2. **RF-13**: amplie a varredura do AC para incluir `docs/agents/api_contracts.md`,
   `docs/agents/domain_rules.md`, `docs/agents/data_model.md`,
   `docs/agents/coding_guidelines.md` e `.ai/rules/requests.md`. **Decisão: essas
   atualizações ENTRAM no escopo** — deixar as referências de arquitetura
   descrevendo um lookup extinto não é aceitável.
3. **`show_connection_order`**: acrescente ao Scope a entrada no `#[Fillable([...])]`
   de `app/Models/TrainingSession.php` (o projeto usa o atributo, não a
   propriedade) e o cast `boolean` — sem o cast o SQLite dos testes devolve
   `1`/`0` e a AC de RF-12 (`=== false`) falha.
4. **`SessionBody` / `bodyFrom()`** (`resources/js/prancheta/session.ts`): entra
   no Scope. `seq_mode` sai, `show_connection_order` entra, e é a assinatura JSON
   desse objeto que define `isDirty`.
