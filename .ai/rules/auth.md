---
paths:
  - 'resources/js/pages/auth/**'
---

# Auth

## Rotas de senha entram literais enquanto o reset estiver desligado
`Login.vue`, `ForgotPassword.vue` e `ResetPassword.vue` usam as URLs literais `/forgot-password` e `/reset-password` em vez de importar `@/routes/password`.

Motivo: `Features::resetPasswords()` está comentado no v1 (ver `.ai/rules/config.md`), então o Wayfinder não gera `request`/`email`/`update` — o import quebra `npm run build` e `npm run types:check` com `MISSING_EXPORT`. O `FortifyServiceProvider` continua registrando `resetPasswordView`/`requestPasswordResetLinkView`, então as páginas seguem prontas para quando a feature voltar; ao religá-la, troque os literais pelos helpers do Wayfinder de novo.

Armadilha relacionada: `public/build` e `resources/js/routes` são gitignored. Um `npm run build` que falha esvazia `public/build` e derruba ~10 testes com `ViteManifestNotFoundException` — rode o build de novo depois de consertar.
