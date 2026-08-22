---
paths:
  - 'resources/js/components/prancheta/**'
---

# Prancheta

## Shell da prancheta: data-testid é o contrato entre as fases
O shell (`pages/Board.vue` + `components/prancheta/*`) é frame: cada fase seguinte preenche um `<slot>` (paleta no `ComponentRail`, `wires`/`nodes`/`labels` no `StageCanvas`, `legend`, e `roteiro`/`enunciado`/`calc`/`notas` no `DrillPanel`). Não troque slot por prop nem remova `data-testid` — `tests/Feature/Frontend/BoardShellTest.php` verifica cada peça por esse atributo.

Os quatro painéis do `DrillPanel` usam `v-show`, nunca `v-if`: trocar de aba não pode perder texto das notas nem campos da calculadora.

Avisos ao usuário passam sempre pelo toast (`useToast().warn()` + `prancheta/warnings.ts`), que é o canal único — nada de `alert()` ou mensagem inline.

O tema é preferência do navegador (`localStorage['sd-theme']` + atributo `data-theme`), separado do `useAppearance` do starter kit (`appearance` + classe `.dark`). `useTheme().vars` guarda os tokens relidos a cada troca — é daí que o canvas tira a cor das arestas.
