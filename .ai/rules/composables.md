---
paths:
  - resources/js/composables/useStageInteraction.ts
---

# Composables

## Todo gesto do palco passa por useStageInteraction
É a única peça que faz hit-testing no DOM do palco: ponteiro, roda e teclado entram aqui e saem como chamadas ao `CanvasEngine`. Não espalhe `@pointerdown` de pan/arrasto pelos componentes — `CanvasNode` só cuida do duplo clique que abre o rótulo.

O hit-testing usa atributos, não classes: `[data-node-id]`, `[data-edge-id]`, `[data-handle]` e `[data-testid="node-label"]`. Renomear qualquer um deles quebra arrasto, seleção e a exceção do duplo clique.

`OVERLAYS` (`[data-testid="legend"]`, `[data-testid="zoombar"]`) engole ponteiro e roda antes do palco — US-3.4 exige que pan e zoom não disparem sobre eles.

Atalhos não disparam quando `isTyping(event.target)` — input, textarea, select ou `isContentEditable`. O rótulo em edição é contenteditable, então essa checagem é o que impede `Del` de apagar o bloco enquanto se digita o nome dele.
