# Respostas do desenvolvedor — clarificação

Todas confirmadas pelo desenvolvedor. Aplique-as ao SPEC.md in-place e incremente a versão.

## A-01 — Navegação do AppSidebar/AppHeader (marcador 1 do Open Questions)

Leitura escolhida: **(b) apontar para a prancheta**.

- O único item de `mainNavItems` em `AppSidebar.vue:20-26` e `AppHeader.vue:56-62` passa a ter título **"Prancheta"** e `href` da rota `board`.
- O `<Link>` que embrulha `<AppLogo />` no header do sidebar (`AppSidebar.vue:48`) e o equivalente no `AppHeader.vue:149` passam a apontar para a rota `board`.
- Isso satisfaz RF-05 sem deixar `/settings/*` sem caminho de volta. O conteúdo das telas de settings permanece FORA DE ESCOPO — muda apenas o alvo dos links de navegação.

## A-02 — Ícones de UI-07 e pipeline de geração (marcador 2 do Open Questions)

- Variante: **`maxdraw-mark.svg`** (traço `#1e6f6c`, rects `#3ddad7` / `#E6EDF3`) é a fonte dos três ícones.
- `public/favicon.svg` = cópia do `maxdraw-mark.svg`.
- `public/apple-touch-icon.png` = **180×180**, com **fundo sólido `#0A0E13`** (iOS não aceita transparência).
- `public/favicon.ico` = derivado do mesmo mark.
- Geração: **script pontual, executado uma única vez no host, com os binários commitados**. O host hoje NÃO tem rasterizador (`convert`, `magick`, `rsvg-convert`, `inkscape`, `cairosvg`, `Pillow` — todos ausentes, verificado). O desenvolvedor aprovou instalar um via `apt-get` no host (ex.: `librsvg2-bin` ou `imagemagick`) como passo manual **de ambiente**, fora do repositório.
- RNF-05 permanece intacto: nada entra em `composer.json` nem em `package.json`. O plano deve tratar a instalação do rasterizador como **pré-requisito manual do desenvolvedor**, não como tarefa de código, e a tarefa de geração deve falhar de forma explícita (mensagem clara) se o binário não existir.

## A-03 — Q-01 do clarifier: marca duplicada em Login/Register

Leitura escolhida: **(b) lockup na página, mark sai do layout**.

- `layouts/auth/AuthSimpleLayout.vue` **deixa de renderizar o bloco de marca** (o `<Link :href="home()">` com `<AppLogoIcon class="size-9 fill-current" />`, `AuthSimpleLayout.vue:27,33`).
- `pages/auth/Login.vue` e `pages/auth/Register.vue` renderizam o `data-testid="brand-lockup"`.
- Consequência aceita explicitamente pelo desenvolvedor: as demais telas de auth (ConfirmPassword, VerifyEmail, TwoFactorChallenge, ForgotPassword, ResetPassword) ficam **sem marca** no topo. Elas seguem fora de escopo — nenhuma outra alteração nelas.
- `AppLogoIcon.vue` continua sendo atualizado por UI-04: ele permanece em uso via `AppLogo.vue` no sidebar/header.
- O AC de UI-05 permanece ancorado em `pages/auth/Login.vue` e `pages/auth/Register.vue`.

## A-04 — Q-02 do clarifier: variante do lockup sem `data-theme`

Leitura escolhida: **(a) seguir `prefers-color-scheme`**.

- A seleção da variante é **puramente CSS / `<picture>`, sem JS**, espelhando o que `prancheta.css:49` já faz (`:root:not([data-theme='light'])` + `prefers-color-scheme`).
- UI-05 deve cobrir **três** estados, e o AC precisa afirmar os três:
  1. `data-theme="dark"` → `maxdraw-lockup-dark`;
  2. `data-theme="light"` → `maxdraw-lockup-light`;
  3. **atributo ausente** (estado padrão, `useTheme.ts:92-94` só o escreve após escolha explícita) → variante resolvida por `prefers-color-scheme`, dark como resultado quando o SO está no escuro.
- Sem flash de troca, pois `config/inertia.php` tem `ssr.enabled = true`.
- RNF-02 continua valendo: nada de `dark:` nem de literal hexadecimal nesses arquivos.

## A-05 — Q-03 do clarifier: verificabilidade do RNF-04 (teclado)

Leitura escolhida: **(a) asserção de fonte**.

- Reescreva RNF-04 para um critério **verificável hoje**: o teste (no estilo `frontendSource(...)` já usado em `tests/Feature/Frontend/BoardShellTest.php`) afirma que `BoardTopBar.vue` declara `aria-haspopup="menu"`, `aria-expanded` vinculado ao estado, e um handler de `Escape` (`@keydown.esc` ou equivalente).
- O **retorno de foco ao gatilho após `Escape` deve ser implementado**, mas fica **explicitamente não coberto por teste** — registre isso como limitação conhecida no SPEC (seção de riscos/limitações), não como AC.
- Nenhuma dependência nova: `jsdom` e `@vue/test-utils` foram **recusados**. `vitest.config.ts` permanece `environment: 'node'` com o `include` atual. RNF-05 intacto.

## A-06 — Q-04 do clarifier: gatilho do menu de usuário

Leitura escolhida: **(b) iniciais sempre, nome em `sr-only` no estreito**.

- **Correção factual ao SPEC**: não existe coluna `avatar` em `users` (`database/migrations/0001_01_01_000000_create_users_table.php`) e `HandleInertiaRequests` compartilha o model cru, logo `auth.user.avatar` é sempre `undefined`. UI-01 NÃO deve dizer "avatar/iniciais" — deve dizer **iniciais derivadas de `auth.user.name`**.
- O gatilho exibe **iniciais sempre visíveis**; o nome completo fica visível a partir de `md` e em `sr-only` abaixo disso.
- O AC de UI-01 continua exigindo que `auth.user.name` seja renderizado — a versão `sr-only` satisfaz, e o AC deve dizer isso literalmente para não virar ambiguidade nova.
- Motivo: a topbar já carrega 7 controles (`problem-picker`, `sessions-button`, `save-chip`, `theme-button`, `export-button`, `save-button` + spacer) e o nome completo em telas estreitas competiria por largura.

## Invariantes que NÃO mudam

- Escopo removido continua sendo apenas `Welcome.vue` + `Dashboard.vue` + rota `dashboard` + `DashboardTest.php`.
- `/settings/*` (conteúdo), VerifyEmail, TwoFactorChallenge, passkeys e as telas órfãs de reset seguem fora de escopo.
- `config/fortify.php` não é alterado (`home` já é `/prancheta`; `resetPasswords` já desligado).
- Nome de rota `home` preservado em `GET /` (RF-04).
- Zero mudança em `composer.json` / `package.json` (RNF-05).

Ao terminar, o arquivo NÃO deve conter nenhum marcador `[NEEDS CLARIFICATION]`.
