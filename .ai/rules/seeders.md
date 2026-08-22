---
paths:
  - 'database/seeders/**'
---

# Seeders

## Catálogo é seeder versionado, com upsert idempotente
Editar o catálogo é editar `database/seeders/*Seeder.php` — nenhuma rota ou tela escreve em tabela de catálogo (os models usam o trait `ReadOnlyAtRuntime`).

`CatalogSeeder` orquestra os filhos na ordem de dependência; cada filho usa `Model::upsert()` com chave de conflito por `slug` (por `minutes` em `session_durations`, por `(phase_id, position)` em `checklist_items`), então rodar duas vezes não duplica linha. FK é sempre resolvida por slug (`pluck('id', 'slug')`), nunca por id fixo.

`components.icon_key` precisa existir como chave em `resources/js/canvas/icons.ts` — o teste `every_component_icon_key_exists_in_engine` lê esse arquivo por regex (`^\s+chave:` seguido de crase), então mantenha uma chave por linha com valor em template string.

## Conteúdo dos 14 problemas fica em database/seeders/data/problems.php
O `ProblemSeeder` não embute enunciado: ele faz `require database_path('seeders/data/problems.php')`, que é a transcrição literal do `const PROBLEMS` do protótipo congelado (`.spec/init/design/pranchetasystemdesign.html`) — mesma ordem, mesmo texto. Editar enunciado é editar esse arquivo.

O nível vem do `lv` do protótipo e é traduzido no seeder para o slug de `problem_levels` (1 → base, 2 → intermediate, 3 → advanced); `lv` fora de 1..3 lança `InvalidArgumentException` em vez de gravar nível errado.

`problem_items` faz upsert por `(problem_id, problem_item_type_id, position)` e depois poda tudo com `position` acima do tamanho atual da lista — é assim que reexecutar não duplica nem deixa órfão. Não troque isso por delete-and-insert: o teste `catalog_seeder_is_idempotent` compara as linhas com `id`, e recriar as linhas muda os ids a cada run.
