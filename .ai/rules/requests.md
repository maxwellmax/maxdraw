---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Autosave: normalizações acontecem antes das regras, e "" volta a ser ""
`TrainingSessionUpdateRequest::prepareForValidation()` faz duas coisas que não são detalhe: número negativo na estimativa vira zero (US-6.2), e `nodes[].label` / `edges[].kind` / `edges[].label` que chegaram `null` voltam a ser string vazia — o `ConvertEmptyStringsToNull` do framework transforma o `""` do motor do canvas em `null`, e "sem tipo" é string vazia no JSON do diagrama. Nada além disso é consertado antes das regras.

Catálogo é validado com `Rule::in(...)` sobre os slugs, nunca com `Rule::exists` por item: um payload no limite tem 200 nós e 400 arestas, e `exists` viraria 600 consultas.

## Ordem da conexão: faixa e unicidade aqui, densidade no cliente
`edges.*.order` é `sometimes|nullable|integer|min:1|max:MAX_EDGES` mais a `Closure` `orderIsUniqueInTheDiagram()`, que recusa o mesmo número duas vezes dentro do array e reporta o erro no próprio índice (`edges.{i}.order`), para o cliente saber qual campo destacar. `null` é valor legítimo: quer dizer "fora da sequência".

O que o servidor NÃO valida é densidade: `[1, 3, 4]` grava como veio. Manter o conjunto `{1..N}` sem buraco é responsabilidade do motor do cliente (`densify()` em `resources/js/canvas/order.ts`, único ponto de saída de toda mutação de ordem) — aqui só se recusa o que não teria conserto. `show_connection_order` é `sometimes|boolean`: bandeira de exibição da sessão, não conteúdo do diagrama.
