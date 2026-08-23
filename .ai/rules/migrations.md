---
paths:
  - 'database/migrations/**'
---

# Migrations

## Derrubar coluna com FK: `dropForeign` é obrigatório também no SQLite
Para remover uma coluna que tem FK, chame `$table->dropForeign([...])` ANTES do `dropColumn`, sem guardar por driver.

O SQLite dos testes não é exceção: a partir da 3.35 o Laravel usa `ALTER TABLE ... DROP COLUMN` nativo, que não reescreve a cláusula `foreign key` da DDL — o banco recusa com "error in table X after drop column: unknown column ... in foreign key definition". O `dropForeign` entra em `SQLiteGrammar::getAlterCommands()` e faz o Laravel reconstruir a tabela inteira (índices e demais FKs preservados).

Verificado na migration `2026_08_23_085323`, que derruba a coluna `training_sessions` do lookup de modo de numeração aposentado pela ordem explícita das conexões.
