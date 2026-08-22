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
use App\Models\SequenceMode;
use App\Models\SessionDuration;
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
 * As 14 tabelas de domínio do `database-schema.md`, na ordem em que as
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
        'sequence_modes',
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
        'sequence_modes',
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
        SequenceMode::class,
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

dataset('lookupModels', [
    'problem_levels' => [ProblemLevel::class],
    'problem_item_types' => [ProblemItemType::class],
    'component_categories' => [ComponentCategory::class],
    'link_types' => [LinkType::class],
    'sequence_modes' => [SequenceMode::class],
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
