<?php

use App\Models\ChecklistItem;
use App\Models\Component;
use App\Models\ComponentCategory;
use App\Models\EstimateMode;
use App\Models\LinkType;
use App\Models\Phase;
use App\Models\Problem;
use App\Models\ProblemItem;
use App\Models\ProblemItemType;
use App\Models\ProblemLevel;
use App\Models\SessionDuration;
use Database\Seeders\CatalogSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * O payload do autosave como o cliente o envia: diagrama, checklist, notas,
 * estimativa, tempo, duração e exibição da ordem de uma vez.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function autosaveBody(array $overrides = []): array
{
    return array_replace([
        'nodes' => [
            ['id' => 'n1', 'type' => 'api', 'label' => 'API REST', 'x' => 320, 'y' => 180],
            ['id' => 'n2', 'type' => 'sql', 'label' => 'Banco', 'x' => 520, 'y' => 180],
        ],
        'edges' => [
            ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => 'query', 'label' => 'SELECT', 'dashed' => false, 'bidir' => false, 'order' => 1],
        ],
        'checks' => [],
        'notes' => 'anotações do treino',
        'elapsed_seconds' => 742,
        'show_connection_order' => true,
        'duration_minutes' => 60,
        'estimate' => [
            'mode' => 'month',
            'dau' => 0,
            'act' => 0,
            'per_month' => 500000000,
            'ratio' => 100,
            'size' => 1,
            'peak' => 3,
            'ret' => 3,
        ],
    ], $overrides);
}

/**
 * O catálogo versionado que a aplicação lê em runtime. A prancheta não sobe sem
 * ele: sessão nova precisa da duração padrão, e o payload do autosave é
 * validado contra os slugs seedados aqui.
 */
function seedCatalog(): void
{
    test()->seed(CatalogSeeder::class);
}

/**
 * As 13 tabelas de domínio do `database-schema.md`, na ordem em que as
 * migrations as criam. Não inclui as tabelas de infraestrutura do Laravel.
 *
 * @return array<int, string>
 */
function domainTables(): array
{
    return [
        'problem_levels',
        'problem_item_types',
        'component_categories',
        'link_types',
        'session_durations',
        'estimate_modes',
        'problems',
        'problem_items',
        'components',
        'phases',
        'checklist_items',
        'users',
        'training_sessions',
    ];
}

/**
 * @param  array<int, string>  $columns
 */
function hasUniqueIndex(string $table, array $columns): bool
{
    return collect(Schema::getIndexes($table))
        ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === $columns);
}

/**
 * @param  array<int, string>  $columns
 */
function hasIndex(string $table, array $columns): bool
{
    return collect(Schema::getIndexes($table))
        ->contains(fn (array $index): bool => $index['columns'] === $columns);
}

/**
 * Executa o callback com a aplicação se reportando fora do console — é assim
 * que o guard de catálogo somente-leitura enxerga uma requisição HTTP.
 */
function asHttpRuntime(Closure $callback): void
{
    $property = new ReflectionProperty(app(), 'isRunningInConsole');
    $wasRunningInConsole = app()->runningInConsole();

    $property->setValue(app(), false);

    try {
        $callback();
    } finally {
        $property->setValue(app(), $wasRunningInConsole);
    }
}

/**
 * As tabelas do catálogo — a metade somente-leitura do schema, populada pelo
 * `CatalogSeeder` e nunca escrita pela aplicação.
 *
 * @return array<int, string>
 */
function catalogTables(): array
{
    return [
        'problem_levels',
        'problem_item_types',
        'component_categories',
        'link_types',
        'session_durations',
        'estimate_modes',
        'problems',
        'problem_items',
        'components',
        'phases',
        'checklist_items',
    ];
}

/**
 * Os models de catálogo já povoados pelo seeder desta fase.
 *
 * @return array<int, class-string<Model>>
 */
function catalogModels(): array
{
    return [
        ProblemLevel::class,
        ProblemItemType::class,
        ComponentCategory::class,
        Component::class,
        LinkType::class,
        Problem::class,
        ProblemItem::class,
        SessionDuration::class,
        EstimateMode::class,
        Phase::class,
        ChecklistItem::class,
    ];
}

/**
 * Retrato do catálogo inteiro sem os timestamps: o upsert do seeder reescreve
 * `updated_at` a cada execução, e é o conteúdo que precisa ser idêntico.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function catalogSnapshot(): array
{
    return collect(catalogTables())
        ->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->orderBy('id')->get()
                ->map(fn (object $row): array => collect((array) $row)->except(['created_at', 'updated_at'])->all())
                ->all(),
        ])
        ->all();
}

/**
 * As chaves do mapa de ícones do motor do canvas, lidas do próprio módulo — é
 * ele que decide quais `icon_key` o catálogo pode usar.
 *
 * @return array<int, string>
 */
