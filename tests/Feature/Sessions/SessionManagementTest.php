<?php

use App\Models\Problem;
use App\Models\SessionDuration;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionCreator;

/**
 * Gerenciar sessões (Phase 19): listar, abrir, criar e excluir. A corrente é
 * sempre a de maior `last_opened_at`, e o usuário nunca fica sem uma.
 */
beforeEach(function () {
    seedCatalog();
});

/**
 * A sessão corrente do usuário, lida pelo mesmo caminho que a prancheta usa.
 */
function currentSession(User $user): TrainingSession
{
    return TrainingSession::find(
        test()->actingAs($user)
            ->get(route('board'))
            ->assertOk()
            ->viewData('page')['props']['session']['id']
    );
}

test('session_list_is_ordered_by_recency', function () {
    $user = User::factory()->create();

    $oldest = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subWeek()]);
    $newest = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()]);
    $middle = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subDay()]);

    TrainingSession::factory()->count(2)->create(['user_id' => User::factory()->create()->id]);

    $response = $this->actingAs($user)->getJson(route('sessions.index'))->assertOk();

    expect($response->json('data.*.id'))->toBe([$newest->id, $middle->id, $oldest->id]);
});

test('the_list_carries_the_date_the_problem_the_duration_and_the_time_used', function () {
    $user = User::factory()->create();
    $problem = Problem::query()->where('slug', 'feed')->sole();

    $session = TrainingSession::factory()->withProblem($problem)->withDiagram(3, 2)->create([
        'user_id' => $user->id,
        'elapsed_seconds' => 742,
        'session_duration_id' => SessionDuration::query()->where('minutes', 60)->value('id'),
        'last_opened_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $session->id)
        ->assertJsonPath('data.0.problem_id', $problem->id)
        ->assertJsonPath('data.0.duration_minutes', 60)
        ->assertJsonPath('data.0.elapsed_seconds', 742)
        ->assertJsonCount(3, 'data.0.nodes')
        ->assertJsonPath('data.0.last_opened_at', $session->last_opened_at->toJSON())
        ->assertJsonMissingPath('data.0.user_id');
});

test('opening_a_session_restores_full_state', function () {
    $user = User::factory()->create();
    $problem = Problem::query()->where('slug', 'drive')->sole();

    $saved = TrainingSession::factory()->withProblem($problem)->withDiagram(4, 3)->create([
        'user_id' => $user->id,
        'notes' => 'o que eu escrevi ontem',
        'checks' => [7 => true, 12 => true],
        'estimate' => ['mode' => 'month', 'dau' => 0, 'act' => 0, 'per_month' => 500000000, 'ratio' => 100, 'size' => 1, 'peak' => 3, 'ret' => 3],
        'elapsed_seconds' => 1800,
        'show_connection_order' => false,
        'last_opened_at' => now()->subDays(3),
    ]);

    TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()]);

    $this->travel(1)->minute();

    $this->actingAs($user)
        ->postJson(route('sessions.open', $saved))
        ->assertOk()
        ->assertJsonPath('data.id', $saved->id)
        ->assertJsonPath('data.problem_id', $problem->id)
        ->assertJsonPath('data.notes', 'o que eu escrevi ontem')
        ->assertJsonPath('data.checks', ['7' => true, '12' => true])
        ->assertJsonPath('data.estimate.per_month', 500000000)
        ->assertJsonPath('data.elapsed_seconds', 1800)
        ->assertJsonPath('data.show_connection_order', false)
        ->assertJsonCount(4, 'data.nodes')
        ->assertJsonCount(3, 'data.edges');

    $current = currentSession($user);

    expect($current->id)->toBe($saved->id)
        ->and($current->notes)->toBe('o que eu escrevi ontem')
        ->and($current->checks)->toBe(['7' => true, '12' => true])
        ->and($current->elapsed_seconds)->toBe(1800)
        ->and($current->show_connection_order)->toBeFalse();
});

test('opening_a_foreign_session_never_restores_it', function () {
    $foreign = TrainingSession::factory()->create([
        'user_id' => User::factory()->create()->id,
        'notes' => 'segredo do outro usuário',
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('sessions.open', $foreign));

    expect($response->status())->toBeIn([403, 404]);

    $response->assertDontSee($foreign->notes);
});

test('switching_sessions_saves_the_current_one_first', function () {
    $user = User::factory()->create();

    $current = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()]);
    $other = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subDay()]);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $current), autosaveBody(['notes' => 'o último traço antes de trocar']))
        ->assertOk();

    $this->travel(1)->minute();

    $this->actingAs($user)->postJson(route('sessions.open', $other))->assertOk();

    $current->refresh();

    expect(currentSession($user)->id)->toBe($other->id)
        ->and($current->notes)->toBe('o último traço antes de trocar')
        ->and($current->elapsed_seconds)->toBe(742)
        ->and($current->nodes)->toHaveCount(2)
        ->and($current->edges)->toHaveCount(1)
        ->and($current->duration_minutes)->toBe(60);
});

