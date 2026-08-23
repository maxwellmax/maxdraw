<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('migrations_create_lookup_tables', function (string $table, array $columns, array $uniques) {
    expect(Schema::hasTable($table))->toBeTrue()
        ->and(Schema::hasColumns($table, $columns))->toBeTrue()
        ->and(Schema::getColumnListing($table))->toEqualCanonicalizing($columns);

    foreach ($uniques as $unique) {
        expect(hasUniqueIndex($table, $unique))->toBeTrue();
    }

    foreach ($columns as $column) {
        expect(Schema::getColumnType($table, $column))->not->toBe('enum');
    }
})->with([
    'problem_levels' => [
        'problem_levels',
        ['id', 'name', 'slug', 'position', 'is_active', 'created_at', 'updated_at'],
        [['slug']],
    ],
    'problem_item_types' => [
        'problem_item_types',
        ['id', 'name', 'slug', 'description', 'is_active', 'created_at', 'updated_at'],
        [['slug']],
    ],
    'component_categories' => [
        'component_categories',
        ['id', 'name', 'slug', 'color_token', 'position', 'is_active', 'created_at', 'updated_at'],
        [['slug'], ['color_token']],
    ],
    'link_types' => [
        'link_types',
        ['id', 'name', 'slug', 'badge_label', 'dash_array', 'is_bidirectional_default', 'gloss', 'position', 'is_active', 'created_at', 'updated_at'],
        [['slug']],
    ],
    'session_durations' => [
        'session_durations',
        ['id', 'minutes', 'is_default', 'position', 'is_active', 'created_at', 'updated_at'],
        [['minutes']],
    ],
    'estimate_modes' => [
        'estimate_modes',
        ['id', 'name', 'slug', 'highlighted_row', 'position', 'is_active', 'created_at', 'updated_at'],
        [['slug']],
    ],
]);

test('lookup_nullable_columns_follow_the_schema', function () {
    $problemItemTypes = collect(Schema::getColumns('problem_item_types'))->keyBy('name');
    $linkTypes = collect(Schema::getColumns('link_types'))->keyBy('name');

    expect($problemItemTypes['description']['nullable'])->toBeTrue()
        ->and($linkTypes['dash_array']['nullable'])->toBeTrue()
        ->and($linkTypes['gloss']['nullable'])->toBeFalse();
});

test('lookup_boolean_columns_have_the_declared_default', function () {
    DB::table('problem_levels')->insert(['name' => 'Base', 'slug' => 'base', 'position' => 1]);
    DB::table('session_durations')->insert(['minutes' => 45, 'position' => 1]);
    DB::table('link_types')->insert([
        'name' => 'HTTP / REST',
        'slug' => 'http',
        'badge_label' => 'HTTP',
        'gloss' => 'requisição e resposta; o chamador fica esperando',
        'position' => 1,
    ]);

    expect((bool) DB::table('problem_levels')->value('is_active'))->toBeTrue()
        ->and((bool) DB::table('session_durations')->value('is_default'))->toBeFalse()
        ->and((bool) DB::table('link_types')->value('is_bidirectional_default'))->toBeFalse();
});

test('the_sequence_mode_lookup_no_longer_exists', function () {
    expect(Schema::hasTable('sequence_modes'))->toBeFalse()
        ->and(catalogTables())->not->toContain('sequence_modes')
        ->and(domainTables())->not->toContain('sequence_modes');
});