function canvasIconKeys(): array
{
    $source = file_get_contents(resource_path('js/canvas/icons.ts'));

    preg_match_all('/^\s+([A-Za-z][A-Za-z0-9]*):\s*`/m', (string) $source, $matches);

    return $matches[1];
}

/**
 * Os nomes dos testes de um pacote do frontend, lidos dos arquivos do Vitest.
 * O gate da fase roda `php artisan test`, então é daqui que a suíte PHP
 * enxerga o que a suíte do cliente cobre.
 *
 * @return array<int, string>
 */
function frontendTestNames(string $directory): array
{
    $names = [];

    foreach (glob(resource_path('js/'.$directory.'/*.test.ts')) as $file) {
        preg_match_all("/\bit\('([^']+)'/", (string) file_get_contents($file), $matches);

        $names = [...$names, ...$matches[1]];
    }

    return $names;
}

/**
 * @return array<int, string>
 */
function canvasTestNames(): array
{
    return frontendTestNames('canvas');
}

/**
 * @return array<int, string>
 */
function pranchetaTestNames(): array
{
    return frontendTestNames('prancheta');
}

/**
 * Os arquivos do motor do canvas, pelo nome. Resolvido sem `resource_path()`
 * de propósito: datasets do Pest são montados antes de a aplicação subir.
 *
 * @return array<int, string>
 */
function canvasFiles(): array
{
    return array_map('basename', glob(dirname(__DIR__).'/resources/js/canvas/*.ts'));
}

/**
 * Os arquivos do pacote da prancheta, pelo nome. Resolvido sem
 * `resource_path()` pelo mesmo motivo que `canvasFiles()`.
 *
 * @return array<int, string>
 */
function pranchetaFiles(): array
{
    return array_map('basename', glob(dirname(__DIR__).'/resources/js/prancheta/*.ts'));
}

/**
 * Toda rota de sessão, com o verbo que ela aceita e se o caminho carrega uma
 * sessão. É a lista que o teste de isolamento entre usuários percorre: rota
 * nova aqui é rota nova lá, sem depender de ninguém lembrar (US-1.4).
 */
dataset('sessionRoutes', [
    'listagem' => ['GET', 'sessions.index', false],
    'criação' => ['POST', 'sessions.store', false],
    'leitura' => ['GET', 'sessions.show', true],
    'abertura' => ['POST', 'sessions.open', true],
    'escrita' => ['PUT', 'sessions.update', true],
    'exclusão' => ['DELETE', 'sessions.destroy', true],
]);

dataset('lookupModels', [
    'problem_levels' => [ProblemLevel::class],
    'problem_item_types' => [ProblemItemType::class],
    'component_categories' => [ComponentCategory::class],
    'link_types' => [LinkType::class],
    'session_durations' => [SessionDuration::class],
    'estimate_modes' => [EstimateMode::class],
]);

/**
 * O CSS do protótipo congelado — o contrato visual que a Phase 6 porta.
 */
function prototypeCss(): string
{
    return file_get_contents(base_path('.spec/init/design/pranchetasystemdesign.html'));
}

function pranchetaCss(): string
{
    return file_get_contents(resource_path('css/prancheta.css'));
}

/**
 * Lê os tokens declarados no primeiro bloco cujo seletor casa com o padrão, com
 * os valores normalizados: caixa, aspas, espaços e o zero antes do ponto variam
 * conforme o formatador, o hex em si não.
 *
 * @return array<string, string>
 */
function cssTokens(string $css, string $selectorPattern): array
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

    if (preg_match('/'.$selectorPattern.'\s*\{/', $css, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        return [];
    }

    $open = $matches[0][1] + strlen($matches[0][0]) - 1;
    $depth = 1;
    $cursor = $open + 1;

    while ($cursor < strlen($css) && $depth > 0) {
        $depth += match ($css[$cursor]) {
            '{' => 1,
            '}' => -1,
            default => 0,
        };

        $cursor++;
    }

    preg_match_all(
        '/(--[a-z0-9-]+)\s*:\s*([^;]+);/i',
        substr($css, $open + 1, $cursor - $open - 2),
        $declarations,
        PREG_SET_ORDER
    );

    $tokens = [];

    foreach ($declarations as $declaration) {
        $tokens[$declaration[1]] = normalizeCssValue($declaration[2]);
    }

    ksort($tokens);

    return $tokens;
}

function normalizeCssValue(string $value): string
{
    $value = str_replace('"', "'", mb_strtolower($value));
    $value = (string) preg_replace('/\s+/', '', $value);

    return (string) preg_replace('/(?<![0-9])0\./', '.', $value);
}

/**
 * O conteúdo de um arquivo do frontend, pelo caminho relativo a `resources/js`.
 */
function frontendSource(string $path): string
{
    return file_get_contents(resource_path('js/'.$path));
}
