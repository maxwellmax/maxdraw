---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Autosave: normalizações acontecem antes das regras, e "" volta a ser ""
`TrainingSessionUpdateRequest::prepareForValidation()` faz três coisas que não são detalhe: `seq_mode` ausente/inválido vira `out` (US-4.3, nunca 422), número negativo na estimativa vira zero (US-6.2), e `nodes[].label` / `edges[].kind` / `edges[].label` que chegaram `null` voltam a ser string vazia — o `ConvertEmptyStringsToNull` do framework transforma o `""` do motor do canvas em `null`, e "sem tipo" é string vazia no JSON do diagrama.

Catálogo é validado com `Rule::in(...)` sobre os slugs, nunca com `Rule::exists` por item: um payload no limite tem 200 nós e 400 arestas, e `exists` viraria 600 consultas.
