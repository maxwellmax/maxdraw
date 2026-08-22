---
paths:
  - config/fortify.php
---

# Config

## Recuperação de senha fica desligada no v1
`Features::resetPasswords()` está comentado de propósito na Phase 2.3/2.4: o `database-schema.md` manda a tabela `password_reset_tokens` existir (ela vem da migration padrão do starter kit) mas o fluxo NÃO pode ser exposto em rota nenhuma no v1.

Consequências que não são óbvias no código:
- `tests/Feature/Auth/PasswordResetTest.php` fica skipped via `skipUnlessFortifyHas()`; isso é o comportamento esperado, não um teste quebrado.
- `tests/Feature/Migrations/InfrastructureTablesMigrationTest.php` trava a decisão — reativar a feature deixa a suíte vermelha.
- O link "Forgot your password?" do `Login.vue` some sozinho: já é guardado por `v-if="canResetPassword"`, alimentado por `Features::enabled()` no `FortifyServiceProvider`.

Só religue depois que a Open Question "Recuperação de senha" (user-stories) for decidida.
