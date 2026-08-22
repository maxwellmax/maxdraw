---
paths:
  - 'app/Http/Resources/**'
---

# Resources

## TrainingSessionResource precisa de #[PreserveKeys] por causa do mapa checks
`checks` é um mapa chaveado por `checklist_items.id`, e o `filter()` do JsonResource reindexa (array_values) qualquer array cujas chaves sejam todas numéricas — sem o atributo `#[PreserveKeys]` na classe, a marcação do item 7 chega ao cliente como a do item 0.

O catálogo do board é servido como array puro: `CatalogService` e o `BoardController` chamam `->resolve()` nas coleções para não empilhar um `data` por nível dentro das props do Inertia. O wrapper `data` continua valendo nas respostas JSON de `sessions.index/show/store` — os testes da Phase 7 dependem dele.
