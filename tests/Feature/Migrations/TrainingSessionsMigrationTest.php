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
        'show_connection_order' => true,
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
            'id', 'user_id', 'problem_id', 'name', 'session_duration_id', 'show_connection_order',
            'elapsed_seconds', 'notes', 'nodes', 'edges', 'checks', 'estimate',
            'last_opened_at', 'created_at', 'updated_at',
        ])
        ->and(hasIndex('training_sessions', ['user_id', 'last_opened_at']))->toBeTrue()
        ->and(hasIndex('training_sessions', ['user_id', 'created_at']))->toBeTrue();

    $columns = collect(Schema::getColumns('training_sessions'))->keyBy('name');

    expect($columns['problem_id']['nullable'])->toBeTrue()
        ->and($columns['name']['nullable'])->toBeTrue()
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

    expect(domainTables())->toHaveCount(13);
});

test('no_enum_columns_anywhere', function () {
    foreach (domainTables() as $table) {
        foreach (Schema::getColumns($table) as $column) {
            expect($column['type_name'])->not->toBe('enum')
                ->and($column['type'])->not->toStartWith('enum');
        }
    }
});

/**
 * O diagrama de uma sessão como ele era antes da ordem explícita: nenhuma
 * aresta carrega `order`, e é justamente isso que o backfill precisa acertar.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function legacyDiagram(int $seed): array
{
    return [
        'nodes' => [
            ['id' => 'n1', 'type' => 'api', 'label' => 'API «REST» nº '.$seed, 'x' => 320, 'y' => 180],
            ['id' => 'n2', 'type' => 'sql', 'label' => 'Coleção de dados — réplica', 'x' => 520, 'y' => 180],
        ],
        'edges' => [
            ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => 'query', 'label' => 'SELECT', 'dashed' => false, 'bidir' => false],
            ['id' => 'e2', 'from' => 'n2', 'to' => 'n1', 'kind' => '', 'label' => 'resposta', 'dashed' => true, 'bidir' => true],
        ],
    ];
}

/**
 * Grava uma sessão com o schema anterior às duas migrations desta feature —
 * sem `show_connection_order` e com a FK do modo de numeração.
 *
 * @param  array<string, array<int, array<string, mixed>>>  $diagram
 */
function insertLegacyTrainingSession(int $userId, int $durationId, int $sequenceModeId, array $diagram): void
{
    DB::table('training_sessions')->insert([
        'user_id' => $userId,
        'problem_id' => null,
        'session_duration_id' => $durationId,
        'sequence_mode_id' => $sequenceModeId,
        'nodes' => json_encode($diagram['nodes']),
        'edges' => json_encode($diagram['edges']),
        'checks' => json_encode(['7' => true]),
        'estimate' => json_encode(['mode' => 'user']),
        'notes' => 'o que eu escrevi ontem',
        'elapsed_seconds' => 1800,
        'last_opened_at' => now(),
    ]);
}

/**
 * Os dois modos de numeração que o schema anterior oferecia. A base de teste
 * nasce com sessões nos dois porque RF-14a exige que o backfill zere a
 * numeração de todas elas sem consultar o modo de nenhuma.
 *
 * @return array<int, int>
 */
function legacySequenceModeIds(): array
{
    return collect([
        ['name' => 'Ordem de saída de cada bloco', 'slug' => 'out', 'legend_text' => 'Cada bloco numera as próprias saídas.', 'position' => 1],
        ['name' => 'Ordem do fluxo inteiro', 'slug' => 'flow', 'legend_text' => 'Os passos na ordem em que acontecem.', 'position' => 2],
    ])
        ->map(fn (array $mode): int => DB::table('sequence_modes')->insertGetId($mode))
        ->all();
}

/**
 * Quantas migrations precisam voltar para o banco chegar ao schema anterior às
 * duas migrations da ordem explícita. Contado no repositório em vez de fixado
 * em dois: toda migration acrescentada depois delas entra no caminho de volta.
 */
function stepsBackToBeforeTheOrderMigrations(): int
{
    return DB::table('migrations')
        ->where('migration', '>=', '2026_08_23_085307_add_show_connection_order_to_training_sessions_table')
        ->count();
}

/**
 * Volta o banco ao schema anterior à feature e o semeia com sessões antigas,
 * alternadas entre os dois modos de numeração.
 *
 * @return array<int, array<string, mixed>>
 */
function seedTheBaseBeforeTheOrderMigrations(int $sessions): array
{
    test()->artisan('migrate:rollback', ['--step' => stepsBackToBeforeTheOrderMigrations()])->assertSuccessful();

    $userId = User::factory()->create()->id;
    $durationId = DB::table('session_durations')->insertGetId(['minutes' => 45, 'is_default' => true, 'position' => 2]);
    $modeIds = legacySequenceModeIds();

    foreach (range(1, $sessions) as $seed) {
        insertLegacyTrainingSession($userId, $durationId, $modeIds[$seed % count($modeIds)], legacyDiagram($seed));
    }

    return diagramsOnDisk();
}

