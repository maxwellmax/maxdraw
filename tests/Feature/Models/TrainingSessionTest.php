<?php

use App\Models\Problem;
use App\Models\SessionDuration;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;

test('training_session_casts_json_columns_to_array', function () {
    $nodes = [['id' => 'n0', 'type' => 'api', 'label' => 'API', 'x' => 120, 'y' => 100]];
    $edges = [['id' => 'e0', 'from' => 'n0', 'to' => 'n1', 'kind' => 'http', 'label' => '', 'dashed' => false, 'bidir' => false]];
    $checks = [7 => true, 12 => true];
    $estimate = ['mode' => 'user', 'dau' => 1000000, 'act' => 10, 'per_month' => 10000000, 'ratio' => 100, 'size' => 1, 'peak' => 3, 'ret' => 3];

    $session = TrainingSession::factory()->create([
        'nodes' => $nodes,
        'edges' => $edges,
        'checks' => $checks,
        'estimate' => $estimate,
    ]);

    $fresh = TrainingSession::query()->findOrFail($session->id);

    expect($fresh->nodes)->toBeArray()->toBe($nodes)
        ->and($fresh->edges)->toBeArray()->toBe($edges)
        ->and($fresh->checks)->toBeArray()->toBe($checks)
        ->and($fresh->estimate)->toBeArray()->toBe($estimate)
        ->and($fresh->elapsed_seconds)->toBeInt()
        ->and($fresh->last_opened_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('duration_minutes_accessor_reads_lookup', function () {
    SessionDuration::factory()->default()->create();

    $session = TrainingSession::factory()->create();

    expect($session->duration_minutes)->toBe(45)
        ->and($session->sessionDuration->minutes)->toBe(45)
        ->and(TrainingSession::query()->findOrFail($session->id)->duration_minutes)->toBe(45);
});

test('duration_minutes_accessor_follows_the_session_duration_row', function () {
    $sixty = SessionDuration::factory()->create(['minutes' => 60]);

    $session = TrainingSession::factory()->create(['session_duration_id' => $sixty->id]);

    expect($session->duration_minutes)->toBe(60);
});

test('deleting_user_deletes_their_sessions', function () {
    $user = User::factory()->create();
    TrainingSession::factory()->count(3)->create(['user_id' => $user->id]);
    $survivor = TrainingSession::factory()->create();

    $user->delete();

    expect(TrainingSession::query()->count())->toBe(1)
        ->and(TrainingSession::query()->first()->id)->toBe($survivor->id)
        ->and($survivor->user->trainingSessions)->toHaveCount(1);
});

test('user_has_many_training_sessions', function () {
    $user = User::factory()->create();
    TrainingSession::factory()->count(2)->create(['user_id' => $user->id]);
    TrainingSession::factory()->create();

    expect($user->trainingSessions)->toHaveCount(2)
        ->and($user->trainingSessions->first()->user->id)->toBe($user->id);
});

test('training_session_belongs_to_its_lookups_and_optional_problem', function () {
    $session = TrainingSession::factory()->create();

    expect($session->problem)->toBeNull()
        ->and($session->sessionDuration)->toBeInstanceOf(SessionDuration::class)
        ->and(method_exists(TrainingSession::class, 'sequenceMode'))->toBeFalse();

    $withProblem = TrainingSession::factory()->withProblem()->create();

    expect($withProblem->problem)->toBeInstanceOf(Problem::class);
});

/**
 * Sem o cast a coluna volta do SQLite como `1`/`0`, e a comparação estrita que
 * o cliente faz sobre `show_connection_order` deixa de valer.
 */
test('show_connection_order_is_cast_to_a_boolean', function (bool $value) {
    $session = TrainingSession::factory()->create(['show_connection_order' => $value]);

    $fresh = TrainingSession::query()->findOrFail($session->id);

    expect($fresh->show_connection_order)->toBeBool()->toBe($value)
        ->and($fresh->show_connection_order === $value)->toBeTrue();
})->with([
    'ligada' => [true],
    'desligada' => [false],
]);

test('a_session_is_born_with_the_connection_order_visible', function () {
    expect(TrainingSession::factory()->create()->show_connection_order)->toBeTrue();
});

test('no_model_uses_soft_deletes', function () {
    $models = collect(File::files(app_path('Models')))
        ->map(fn ($file): string => 'App\\Models\\'.$file->getFilenameWithoutExtension())
        ->filter(fn (string $model): bool => is_subclass_of($model, Model::class));

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        expect(class_uses_recursive($model))->not->toContain(SoftDeletes::class);
    }
});
