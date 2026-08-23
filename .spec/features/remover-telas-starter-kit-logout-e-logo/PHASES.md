# Phases: remover-telas-starter-kit-logout-e-logo

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/remover-telas-starter-kit-logout-e-logo/PHASES.md`.

Pré-requisito manual (PR-01, fora do código): rasterizador de SVG instalado no host por `apt-get` (`librsvg2-bin` → `rsvg-convert`, ou `imagemagick` → `magick`/`convert`; `icoutils` → `icotool` se só houver `rsvg-convert`). Só a Phase 4 depende dele.

## Phase 1: Porta de entrada `/` e expurgo do starter kit

Antes de implementar, leia:
1. `.spec/features/remover-telas-starter-kit-logout-e-logo/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/remover-telas-starter-kit-logout-e-logo/PLAN.md` — decomposição completa, dependências e riscos
3. `.ai/rules/routes.md` e `.ai/rules/feature.md` — destino pós-autenticação inalterado e `seedCatalog()` na rota `board`

- [ ] T01 — Porta de entrada `/` servindo a tela de login
      Arquivos: `routes/web.php`
      Mudança: apagar `Route::inertia('/', 'Welcome')->name('home');` (linha 7) e `Route::inertia('dashboard', 'Dashboard')->name('dashboard');` (linha 12); declarar `Route::get('/', [AuthenticatedSessionController::class, 'create'])->middleware('guest:'.config('fortify.guard'))->name('home');` com `use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;`. A rota só declara URI/nome/middleware e delega ao controller (docs/agents/architecture.md). NÃO editar `config/fortify.php` nem `bootstrap/app.php` — o 302 do autenticado vem de `redirectUsersTo('/prancheta')`. Rodar `npm run build` para regenerar o Wayfinder (o export `dashboard` some).
      Cobre: RF-01, RF-02, RF-03, RF-04, CT-01, CT-04
      Acceptance criteria: `php artisan route:list --name=home` lista exatamente 1 rota com URI `/`; `php artisan route:list --name=dashboard` retorna 0 rotas; `grep -n "Welcome" routes/web.php` sem linha; `config/fortify.php` e `bootstrap/app.php` sem diff.
      Testes: `tests/Feature/HomeEntrypointTest.php` (T06) — guest 200 em `auth/Login`, autenticado 302 para `/prancheta`
- [ ] T02 — Remover as páginas do starter kit
      Arquivos: `resources/js/pages/Welcome.vue`, `resources/js/pages/Dashboard.vue`, `resources/js/app.ts`
      Mudança: excluir os dois arquivos de página e remover a linha `case name === 'Welcome':` do `switch` de `app.ts`, mantendo `case name === 'Board': return null;` intacto.
      Cobre: RF-01, RF-05
      Acceptance criteria: `ls resources/js/pages/Welcome.vue resources/js/pages/Dashboard.vue` falha nos dois caminhos; `frontendSource('app.ts')` não contém `Welcome`; `tests/Feature/Frontend/BoardShellTest.php` continua verde.
      Testes: `tests/Feature/Frontend/StarterKitCleanupTest.php` (T07) — ausência dos arquivos e de `Welcome` em `app.ts`
- [ ] T03 — Reapontar o shell autenticado para a prancheta
      Arquivos: `resources/js/components/AppSidebar.vue`, `resources/js/components/AppHeader.vue`
      Mudança: trocar `import { dashboard } from '@/routes'` por `import { board } from '@/routes'`; o único item de `mainNavItems` vira `{ title: 'Prancheta', href: board(), icon: LayoutGrid }`; o `<Link>` que embrulha `<AppLogo />` passa a `:href="board()"`. Não tocar em `rightNavItems`/`footerNavItems`, `NavUser`/`UserMenuContent` nem em `resources/js/pages/settings/**`.
      Cobre: RF-05, RF-09
      Acceptance criteria: `frontendSource` dos dois componentes contém `board(` e `'Prancheta'` nos três pontos (nav + logo), `mainNavItems` com exatamente 1 item em cada, zero ocorrência de `dashboard`; `git diff --exit-code resources/js/pages/settings` limpo.
      Testes: `tests/Feature/Frontend/StarterKitCleanupTest.php` (T07) — asserções de `frontendSource`
- [ ] T04 — Fallback da verificação por passkey para a prancheta
      Arquivos: `resources/js/components/PasskeyVerify.vue`
      Mudança: linha 33 — `router.visit(response.redirect ?? '/dashboard')` vira `router.visit(response.redirect ?? '/prancheta')` (ou o helper Wayfinder `board()`). Nenhuma outra alteração no fluxo de passkeys.
      Cobre: RF-05, RF-06
      Acceptance criteria: `frontendSource('components/PasskeyVerify.vue')` contém `'/prancheta'` (ou o helper da rota `board`) e nenhuma ocorrência de `'/dashboard'`.
      Testes: `tests/Feature/Frontend/StarterKitCleanupTest.php` (T07) — asserção de conteúdo
- [ ] T05 — Remover o teste legado do dashboard
      Arquivos: `tests/Feature/DashboardTest.php`
      Mudança: excluir o arquivo (exclusão aprovada pelo desenvolvedor; é também a única classe estilo PHPUnit da suíte, proibida por AGENTS.md §2). Nada equivalente volta.
      Cobre: RF-08
      Acceptance criteria: `ls tests/Feature/DashboardTest.php` retorna erro e `php artisan test --compact` fica verde.
      Testes: `tests/Feature/Frontend/StarterKitCleanupTest.php` (T07) — asserção de ausência do arquivo
- [ ] T06 — Teste da porta de entrada `/`
      Arquivos: `tests/Feature/HomeEntrypointTest.php`
      Mudança: criar com `php artisan make:test --pest HomeEntrypointTest`; três casos — guest `GET /` 200 com componente `auth/Login` e props `canResetPassword`/`status`; `actingAs(User::factory()->create())` em `GET /` redirecionando para `route('board', absolute: false)`; rota nomeada `home` resolvendo para `/`.
      Cobre: RF-02, RF-03, RF-04, CT-01
      Acceptance criteria: `php artisan test --compact --filter="HomeEntrypoint|ExampleTest|ProfileUpdate|VerificationNotification"` verde sem editar `tests/Feature/ExampleTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php` nem `tests/Feature/Auth/VerificationNotificationTest.php`.
      Testes: `tests/Feature/HomeEntrypointTest.php` — este é o teste
- [ ] T07 — Teste do expurgo do starter kit
      Arquivos: `tests/Feature/Frontend/StarterKitCleanupTest.php`
      Mudança: criar em Pest; assertar ausência de `Welcome.vue`, `Dashboard.vue` e `DashboardTest.php`, `Route::getRoutes()->getByName('dashboard')` nulo, varredura por `dashboard` em `resources`, `routes`, `tests`, `app`, `config`, `bootstrap` excluindo `resources/js/{routes,actions,wayfinder}` e `vendor`, e as asserções de `frontendSource` de T03 e T04. Rodar `npm run build` antes da suíte.
      Cobre: RF-01, RF-05, RF-06, RF-08, RF-09
      Acceptance criteria: a varredura retorna 0 ocorrências de `dashboard`; `php artisan test --compact --filter="StarterKitCleanup|BoardShell"` verde após `npm run build` bem-sucedido.
      Testes: `tests/Feature/Frontend/StarterKitCleanupTest.php` — este é o teste

## Phase 2: Menu de usuário na prancheta

Antes de implementar, leia:
1. `.spec/features/remover-telas-starter-kit-logout-e-logo/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/remover-telas-starter-kit-logout-e-logo/PLAN.md` — decomposição completa, dependências e riscos
3. `.ai/rules/prancheta.md`, `.ai/rules/css.md` e `.ai/rules/feature.md` — contrato de `data-testid`, tokens `sd-*` e `seedCatalog()`

- [ ] T08 — Menu de usuário da prancheta (componente novo)
      Arquivos: `resources/js/components/prancheta/BoardUserMenu.vue`
      Mudança: raiz única; lê `usePage().props.auth.user` (já compartilhado por `HandleInertiaRequests::share()`), sem controller/service novo. Gatilho `<PranchetaButton>` com `aria-haspopup="menu"`, `:aria-expanded="open"` vinculado, iniciais de `auth.user.name` via `getInitials` sempre visíveis e nome completo visível em `md`+ / `sr-only` abaixo; nenhuma referência a `avatar`. Popup `role="menu"` com exatamente dois itens nesta ordem: `data-testid="user-menu-settings"` → `<Link :href="edit()">` (`@/routes/profile`, rótulo "Configurações") e `data-testid="user-menu-logout"` → `<Link :href="logout()" as="button" @click="router.flushAll()">` (`@/routes`, rótulo "Sair"). Handler de `Escape` fecha e devolve o foco ao gatilho. Só tokens `sd-*`, sem `dark:` e sem hex. Não reutilizar `UserMenuContent.vue`.
      Cobre: UI-01, UI-02, RNF-04, RF-07
      Acceptance criteria: `frontendSource('components/prancheta/BoardUserMenu.vue')` contém `user-menu-settings`, `user-menu-logout`, o binding do nome, as iniciais, `aria-haspopup="menu"`, `:aria-expanded=` e um handler de `Escape`; não contém `avatar`, `dark:` nem casa `/#[0-9a-fA-F]{3,8}\b/`; nenhum terceiro item de ação no menu.
      Testes: `tests/Feature/Frontend/BoardUserMenuTest.php` (T10) — asserções de fonte e a11y
- [ ] T09 — Compor o menu na barra superior
      Arquivos: `resources/js/components/prancheta/BoardTopBar.vue`
      Mudança: importar `BoardUserMenu` e renderizar `<BoardUserMenu data-testid="user-menu" />` como último filho do contêiner `data-testid="topbar"`, depois de `data-testid="save-button"`. Nenhum dos 9 `data-testid` existentes pode ser removido, renomeado ou reordenado.
      Cobre: UI-01, UI-03, RNF-03
      Acceptance criteria: no template, a ordem `problem-picker → sessions-button → topbar-spacer → save-chip → theme-button → export-button → save-button → user-menu` é crescente por `strpos`; `tests/Feature/Frontend/BoardShellTest.php` verde; o arquivo segue sem `dark:` e sem hex.
      Testes: `tests/Feature/Frontend/BoardUserMenuTest.php` (T10) — cadeia de ordem incluindo `user-menu`
- [ ] T10 — Testes do menu de usuário e do fluxo de saída
      Arquivos: `tests/Feature/Frontend/BoardUserMenuTest.php`
      Mudança: criar em Pest; teste HTTP com `seedCatalog()` + `actingAs` em `route('board')` 200; asserções de fonte de T08/T09; contrato de saída — `POST route('logout')` com `assertRedirect(route('login'))`, `assertGuest()`, token de sessão diferente e `GET route('board')` seguinte redirecionando para `login`. Rodar `npm run build` antes da suíte.
      Cobre: UI-01, UI-02, UI-03, RF-07, RNF-03, RNF-04
      Acceptance criteria: `php artisan test --compact --filter="BoardUserMenu|BoardShell"` verde após `npm run build`.
      Testes: `tests/Feature/Frontend/BoardUserMenuTest.php` — este é o teste

## Phase 3: Identidade maxdraw nas telas

Antes de implementar, leia:
1. `.spec/features/remover-telas-starter-kit-logout-e-logo/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/remover-telas-starter-kit-logout-e-logo/PLAN.md` — decomposição completa, dependências e riscos
3. `.ai/rules/css.md` e `.ai/rules/auth.md` — tokens `sd-*`, blocos de tema do `prancheta.css` e literais de rota de senha

- [ ] T11 — `AppLogoIcon` com o mark maxdraw
      Arquivos: `resources/js/components/AppLogoIcon.vue`, consumidores em `resources/js/components/AppLogo.vue`, `resources/js/layouts/auth/AuthSplitLayout.vue`, `resources/js/layouts/auth/AuthCardLayout.vue`, `resources/js/components/AppHeader.vue`
      Mudança: trocar `viewBox="0 0 40 42"` e o path do logotipo Laravel pelo mark de `.spec/init/design/logo/maxdraw-mark.svg` (`viewBox="0 0 32 32"`, `M9 9h14M9 9v14M9 23h14M23 9v14`, dois `rect` 12×12 `rx="3"`); decidir entre mark monocromático (`currentColor`) e cores fixas da marca e ajustar as classes dos quatro consumos. Hex não pode vazar para os cinco arquivos de RNF-02.
      Cobre: UI-04
      Acceptance criteria: `frontendSource('components/AppLogoIcon.vue')` contém `M9 9h14M9 9v14M9 23h14M23 9v14` e não contém `M17.2 5.633 8.6.855 0 5.633v26.51`.
      Testes: `tests/Feature/Frontend/BrandTest.php` (T16) — asserção de conteúdo do ícone
- [ ] T12 — Assets do lockup e seleção de variante sem JavaScript
      Arquivos: `public/brand/maxdraw-lockup-dark.svg`, `public/brand/maxdraw-lockup-light.svg`, `resources/css/brand.css`, `resources/css/app.css`, `resources/js/components/BrandLockup.vue`
      Mudança: copiar os dois lockups de `.spec/init/design/logo/` para `public/brand/`; criar `BrandLockup.vue` (raiz única, `<picture>`/par de `<img>` para `/brand/maxdraw-lockup-{dark,light}.svg`, texto alternativo acessível, zero JS na escolha); criar `resources/css/brand.css` com os três estados (`:root[data-theme='dark']`, `:root[data-theme='light']` e `@media (prefers-color-scheme: dark) { :root:not([data-theme='light']) … }`) e importá-lo em `app.css`. Proibido escrever essas regras em `resources/css/prancheta.css`.
      Cobre: UI-05
      Acceptance criteria: `resources/css/brand.css` contém as três condições; `BrandLockup.vue` não usa `useTheme`, `matchMedia` ou qualquer seleção por JS; `tests/Feature/Frontend/ThemeTokensTest.php` continua verde.
      Testes: `tests/Feature/Frontend/BrandTest.php` (T16) — três condições na fonte e ausência de JS
- [ ] T13 — Lockup nas telas de Login e Register
      Arquivos: `resources/js/pages/auth/Login.vue`, `resources/js/pages/auth/Register.vue`
      Mudança: renderizar `<BrandLockup data-testid="brand-lockup" />` uma única vez no topo do template de cada página, preservando imports/uso de `PranchetaInput`/`PranchetaButton`, os `<InputError :message="errors.X" />`, os links recíprocos `register()`/`login()` e os literais `/forgot-password` e `/reset-password`.
      Cobre: UI-05
      Acceptance criteria: `data-testid="brand-lockup"` aparece exatamente uma vez em cada uma das duas páginas; nenhuma das duas contém `dark:` nem casa `/#[0-9a-fA-F]{3,8}\b/`; `tests/Feature/Frontend/AuthScreensTest.php` verde.
      Testes: `tests/Feature/Frontend/BrandTest.php` (T16) — contagem do `data-testid` e ausência de hex
- [ ] T14 — `AuthSimpleLayout` sem bloco de marca
      Arquivos: `resources/js/layouts/auth/AuthSimpleLayout.vue`
      Mudança: remover o `<Link :href="home()">` com `<AppLogoIcon class="size-9 fill-current" />` e o `<span class="sr-only">`, junto dos imports órfãos (`AppLogoIcon`, `home` e `Link` se não sobrar uso). Preservar `data-testid="auth-shell"`, o bloco de título/descrição e as classes `bg-sd-paper`, `font-sd-ui`, `text-sd-ink`, `bg-sd-panel`, `border-sd-line`, `shadow-sd-2`. As demais telas de auth ficam sem marca e não recebem outra alteração.
      Cobre: UI-08
      Acceptance criteria: `frontendSource('layouts/auth/AuthSimpleLayout.vue')` não contém `AppLogoIcon` nem `home()`, segue sem `dark:` e sem hex; `tests/Feature/Frontend/AuthScreensTest.php` verde.
      Testes: `tests/Feature/Frontend/BrandTest.php` (T16) — asserção de ausência
- [ ] T15 — Marca maxdraw na barra superior da prancheta
      Arquivos: `resources/js/components/prancheta/BoardTopBar.vue`
      Mudança: substituir o `<svg>` de dois `rect` genéricos e o `<span>Prancheta</span>` pela marca maxdraw com rótulo acessível (`aria-label` ou `<span class="sr-only">`), mantendo o contêiner divisor e o `data-testid="topbar"`. Usar `<AppLogoIcon />` ou `<img src="/brand/…">` — não inlinar SVG com hex.
      Cobre: UI-06
      Acceptance criteria: `frontendSource('components/prancheta/BoardTopBar.vue')` não contém `<rect x="3" y="3" width="7" height="7" rx="1.5" />` nem `>Prancheta<`, contém rótulo acessível, e segue sem `dark:` e sem hex; nenhum `data-testid` existente mudou.
      Testes: `tests/Feature/Frontend/BrandTest.php` (T16) — asserções de ausência e de rótulo
- [ ] T16 — Testes de identidade visual
      Arquivos: `tests/Feature/Frontend/BrandTest.php`
      Mudança: criar em Pest; cobrir o path do mark em `AppLogoIcon`, o `data-testid="brand-lockup"` uma vez por página com as rotas `login`/`register` respondendo 200, as três condições de tema na fonte de marca sem JS, a ausência de `AppLogoIcon`/`home()` no `AuthSimpleLayout` e a ausência do ícone genérico/texto na `BoardTopBar`. Rodar `npm run build` antes da suíte.
      Cobre: UI-04, UI-05, UI-06, UI-08, RNF-02
      Acceptance criteria: `php artisan test --compact --filter="Brand|AuthScreens|BoardShell|ThemeTokens"` verde após `npm run build`.
      Testes: `tests/Feature/Frontend/BrandTest.php` — este é o teste

## Phase 4: Ícones do app

Antes de implementar, leia:
1. `.spec/features/remover-telas-starter-kit-logout-e-logo/SPEC.md` — requisitos RIGID que esta fase cobre (UI-07 e o pré-requisito PR-01)
2. `.spec/features/remover-telas-starter-kit-logout-e-logo/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T17 — Script pontual dos ícones do app e binários versionados
      Arquivos: `scripts/generate-brand-icons.sh`, `public/favicon.svg`, `public/favicon.ico`, `public/apple-touch-icon.png`
      Mudança: script de execução única no host, com guarda `command -v` para `rsvg-convert`/`magick`/`convert` (+ `icotool` quando só houver `rsvg-convert`), saindo com código ≠ 0 e mensagem explícita nomeando o pacote apt quando faltar o binário — nunca gerar arquivo vazio nem pular em silêncio. Saídas derivadas de `.spec/init/design/logo/maxdraw-mark.svg`: `favicon.svg` = cópia byte a byte; `apple-touch-icon.png` = 180×180 achatado sobre `#0A0E13`; `favicon.ico` = derivado do mesmo mark. Rodar uma vez e commitar os três binários. Não alterar `resources/views/app.blade.php` nem `package.json`/`composer.json`.
      Cobre: UI-07, PR-01
      Acceptance criteria: sem rasterizador, o script sai ≠ 0 com mensagem nomeando o pacote; com rasterizador, `public/favicon.svg` contém `M9 9h14M9 9v14M9 23h14M23 9v14`, `file public/apple-touch-icon.png` reporta `180 x 180` sem transparência, e os três arquivos deixam de ser byte-idênticos aos do skeleton; `git diff --exit-code composer.json package.json` limpo.
      Testes: `tests/Feature/Frontend/BrandAssetsTest.php` (T18) — path do SVG, dimensão/opacidade do PNG e diferença binária
- [ ] T18 — Teste dos ícones do app
      Arquivos: `tests/Feature/Frontend/BrandAssetsTest.php`
      Mudança: criar em Pest; assertar o traço do mark em `public/favicon.svg`; `getimagesize(public_path('apple-touch-icon.png'))` igual a 180×180 e amostragem por GD sem pixel totalmente transparente; `hash_file('sha256', …)` diferente das constantes do skeleton (`favicon.svg` `242f4f8f93f5fbc6c8aeec500c9bac02dcfe68daba166d484c5cdc986c88d8ee`, `favicon.ico` `4606a56e6ef3f5ec39201497f57069d5457ce9cea25227134d0ba378788e9070`, `apple-touch-icon.png` `4001aa032ff113e1a268a9bbf1ab0fd9949439f9f54a85895956eb323aba977d`); `resources/views/app.blade.php` com as três tags apontando para os mesmos caminhos.
      Cobre: UI-07
      Acceptance criteria: `php artisan test --compact --filter=BrandAssets` verde.
      Testes: `tests/Feature/Frontend/BrandAssetsTest.php` — este é o teste

## Phase 5: Fechamento e gate

Antes de implementar, leia:
1. `.spec/features/remover-telas-starter-kit-logout-e-logo/SPEC.md` — requisitos RIGID que esta fase cobre (RNF-01, RNF-03, RNF-05)
2. `.spec/features/remover-telas-starter-kit-logout-e-logo/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T19 — Gate final e verificações de não-regressão
      Arquivos: nenhum arquivo de aplicação; `vendor/bin/pint --dirty --format agent` sobre o PHP tocado (`routes/web.php` e os testes novos)
      Mudança: rodar `npm run build` e depois exatamente `composer ci:check` (não trocar de runner); conferir ausência de baseline nova de PHPStan e de `ignoreErrors` novo em `phpstan.neon`; `git diff --exit-code composer.json package.json`; `git diff --exit-code resources/js/pages/settings`; os 9 `data-testid` da topbar intactos; `php artisan route:list --name=dashboard` com 0 rotas e `--name=home` com 1 rota em `/`.
      Cobre: RNF-01, RNF-03, RNF-05, RF-01, RF-04, RF-09
      Acceptance criteria: `composer ci:check` termina com código de saída 0; os dois `git diff --exit-code` ficam limpos; `phpstan.neon` sem baseline/`ignoreErrors` novos.
      Testes: suíte completa via `composer ci:check` (npm lint/format/types + vitest + `composer test`)
