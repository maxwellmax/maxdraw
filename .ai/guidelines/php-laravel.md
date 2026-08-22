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