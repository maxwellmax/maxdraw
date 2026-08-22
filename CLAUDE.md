<laravel-boost-guidelines>
=== .ai/php-laravel rules ===

## General code instructions

- Comments above classes and methods: ONLY PHPDoc `/** ... */` blocks. NEVER put a `//`, `#`, or `/* */` comment directly above a `class`/`function` declaration. Inside method bodies, do NOT write comments that narrate WHAT the code does — the code must be self-documenting. Add a comment only to explain WHY non-obvious code exists. Don't add docblock comments when defining variables, unless instructed to, like `/** @var \App\Models\User $currentUser */`.
- For new features, you MUST generate Pest automated tests (the suite is Pest; do not add PHPUnit-style test classes).
- For library documentation, if some library is not available in Laravel Boost 'search-docs', always use context7. Automatically use the Context7 MCP tools to resolve library id and get library docs without me having to explicitly ask.
- If you made changes to CSS/Javascript files or added new Tailwind classes in Blade, run `npm run build` after all front-end changes are finished.

---

## PHP instructions

- **Conceitos de domínio neste projeto são tabelas de lookup, não Enums** (ver `.spec/init/database-schema.md`): nenhuma migration cria coluna `enum` e nenhum model casta atributo de domínio para Enum. As regras de Enum abaixo valem apenas para Enums que existam no código por outros motivos — nunca as use para justificar criar um Enum onde o schema definiu um lookup.
- In PHP, use `match` operator over `switch` whenever possible
- Generate Enums always in the folder `app/Enums`, not in the main `app/` folder, unless instructed differently.
- Always use Enum value as the default in the migration if column values are from the enum. Always casts this column to the enum type in the Model.
- Don't create temporary variables like `$currentUser = auth()->user()` if that variable is used only one time.
- Always use Enum where possible instead of hardcoded string values, if Enum class exists. For example, in Blade files, and in the tests when creating data if field is casted to Enum then use that Enum instead of hardcoding the value.
- Use `is_null($value)` instead of `$value === null` and `! is_null($value)` instead of `$value !== null` for null checks.

---

## Design de classes

Estes são os princípios SOLID na forma concreta que assumem neste projeto.
Onde já houver regra específica (controller magro, service, cálculo único), ela é a
aplicação — siga-a, não a versão abstrata.

- Uma classe, uma razão para mudar. Se o nome precisa de "and"/"Manager"/"Helper"
  para descrever o que ela faz, provavelmente são duas classes.
- Services e Actions de propósito único. Uma Action = um caso de uso. Não agrupe
  operações não relacionadas na mesma classe só por conveniência.
- Dependa de contratos, não de implementações concretas, quando houver mais de uma
  implementação real (ou uma real + um fake de teste). Injete por construtor.
- NÃO crie interface para uma classe com uma única implementação e nenhuma segunda
  à vista. Extraia o contrato quando a segunda implementação chegar, não antes.
- Prefira a solução mais simples que resolve o caso atual. Abstração se paga quando
  há duplicação real ou variação concreta — não por antecipação.

---

## Laravel instructions

- **O ambiente padrão deste projeto é o host**, com `php`, `composer` e `npm` globais: a aplicação sobe sem Docker (`php artisan serve` + `npm run dev`). O Docker Compose / Laravel Sail é uma trilha opcional, para quem clona o repositório público.
- Quando estiver trabalhando dentro do ambiente containerizado, prefixe TODOS os comandos com `./vendor/bin/sail` (`sail artisan migrate`, `sail artisan test`, `sail composer install`, `sail npm run build`) e mantenha o mesmo runner do início ao fim — nunca misture host e container no mesmo fluxo.
- Rode a suíte com o comando de teste que o documento da fase informar. Não troque de runner por conta própria: o gate de testes executa aquele comando exato, e um runner diferente dá verde para você e vermelho para o gate.
- Exceção que vale sempre: `php artisan boost:install` roda no host, nunca via `sail` — a detecção de agente do Boost usa `command -v claude`, que falha dentro do container.
- **Eloquent Observers** should be registered in Eloquent Models with PHP Attributes, and not in AppServiceProvider. Example: `#[ObservedBy([UserObserver::class])]` with `use Illuminate\Database\Eloquent\Attributes\ObservedBy;` on top
- Aim for "slim" Controllers/Components and put larger logic pieces in Service classes
- Use Laravel helpers instead of `use` section classes. Examples: use `auth()->id()` instead of `Auth::id()` and adding `Auth` in the `use` section. Other examples: use `redirect()->route()` instead of `Redirect::route()`, or `str()->slug()` instead of `Str::slug()`.
- Don't use `whereKey()` or `whereKeyNot()`, use specific fields like `id`. Example: instead of `->whereKeyNot($currentUser->getKey())`, use `->where('id', '!=', $currentUser->id)`.
- Don't add `::query()` when running Eloquent `create()` statements. Example: instead of `User::query()->create()`, use `User::create()`.
- When adding columns in a migration, update the model's `$fillable` array to include those new attributes.
- Never chain multiple migration-creating commands (e.g., `make:model -m`, `make:migration`) with `&&` or `;` — they may get identical timestamps. Run each command separately and wait for completion before running the next.
- Enums: If a PHP Enum exists for a domain concept, always use its cases (or their `->value`) instead of raw strings everywhere — routes, middleware, migrations, seeds, configs, and UI defaults.
- Don't create Controllers with just one method which just returns `view()`. Instead, use `Route::view()` with Blade file directly.
- Always use Laravel's @session() directive instead of @if(session()) for displaying flash messages in Blade templates.
- In Blade files always use `@selected()` and `@checked()` directives instead of `selected` and `checked` HTML attributes. Good example: @selected(old('status') === App\Enums\ProjectStatus::Pending->value). Bad example: {{ old('status') === App\Enums\ProjectStatus::Pending->value ? 'selected' : '' }}.