/**
 * O par `nodes`/`edges` de toda sessão, já decodificado — comparar estrutura,
 * nunca string: o `json_encode` da migration não precisa casar byte a byte
 * com o que gravou o diagrama.
 *
 * @return array<int, array<string, mixed>>
 */
function diagramsOnDisk(): array
{
    return DB::table('training_sessions')->orderBy('id')->get()
        ->map(fn (object $row): array => [
            'nodes' => json_decode($row->nodes, true),
            'edges' => json_decode($row->edges, true),
        ])
        ->all();
}

test('the_order_migration_keeps_every_diagram_and_backfills_a_null_order', function () {
    $before = seedTheBaseBeforeTheOrderMigrations(3);

    expect(DB::table('training_sessions')->distinct()->pluck('sequence_mode_id'))->toHaveCount(2);

    $this->artisan('migrate')->assertSuccessful();

    $after = diagramsOnDisk();

    expect(DB::table('training_sessions')->count())->toBe(3)
        ->and($after)->toHaveCount(3);

    foreach ($after as $index => $diagram) {
        expect($diagram['nodes'])->toBe($before[$index]['nodes'])
            ->and(array_map(
                fn (array $edge): array => collect($edge)->except('order')->all(),
                $diagram['edges'],
            ))->toBe($before[$index]['edges']);
    }

    $orders = collect($after)->flatMap(fn (array $diagram): array => array_column($diagram['edges'], 'order'));

    expect($orders)->toHaveCount(6)
        ->and($orders->reject(fn (mixed $order): bool => is_null($order)))->toBeEmpty()
        ->and(DB::table('training_sessions')->where('show_connection_order', '!=', true)->count())->toBe(0);
});

test('the_order_migration_keeps_the_other_columns_of_every_session', function () {
    seedTheBaseBeforeTheOrderMigrations(2);

    $before = DB::table('training_sessions')->orderBy('id')
        ->get(['id', 'user_id', 'problem_id', 'session_duration_id', 'elapsed_seconds', 'notes', 'checks', 'estimate', 'last_opened_at']);

    $this->artisan('migrate')->assertSuccessful();

    $after = DB::table('training_sessions')->orderBy('id')
        ->get(['id', 'user_id', 'problem_id', 'session_duration_id', 'elapsed_seconds', 'notes', 'checks', 'estimate', 'last_opened_at']);

    expect($after->toArray())->toEqual($before->toArray());
});

test('rolling_the_order_migrations_back_never_drops_a_diagram', function () {
    seedTheBaseBeforeTheOrderMigrations(2);

    $this->artisan('migrate')->assertSuccessful();

    $before = diagramsOnDisk();

    $this->artisan('migrate:rollback', ['--step' => stepsBackToBeforeTheOrderMigrations()])->assertSuccessful();

    expect(Schema::hasTable('sequence_modes'))->toBeTrue()
        ->and(Schema::hasColumn('training_sessions', 'sequence_mode_id'))->toBeTrue()
        ->and(Schema::hasColumn('training_sessions', 'show_connection_order'))->toBeFalse()
        ->and(collect(Schema::getColumns('training_sessions'))->firstWhere('name', 'sequence_mode_id')['nullable'])->toBeTrue()
        ->and(DB::table('training_sessions')->count())->toBe(2)
        ->and(diagramsOnDisk())->toBe($before);
});

test('the_sequence_mode_lookup_and_its_foreign_key_are_gone', function () {
    expect(Schema::hasTable('sequence_modes'))->toBeFalse()
        ->and(Schema::getColumnListing('training_sessions'))->not->toContain('sequence_mode_id')
        ->and(collect(Schema::getForeignKeys('training_sessions'))->pluck('foreign_table'))
        ->not->toContain('sequence_modes');
});

test('show_connection_order_is_true_without_being_written', function () {
    DB::table('training_sessions')->insert([
        'user_id' => User::factory()->create()->id,
        'problem_id' => null,
        'session_duration_id' => DB::table('session_durations')->insertGetId(['minutes' => 45, 'is_default' => true, 'position' => 2]),
        'nodes' => json_encode([]),
        'edges' => json_encode([]),
        'checks' => json_encode([]),
        'estimate' => json_encode(['mode' => 'user']),
        'last_opened_at' => now(),
    ]);

    expect((bool) DB::table('training_sessions')->value('show_connection_order'))->toBeTrue();
});
