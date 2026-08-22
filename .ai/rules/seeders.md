---
paths:
  - 'database/seeders/**'
---

# Seeders

## Catálogo é seeder versionado, com upsert idempotente
Editar o catálogo é editar `database/seeders/*Seeder.php` — nenhuma rota ou tela escreve em tabela de catálogo (os models usam o trait `ReadOnlyAtRuntime`).

`CatalogSeeder` orquestra os filhos na ordem de dependência; cada filho usa `Model::upsert()` com chave de conflito por `slug` (por `minutes` em `session_durations`, por `(phase_id, position)` em `checklist_items`), então rodar duas vezes não duplica linha. FK é sempre resolvida por slug (`pluck('id', 'slug')`), nunca por id fixo.

`components.icon_key` precisa existir como chave em `resources/js/canvas/icons.ts` — o teste `every_component_icon_key_exists_in_engine` lê esse arquivo por regex (`^\s+chave:` seguido de crase), então mantenha uma chave por linha com valor em template string.
