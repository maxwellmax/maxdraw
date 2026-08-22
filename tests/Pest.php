<?php

use App\Models\ComponentCategory;
use App\Models\EstimateMode;
use App\Models\LinkType;
use App\Models\ProblemItemType;
use App\Models\ProblemLevel;
use App\Models\SequenceMode;
use App\Models\SessionDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

dataset('lookupModels', [
    'problem_levels' => [ProblemLevel::class],
    'problem_item_types' => [ProblemItemType::class],
    'component_categories' => [ComponentCategory::class],
    'link_types' => [LinkType::class],
    'sequence_modes' => [SequenceMode::class],
    'session_durations' => [SessionDuration::class],
    'estimate_modes' => [EstimateMode::class],
]);
