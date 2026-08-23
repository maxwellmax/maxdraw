<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('catalog_seed_produces_expected_counts', function () {
    $this->artisan('db:seed')->assertSuccessful();

    $counts = collect(catalogTables())
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();

    expect($counts)->toBe([
        'problem_levels' => 3,
        'problem_item_types' => 3,
        'component_categories' => 6,
        'link_types' => 9,
        'session_durations' => 3,
        'estimate_modes' => 2,
        'problems' => 14,
        'problem_items' => 230,
        'components' => 28,
        'phases' => 5,
        'checklist_items' => 25,
    ]);
});

test('catalog_has_no_dangling_foreign_keys', function () {
    $this->artisan('db:seed')->assertSuccessful();

    $checked = 0;

    foreach (catalogTables() as $table) {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            [$column] = $foreignKey['columns'];
            [$foreignColumn] = $foreignKey['foreign_columns'];
            $foreignTable = $foreignKey['foreign_table'];

            $dangling = DB::table($table)
                ->whereNotNull($column)
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from($foreignTable)
                    ->whereColumn("{$foreignTable}.{$foreignColumn}", "{$table}.{$column}"))
                ->count();

            expect($dangling)->toBe(
                0,
                "{$table}.{$column} tem {$dangling} linha(s) apontando para {$foreignTable}.{$foreignColumn} inexistente",
            );

            $checked++;
        }
    }

    expect($checked)->toBe(5);
});
