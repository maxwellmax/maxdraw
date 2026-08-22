---
paths:
  - 'resources/js/canvas/**'
---

# Canvas

## O motor do canvas é TypeScript puro — nada de Vue nem de DOM
Nenhum arquivo de `resources/js/canvas/` pode importar Vue/Inertia nem tocar em `document`/`window` — `tests/Feature/Frontend/CanvasEngineTest.php` varre o pacote inteiro por isso. A camada Vue mede e traduz; o motor decide.

Consequências práticas: a altura real de cada bloco chega por `engine.measure(id, height)` (o `ResizeObserver` está no `CanvasNode.vue`) e o tamanho do palco por `engine.setSize()`. Sem isso, `bez()` usa a altura padrão 86 e `fit()` enquadra num palco de tamanho zero.

`CanvasEngine` é instanciado com `reactive(new CanvasEngine(...))` no `Board.vue`: use `private` do TS, nunca campos `#privados` — o proxy do Vue quebra com eles.

Os números do protótipo (132/86, pads 7 e 10, controle 0.45/26/110, ponta 9.5/3.9, snap 4, colisão 122×80, 200/400/60) são constantes exportadas e estão travadas por teste; os limites 200/400/60 são conferidos contra as constantes privadas de `TrainingSessionUpdateRequest`.

Recusa de mutação volta como `{ ok: false, reason }`. `nodeLimitReached`/`edgeLimitReached` têm o nome da chave em `prancheta/warnings.ts` para ir direto ao toast; `invalidLink` e `unknownComponent` são recusa silenciosa.
