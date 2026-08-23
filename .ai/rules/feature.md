---
paths:
  - 'tests/Feature/**'
---

# Feature

## Teste que abre a prancheta precisa de seedCatalog()
`GET /prancheta` cria a sessão corrente quando o usuário não tem nenhuma, e isso exige a duração padrão (45) no banco. Todo teste que bate na rota `board` chama `seedCatalog()` (helper do `tests/Pest.php`, roda o `CatalogSeeder`) — sem ele a rota estoura com "o catálogo não foi seedado".

Não ligue o seed globalmente (`$seed`/`$seeder` no `TestCase`): vários testes de model e de lookup contam linhas ativas ou recriam durações 30/45/60, e o catálogo pré-existente os derruba por contagem e por índice único.