test('new_session_starts_empty_with_defaults', function () {
    $user = User::factory()->create();

    TrainingSession::factory()->withDiagram(3, 2)->create([
        'user_id' => $user->id,
        'notes' => 'o treino de antes',
        'elapsed_seconds' => 900,
    ]);

    $created = $this->actingAs($user)
        ->postJson(route('sessions.store'))
        ->assertCreated()
        ->assertJsonPath('data.nodes', [])
        ->assertJsonPath('data.edges', [])
        ->assertJsonPath('data.checks', [])
        ->assertJsonPath('data.notes', null)
        ->assertJsonPath('data.elapsed_seconds', 0)
        ->assertJsonPath('data.duration_minutes', 45)
        ->assertJsonPath('data.show_connection_order', true)
        ->assertJsonPath('data.problem_id', null)
        ->assertJsonPath('data.estimate', SessionCreator::DEFAULT_ESTIMATE)
        ->json('data.id');

    expect(SessionCreator::DEFAULT_ESTIMATE['mode'])->toBe('user')
        ->and(TrainingSession::find($created)->estimate)->toBe(SessionCreator::DEFAULT_ESTIMATE);
});

test('creating_a_session_preserves_previous_ones', function () {
    $user = User::factory()->create();
    $problem = Problem::query()->where('slug', 'chat')->sole();

    $previous = TrainingSession::factory()->withProblem($problem)->withDiagram(5, 4)->create([
        'user_id' => $user->id,
        'notes' => 'nada disso pode sumir',
        'checks' => [3 => true],
        'elapsed_seconds' => 1200,
    ]);

    $older = TrainingSession::factory()->create([
        'user_id' => $user->id,
        'notes' => 'nem isto',
        'last_opened_at' => now()->subWeek(),
    ]);

    $created = $this->actingAs($user)->postJson(route('sessions.store'))->assertCreated()->json('data.id');

    expect(TrainingSession::query()->count())->toBe(3);

    $previous->refresh();

    expect($previous->id)->not->toBe($created)
        ->and($previous->problem_id)->toBe($problem->id)
        ->and($previous->notes)->toBe('nada disso pode sumir')
        ->and($previous->checks)->toBe(['3' => true])
        ->and($previous->elapsed_seconds)->toBe(1200)
        ->and($previous->nodes)->toHaveCount(5)
        ->and($previous->edges)->toHaveCount(4)
        ->and($older->fresh()->notes)->toBe('nem isto');
});

test('new_session_becomes_current', function () {
    $user = User::factory()->create();

    TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()]);

    $created = $this->actingAs($user)->postJson(route('sessions.store'))->assertCreated()->json('data.id');

    $current = currentSession($user);

    expect($current->id)->toBe($created)
        ->and($current->problem_id)->toBeNull()
        ->and($current->nodes)->toBe([]);
});

test('deleting_current_promotes_most_recent_remaining', function () {
    $user = User::factory()->create();

    $current = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()]);
    $runnerUp = TrainingSession::factory()->withDiagram(2, 1)->create([
        'user_id' => $user->id,
        'notes' => 'o treino que sobrou',
        'last_opened_at' => now()->subHour(),
    ]);
    $oldest = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subDays(3)]);

    $this->actingAs($user)
        ->deleteJson(route('sessions.destroy', $current))
        ->assertNoContent();

    expect(TrainingSession::find($current->id))->toBeNull()
        ->and(TrainingSession::query()->count())->toBe(2)
        ->and(TrainingSession::find($oldest->id))->not->toBeNull();

    $promoted = currentSession($user);

    expect($promoted->id)->toBe($runnerUp->id)
        ->and($promoted->notes)->toBe('o treino que sobrou')
        ->and($promoted->nodes)->toHaveCount(2);
});

test('deleting_the_only_session_creates_an_empty_one', function () {
    $user = User::factory()->create();

    $only = TrainingSession::factory()->withDiagram(4, 3)->create([
        'user_id' => $user->id,
        'notes' => 'tudo o que eu tinha',
        'checks' => [5 => true],
        'elapsed_seconds' => 2000,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('sessions.destroy', $only))
        ->assertNoContent();

    $replacement = TrainingSession::query()->sole();

    expect($replacement->id)->not->toBe($only->id)
        ->and($replacement->user_id)->toBe($user->id)
        ->and($replacement->nodes)->toBe([])
        ->and($replacement->edges)->toBe([])
        ->and($replacement->checks)->toBe([])
        ->and($replacement->notes)->toBeNull()
        ->and($replacement->elapsed_seconds)->toBe(0)
        ->and($replacement->duration_minutes)->toBe(45)
        ->and($replacement->show_connection_order)->toBeTrue()
        ->and($replacement->estimate)->toBe(SessionCreator::DEFAULT_ESTIMATE)
        ->and(currentSession($user)->id)->toBe($replacement->id);
});

test('deleting_foreign_session_is_forbidden', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $foreign = TrainingSession::factory()->withDiagram(3, 2)->create([
        'user_id' => $owner->id,
        'notes' => 'segredo do outro usuário',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('sessions.destroy', $foreign));

    expect($response->status())->toBeIn([403, 404]);

    $response->assertDontSee($foreign->notes);

    $foreign->refresh();

    expect($foreign->notes)->toBe('segredo do outro usuário')
        ->and($foreign->user_id)->toBe($owner->id)
        ->and(TrainingSession::ownedBy($user)->count())->toBe(0);
});

test('deleting_a_session_takes_the_whole_content_with_it', function () {
    $user = User::factory()->create();

    $doomed = TrainingSession::factory()->withDiagram(5, 4)->create([
        'user_id' => $user->id,
        'notes' => 'some junto',
        'checks' => [9 => true],
    ]);

    TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subDay()]);

    $this->actingAs($user)->deleteJson(route('sessions.destroy', $doomed))->assertNoContent();

    expect(TrainingSession::query()->where('id', $doomed->id)->exists())->toBeFalse()
        ->and(TrainingSession::query()->count())->toBe(1);
});
