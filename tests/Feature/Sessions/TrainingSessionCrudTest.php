<?php

use App\Models\Problem;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionCreator;
use Illuminate\Support\Facades\Route;

/**
 * Criar, abrir e excluir sessão (Phase 8.2). A sessão corrente é sempre a de
 * maior `last_opened_at`, e o treino nunca fica sem uma.
 */
beforeEach(function () {
    seedCatalog();
});

/**
 * O id da sessão corrente do usuário, lido pelo mesmo caminho que a prancheta usa.
 */
function currentSessionId(User $user): int
{
    return test()->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->viewData('page')['props']['session']['id'];
}

test('the_session_routes_live_under_the_api_prefix_behind_the_web_auth_guard', function (string $name) {
    $route = Route::getRoutes()->getByName($name);

    expect($route->uri())->toStartWith('api/sessions')
        ->and($route->gatherMiddleware())->toContain('web')
        ->and($route->gatherMiddleware())->toContain('auth')
        ->and(collect($route->gatherMiddleware())->filter(fn (mixed $middleware): bool => is_string($middleware) && str_contains($middleware, 'sanctum')))
        ->toBeEmpty();
})->with(['sessions.index', 'sessions.store', 'sessions.show', 'sessions.open', 'sessions.update', 'sessions.destroy']);

test('store_rejects_duration_outside_30_45_60', function (mixed $minutes) {
    $this->actingAs(User::factory()->create())
        ->postJson(route('sessions.store'), ['duration_minutes' => $minutes])
        ->assertStatus(422)
        ->assertJsonValidationErrors('duration_minutes');

    expect(TrainingSession::query()->count())->toBe(0);
})->with([
    'abaixo do mínimo' => [15],
    'entre as opções' => [50],
    'acima do máximo' => [90],
    'zero' => [0],
    'negativo' => [-45],
]);

test('store_creates_empty_session_with_defaults', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('sessions.store'))
        ->assertCreated();

    $session = TrainingSession::query()->sole();

    $response->assertJsonPath('data.id', $session->id)
        ->assertJsonPath('data.problem_id', null)
        ->assertJsonPath('data.duration_minutes', 45)
        ->assertJsonPath('data.seq_mode', 'out')
        ->assertJsonPath('data.elapsed_seconds', 0)
        ->assertJsonPath('data.notes', null)
        ->assertJsonPath('data.nodes', [])
        ->assertJsonPath('data.edges', [])
        ->assertJsonPath('data.checks', [])
        ->assertJsonPath('data.estimate', SessionCreator::DEFAULT_ESTIMATE)
        ->assertJsonMissingPath('data.user_id');

    expect($session->user_id)->toBe($user->id);
});

test('store_accepts_a_problem_and_a_duration_from_the_lookup', function (int $minutes) {
    $problem = Problem::query()->where('slug', 'chat')->sole();

    $this->actingAs(User::factory()->create())
        ->postJson(route('sessions.store'), ['problem_id' => $problem->id, 'duration_minutes' => $minutes])
        ->assertCreated()
        ->assertJsonPath('data.problem_id', $problem->id)
        ->assertJsonPath('data.duration_minutes', $minutes);

    expect(TrainingSession::query()->sole()->sessionDuration->minutes)->toBe($minutes);
})->with([30, 45, 60]);

test('store_makes_new_session_current', function () {
    $user = User::factory()->create();

    $previous = TrainingSession::factory()->withDiagram(3, 2)->create([
        'user_id' => $user->id,
        'notes' => 'o que eu já tinha escrito',
        'elapsed_seconds' => 600,
        'last_opened_at' => now()->subMinute(),
    ]);

    $created = $this->actingAs($user)
        ->postJson(route('sessions.store'))
        ->assertCreated()
        ->json('data.id');

    expect(currentSessionId($user))->toBe($created)
        ->and($created)->not->toBe($previous->id);

    $previous->refresh();

    expect($previous->notes)->toBe('o que eu já tinha escrito')
        ->and($previous->elapsed_seconds)->toBe(600)
        ->and($previous->nodes)->toHaveCount(3)
        ->and($previous->edges)->toHaveCount(2);
});

