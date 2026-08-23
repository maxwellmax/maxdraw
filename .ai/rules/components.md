---
paths:
  - 'resources/js/components/**'
---

# Components

## AppLogoIcon é o mark maxdraw monocromático (currentColor)
`AppLogoIcon.vue` traz o mark maxdraw (`viewBox="0 0 32 32"`, traço `M9 9h14M9 9v14M9 23h14M23 9v14`, dois `rect` 12×12 `rx="3"`) em `currentColor`, e não as cores fixas da marca. É essa decisão que permite consumi-lo com `fill-current`/`text-*` e usá-lo na `BoardTopBar` sem cor hexadecimal literal — o arquivo inteiro da barra é proibido de casar `/#[0-9a-fA-F]{3,8}\b/` (UI-03/RNF-02). Se algum dia o ícone ganhar cores fixas, a barra precisa passar a consumir `<img src="/brand/…">`.

O hex da marca vive só em `public/brand/*.svg` (lockups) e nos ícones de `public/`. `BrandLockup.vue` escolhe a variante por classe CSS (`brand-lockup__dark`/`__light`) — zero JavaScript, sem `useTheme` nem `matchMedia`.
