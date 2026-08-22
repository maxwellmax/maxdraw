<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function insertProblemLevel(): int
{
    return DB::table('problem_levels')->insertGetId([
        'name' => 'Base',
        'slug' => 'base',
        'position' => 1,
    ]);
}

function insertProblem(int $problemLevelId, string $slug = 'url'): int
{
    return DB::table('problems')->insertGetId([
        'slug' => $slug,
        'name' => 'Encurtador de URL',
        'tag' => 'Chave-valor · Cache',
        'problem_level_id' => $problemLevelId,
        'context' => 'Enunciado em prosa.',
        'position' => 1,
    ]);
}

function insertProblemItemType(string $slug = 'requirement'): int
{
    return DB::table('problem_item_types')->insertGetId([
        'name' => 'Requisito funcional',
        'slug' => $slug,
    ]);
}

function insertPhase(int $position = 1, string $slug = 'requisitos'): int
{
    return DB::table('phases')->insertGetId([
        'slug' => $slug,
        'name' => 'Requisitos & escopo',
        'weight' => '0.110',
        'position' => $position,
    ]);
}

test('problems_table_matches_the_schema', function () {
    expect(Schema::hasColumns('problems', [
        'id', 'slug', 'name', 'tag', 'problem_level_id', 'context', 'position', 'is_active', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(hasUniqueIndex('problems', ['slug']))->toBeTrue()
        ->and(hasIndex('problems', ['problem_level_id', 'position']))->toBeTrue();
});

test('problem_items_cascade_on_problem_delete', function () {
    $problemId = insertProblem(insertProblemLevel());
    $problemItemTypeId = insertProblemItemType();

    DB::table('problem_items')->insert([
        'problem_id' => $problemId,
        'problem_item_type_id' => $problemItemTypeId,
        'position' => 1,
        'content' => 'Encurtar uma URL longa.',
    ]);

    expect(DB::table('problem_items')->count())->toBe(1);

    DB::table('problems')->where('id', $problemId)->delete();

    expect(DB::table('problem_items')->count())->toBe(0);
});

test('problem_items_position_is_unique_per_type', function () {
    $problemId = insertProblem(insertProblemLevel());
    $problemItemTypeId = insertProblemItemType();

    $row = [
        'problem_id' => $problemId,
        'problem_item_type_id' => $problemItemTypeId,
        'position' => 1,
        'content' => 'Encurtar uma URL longa.',
    ];

    DB::table('problem_items')->insert($row);

    expect(fn () => DB::table('problem_items')->insert($row))->toThrow(QueryException::class);

    DB::table('problem_items')->insert([...$row, 'problem_item_type_id' => insertProblemItemType('scale')]);

    expect(DB::table('problem_items')->count())->toBe(2);
});

test('components_table_matches_the_schema', function () {
    expect(Schema::hasColumns('components', [
        'id', 'component_category_id', 'slug', 'name', 'short_name', 'icon_key', 'position', 'is_active', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(hasUniqueIndex('components', ['slug']))->toBeTrue()
        ->and(hasIndex('components', ['component_category_id', 'position']))->toBeTrue();
});

test('checklist_items_cascade_on_phase_delete', function () {
    $phaseId = insertPhase();

    DB::table('checklist_items')->insert([
        'phase_id' => $phaseId,
        'position' => 1,
        'content' => 'Listei os requisitos funcionais.',
    ]);

    expect(DB::table('checklist_items')->count())->toBe(1);

    DB::table('phases')->where('id', $phaseId)->delete();

    expect(DB::table('checklist_items')->count())->toBe(0);
});

test('checklist_item_position_is_unique_per_phase', function () {
    $phaseId = insertPhase();

    $row = ['phase_id' => $phaseId, 'position' => 1, 'content' => 'Listei os requisitos funcionais.'];

    DB::table('checklist_items')->insert($row);

    expect(fn () => DB::table('checklist_items')->insert($row))->toThrow(QueryException::class);
});

test('phase_position_is_unique', function () {
    insertPhase(position: 1, slug: 'requisitos');

    expect(fn () => insertPhase(position: 1, slug: 'estimativas'))->toThrow(QueryException::class);

    expect(hasUniqueIndex('phases', ['position']))->toBeTrue()
        ->and(hasUniqueIndex('phases', ['slug']))->toBeTrue();
});

test('phase_weight_keeps_three_decimal_places', function () {
    insertPhase();

    expect((float) DB::table('phases')->value('weight'))->toBe(0.11);
});