test('open_session_updates_last_opened_at', function () {
    $user = User::factory()->create();

    $older = TrainingSession::factory()->withDiagram(4, 3)->create([
        'user_id' => $user->id,
        'notes' => 'treino de ontem',
        'elapsed_seconds' => 1800,
        'checks' => [],
        'last_opened_at' => now()->subDays(2),
    ]);

    TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()]);

    $this->travel(1)->minute();

    $this->actingAs($user)
        ->postJson(route('sessions.open', $older))
        ->assertOk()
        ->assertJsonPath('data.id', $older->id)
        ->assertJsonPath('data.notes', 'treino de ontem')
        ->assertJsonPath('data.elapsed_seconds', 1800)
        ->assertJsonCount(4, 'data.nodes')
        ->assertJsonCount(3, 'data.edges');

    expect($older->fresh()->last_opened_at->diffInSeconds(now()))->toBeLessThan(2)
        ->and(currentSessionId($user))->toBe($older->id);
});

test('open_of_foreign_session_is_forbidden', function () {
    $session = TrainingSession::factory()->create([
        'user_id' => User::factory()->create()->id,
        'notes' => 'segredo do outro usuário',
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('sessions.open', $session));

    expect($response->status())->toBeIn([403, 404]);

    $response->assertDontSee($session->notes);
});

test('delete_promotes_most_recent_remaining_to_current', function () {
    $user = User::factory()->create();

    $current = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()]);
    $runnerUp = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subHour()]);
    TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subDays(2)]);

    $this->actingAs($user)
        ->deleteJson(route('sessions.destroy', $current))
        ->assertNoContent();

    expect(TrainingSession::find($current->id))->toBeNull()
        ->and(TrainingSession::query()->count())->toBe(2)
        ->and(currentSessionId($user))->toBe($runnerUp->id);
});

test('deleting_last_session_creates_an_empty_one', function () {
    $user = User::factory()->create();

    $only = TrainingSession::factory()->withDiagram(2, 1)->create([
        'user_id' => $user->id,
        'notes' => 'tudo o que eu tinha',
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
        ->and($replacement->sequenceMode->slug)->toBe('out')
        ->and(currentSessionId($user))->toBe($replacement->id);
});

test('delete_of_foreign_session_is_forbidden', function () {
    $user = User::factory()->create();
    $foreign = TrainingSession::factory()->create([
        'user_id' => User::factory()->create()->id,
        'notes' => 'segredo do outro usuário',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('sessions.destroy', $foreign));

    expect($response->status())->toBeIn([403, 404])
        ->and(TrainingSession::find($foreign->id))->not->toBeNull();

    $response->assertDontSee($foreign->notes);
});

test('deleting_a_session_takes_its_whole_content_with_it', function () {
    $user = User::factory()->create();

    $doomed = TrainingSession::factory()->withDiagram(5, 4)->create([
        'user_id' => $user->id,
        'notes' => 'some junto',
        'checks' => [],
    ]);

    TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subDay()]);

    $this->actingAs($user)->deleteJson(route('sessions.destroy', $doomed))->assertNoContent();

    expect(TrainingSession::query()->where('id', $doomed->id)->exists())->toBeFalse();
});

test('a_guest_reaches_none_of_the_session_actions', function (string $method, string $routeName, bool $needsSession) {
    $session = TrainingSession::factory()->create(['user_id' => User::factory()->create()->id]);

    $response = $this->{$method}($needsSession ? route($routeName, $session) : route($routeName));

    expect($response->status())->toBeIn([401, 302]);

    $this->assertGuest();
})->with([
    'criação' => ['postJson', 'sessions.store', false],
    'abertura' => ['postJson', 'sessions.open', true],
]);
