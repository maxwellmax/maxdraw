<?php

use App\Models\Problem;
use App\Models\ProblemItem;
use App\Models\ProblemLevel;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(CatalogSeeder::class));

/**
 * A fonte versionada dos enunciados — é contra ela que o round-trip é conferido.
 *
 * @return array<int, array<string, mixed>>
 */
function problemSource(): array
{
    return require database_path('seeders/data/problems.php');
}

test('problem_seeder_is_idempotent', function () {
    $firstRun = DB::table('problem_items')->count();

    $this->seed(CatalogSeeder::class);

    expect(DB::table('problem_items')->count())->toBe($firstRun)
        ->and(Problem::query()->count())->toBe(14);
});

test('problem_seeder_removes_items_left_over_from_a_longer_list', function () {
    $problem = Problem::query()->where('slug', 'url')->sole();
    $lastRequirement = $problem->requirements()->get()->last();

    ProblemItem::create([
        'problem_id' => $problem->id,
        'problem_item_type_id' => $lastRequirement->problem_item_type_id,
        'position' => $lastRequirement->position + 1,
        'content' => 'Requisito que saiu do enunciado',
    ]);

    $this->seed(CatalogSeeder::class);

    expect($problem->requirements()->count())->toBe(5)
        ->and(ProblemItem::query()->where('content', 'Requisito que saiu do enunciado')->exists())->toBeFalse();
});

test('problem_items_keep_source_order', function () {
    $seeded = Problem::query()->with('problemItems.problemItemType')->get()
        ->mapWithKeys(fn (Problem $problem): array => [
            $problem->slug => [
                'requirements' => $problem->requirements()->pluck('content')->all(),
                'scale' => $problem->scaleTargets()->pluck('content')->all(),
                'topics' => $problem->topics()->pluck('content')->all(),
            ],
        ])
        ->all();

    $expected = collect(problemSource())
        ->mapWithKeys(fn (array $problem): array => [
            $problem['slug'] => [
                'requirements' => $problem['requirements'],
                'scale' => $problem['scale'],
                'topics' => $problem['topics'],
            ],
        ])
        ->all();

    expect($seeded)->toBe($expected);
});

test('problem_item_positions_are_sequential_inside_each_list', function () {
    foreach (Problem::query()->get() as $problem) {
        foreach (['requirements', 'scaleTargets', 'topics'] as $list) {
            $positions = $problem->{$list}()->pluck('position')->all();

            expect($positions)->toBe(range(1, count($positions)));
        }
    }
});

test('seeds_fourteen_problems_with_expected_slugs', function () {
    expect(Problem::query()->pluck('slug')->all())->toBe([
        'url',
        'feed',
        'chat',
        'video',
        'ride',
        'drive',
        'rate',
        'notif',
        'typeahead',
        'tickets',
        'crawler',
        'metrics',
        'pay',
        'leaderboard',
    ]);
});

test('problems_keep_the_prototype_name_tag_and_context', function () {
    $seeded = Problem::query()->get()->map->only(['slug', 'name', 'tag', 'context'])->all();

    $expected = collect(problemSource())
        ->map(fn (array $problem): array => [
            'slug' => $problem['slug'],
            'name' => $problem['name'],
            'tag' => $problem['tag'],
            'context' => $problem['context'],
        ])
        ->all();

    expect($seeded)->toBe($expected)
        ->and(Problem::query()->where('slug', 'url')->sole()->tag)->toBe('Chave-valor · Cache');
});

test('every_problem_has_all_three_lists_non_empty', function () {
    $counts = Problem::query()->get()
        ->mapWithKeys(fn (Problem $problem): array => [
            $problem->slug => [
                $problem->requirements()->count(),
                $problem->scaleTargets()->count(),
                $problem->topics()->count(),
            ],
        ])
        ->all();

    foreach ($counts as $slug => [$requirements, $scale, $topics]) {
        expect($requirements)->toBeGreaterThan(0, "problema [{$slug}] sem requisito funcional")
            ->and($scale)->toBeGreaterThan(0, "problema [{$slug}] sem item de escala")
            ->and($topics)->toBeGreaterThan(0, "problema [{$slug}] sem tópico do gabarito");
    }

    expect(ProblemItem::query()->count())->toBe(230);
});

test('problem_levels_distribution_matches_prototype', function () {
    $perLevel = ProblemLevel::query()->get()
        ->mapWithKeys(fn (ProblemLevel $level): array => [$level->slug => $level->problems()->count()])
        ->all();

    expect($perLevel)->toBe([
        'base' => 2,
        'intermediate' => 5,
        'advanced' => 7,
    ])
        ->and(Problem::query()->where('slug', 'url')->sole()->problemLevel->slug)->toBe('base')
        ->and(Problem::query()->where('slug', 'drive')->sole()->problemLevel->slug)->toBe('intermediate')
        ->and(Problem::query()->where('slug', 'feed')->sole()->problemLevel->slug)->toBe('advanced');
});
