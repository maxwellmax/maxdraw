<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @param  array<string, mixed>  $overrides
 */
function insertTrainingSession(int $userId, array $overrides = []): int
{
    return DB::table('training_sessions')->insertGetId([
        'user_id' => $userId,
        'problem_id' => null,
        'session_duration_id' => DB::table('session_durations')->insertGetId([
            'minutes' => 45,
            'is_default' => true,
            'position' => 2,
        ]),
        'sequence_mode_id' => DB::table('sequence_modes')->insertGetId([
            'name' => 'Ordem de saída de cada bloco',
            'slug' => 'out',
            'legend_text' => 'Cada bloco numera as próprias saídas.',
            'position' => 1,
        ]),
        'nodes' => json_encode([]),
        'edges' => json_encode([]),
        'checks' => json_encode([]),
        'estimate' => json_encode(['mode' => 'user']),
        'last_opened_at' => now(),
        ...$overrides,
    ]);
}

test('users_table_matches_the_schema', function () {
    expect(Schema::hasColumns('users', [
        'id', 'name', 'email', 'email_verified_at', 'password', 'remember_token', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(hasUniqueIndex('users', ['email']))->toBeTrue()
        ->and(Schema::hasColumn('users', 'deleted_at'))->toBeFalse();
});

test('training_sessions_table_matches_the_schema', function () {
    expect(Schema::hasTable('training_sessions'))->toBeTrue()
        ->and(Schema::getColumnListing('training_sessions'))->toEqualCanonicalizing([
            'id', 'user_id', 'problem_id', 'session_duration_id', 'sequence_mode_id',
            'elapsed_seconds', 'notes', 'nodes', 'edges', 'checks', 'estimate',
            'last_opened_at', 'created_at', 'updated_at',
        ])
        ->and(hasIndex('training_sessions', ['user_id', 'last_opened_at']))->toBeTrue()
        ->and(hasIndex('training_sessions', ['user_id', 'created_at']))->toBeTrue();

    $columns = collect(Schema::getColumns('training_sessions'))->keyBy('name');

    expect($columns['problem_id']['nullable'])->toBeTrue()
        ->and($columns['notes']['nullable'])->toBeTrue()
        ->and($columns['last_opened_at']['nullable'])->toBeFalse()
        ->and($columns['nodes']['nullable'])->toBeFalse()
        ->and($columns['edges']['nullable'])->toBeFalse()
        ->and($columns['checks']['nullable'])->toBeFalse()
        ->and($columns['estimate']['nullable'])->toBeFalse();
});

test('training_session_starts_without_a_problem_and_with_no_elapsed_time', function () {
    insertTrainingSession(User::factory()->create()->id);

    $session = DB::table('training_sessions')->first();

    expect($session->problem_id)->toBeNull()
        ->and($session->elapsed_seconds)->toBe(0)
        ->and($session->notes)->toBeNull();
});

test('training_sessions_cascade_on_user_delete', function () {
    $user = User::factory()->create();
    insertTrainingSession($user->id);

    expect(DB::table('training_sessions')->count())->toBe(1);

    DB::table('users')->where('id', $user->id)->delete();

    expect(DB::table('training_sessions')->count())->toBe(0);
});

test('problem_delete_is_restricted_while_referenced', function () {
    $problemId = DB::table('problems')->insertGetId([
        'slug' => 'url',
        'name' => 'Encurtador de URL',
        'tag' => 'Chave-valor · Cache',
        'problem_level_id' => DB::table('problem_levels')->insertGetId([
            'name' => 'Base',
            'slug' => 'base',
            'position' => 1,
        ]),
        'context' => 'Enunciado em prosa.',
        'position' => 1,
    ]);

    insertTrainingSession(User::factory()->create()->id, ['problem_id' => $problemId]);

    expect(fn () => DB::table('problems')->where('id', $problemId)->delete())
        ->toThrow(QueryException::class);

    expect(DB::table('problems')->count())->toBe(1);
});

test('no_soft_deletes_anywhere', function () {
    foreach (domainTables() as $table) {
        expect(Schema::hasTable($table))->toBeTrue()
            ->and(Schema::hasColumn($table, 'deleted_at'))->toBeFalse();
    }

    expect(domainTables())->toHaveCount(14);
});

test('no_enum_columns_anywhere', function () {
    foreach (domainTables() as $table) {
        foreach (Schema::getColumns($table) as $column) {
            expect($column['type_name'])->not->toBe('enum')
                ->and($column['type'])->not->toStartWith('enum');
        }
    }
});