### Service classes

- Use Service classes to encapsulate reusable business logic, keeping Controllers slim.
- Service classes MUST be created in the `app/Services/` folder.
- If a Service is used in only ONE method of a Controller, inject it directly into that method via type-hinting. If it is used in MULTIPLE methods, initialize it in the Constructor.
- Services MUST NOT contain presentation logic (views, redirects, flash messages). Return data or throw exceptions, and let the Controller decide how to present the result.
- Services MUST be independently testable — avoid coupling with `request()`, `session()`, or `auth()` directly. Receive those values as parameters instead.

### Model construction rules

- Models MUST define the `$fillable` property correctly for all mass-assignable attributes.
- When adding new columns via migration, you MUST update the corresponding Model `$fillable` array.
- Relationships MUST follow Laravel naming conventions (`user()`, `orders()`, `profile()`, etc.).
- Relationship methods MUST use correct return types (`HasMany`, `BelongsTo`, `HasOne`, etc.).
- All relationships MUST have their inverse defined when applicable.
    - If `User` hasMany `Order`, then `Order` MUST define `belongsTo(User::class)`.
    - If `User` hasOne `Profile`, then `Profile` MUST define `belongsTo(User::class)`.
- Do not assume foreign key naming. Explicitly define foreign keys if they don't follow Laravel conventions.
- If a column represents a domain concept backed by an Enum, the Model MUST cast it using `$casts`.

---

## Inertia + Vue 3 instructions

This project's frontend is **Inertia v3 + Vue 3** (a versão entregue pelo starter kit oficial do Laravel 13; a `.spec` diz "Inertia 2" porque foi escrita antes do bump — não faça downgrade). It does NOT use Livewire — ignore any Livewire guidance. As regras de Blade das seções acima (`@session`, `@selected`, `@checked`, `Route::view`, controller que só retorna `view()`) só se aplicam a telas Blade: aqui toda tela é renderizada por `Inertia::render`, então elas não valem. For non-trivial Vue/Inertia work, activate the `inertia-vue-development` skill; for styling, activate `tailwindcss-development`.

- Server routes render pages via `Inertia::render('Directory/PageName', [...props])` from traditional Controllers/actions. Do not build API-style JSON endpoints for pages that Inertia renders.
- Vue pages live in `resources/js/Pages/` (mirroring the feature, e.g. `Pages/Quotes/Index.vue`); shared pieces in `resources/js/Components/` and `resources/js/Layouts/`.
- Every Vue component must have a single root element.
- Use `useForm` / the `<Form>` component from `@inertiajs/vue3` for forms and `<Link>`/`router` for navigation. Prefer partial reloads and Inertia features (deferred props, prefetching, polling, optimistic updates) over ad-hoc `fetch`.
- Keep business logic on the server (Controller → Service), never in `.vue` files — the frontend only binds, validates, and presents.

---

## Testing instructions

### Before Writing Tests

1. **Check database schema** - Use `database-schema` tool to understand:
    - Which columns have defaults
    - Which columns are nullable
    - Foreign key relationship names

2. **Verify relationship names** - Read the model file to confirm:
    - Exact relationship method names (not assumed from column names)
    - Return types and related models

3. **Test realistic states** - Don't assume:
    - Empty model = all nulls (check for defaults)
    - `user_id` foreign key = `user()` relationship (could be `author()`, `employer()`, etc.)
    - When testing form submissions that redirect back with errors, assert that old input is preserved using `assertSessionHasOldInput()`.

### Datasets

- Whenever you create or edit tests, evaluate whether multiple scenarios share the same test body and assertions, varying only the input. If they do, consolidate them into a **Pest dataset** (`->with([...])` inline, or a named `dataset('...', fn () => [...])` declared in `tests/Pest.php` / `tests/Datasets/`) with descriptive string keys, instead of duplicating near-identical tests.
- Do NOT introduce a dataset for a single scenario — only when there are two or more cases with the same body/assertions.
- When it aids readability, group cases by expected outcome (e.g. datasets `validX` / `invalidX`).

### Feature tests for pages

- Test Inertia pages as HTTP feature tests: hit the route with `$this->get(...)`/`$this->post(...)` and assert on the response and persisted state. Use `Inertia\Testing\AssertableInertia` (`->assertInertia(fn ($page) => $page->component('Quotes/Index')->has('quotes'))`) when you need to assert the component and props.
- When testing form submissions that redirect back with errors, assert old input is preserved with `assertSessionHasOldInput()`.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
