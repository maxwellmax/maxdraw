<?php

use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * O isolamento por usuário (US-1.4): as rotas de sessão carregam a linha já
 * escopada ao dono e ainda passam pela `TrainingSessionPolicy` — as duas
 * trancas, nunca só uma.
 */
function foreignSession(): TrainingSession
{
    return TrainingSession::factory()->create([
        'user_id' => User::factory()->create()->id,
        'notes' => 'segredo do outro usuário',
    ]);
}

test('user_cannot_read_another_users_session', function () {
    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('sessions.show', $session = foreignSession()));

    expect($response->status())->toBeIn([403, 404]);

    $response->assertDontSee($session->notes);
});

test('user_cannot_update_another_users_session', function () {
    $session = foreignSession();

    $response = $this->actingAs(User::factory()->create())
        ->putJson(route('sessions.update', $session), ['notes' => 'invadido']);

    expect($response->status())->toBeIn([403, 404])
        ->and($session->fresh()->notes)->toBe('segredo do outro usuário');

    $response->assertDontSee($session->notes);
});

test('user_cannot_delete_another_users_session', function () {
    $session = foreignSession();

    $response = $this->actingAs(User::factory()->create())
        ->deleteJson(route('sessions.destroy', $session));

    expect($response->status())->toBeIn([403, 404])
        ->and(TrainingSession::find($session->id))->not->toBeNull();
});

test('guest_gets_no_session_data_from_any_session_route', function (string $method, string $routeName, bool $needsSession) {
    $session = foreignSession();

    $response = $this->{$method}(
        $needsSession ? route($routeName, $session) : route($routeName)
    );

    expect($response->status())->toBeIn([401, 302]);

    $response->assertDontSee($session->notes);

    $this->assertGuest();
})->with([
    'listagem' => ['getJson', 'sessions.index', false],
    'leitura' => ['getJson', 'sessions.show', true],
    'escrita' => ['putJson', 'sessions.update', true],
    'exclusão' => ['deleteJson', 'sessions.destroy', true],
]);

test('session_list_contains_only_own_sessions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $own = TrainingSession::factory()->count(2)->create(['user_id' => $user->id]);
    $foreign = TrainingSession::factory()->count(3)->create([
        'user_id' => $other->id,
        'notes' => 'segredo do outro usuário',
    ]);

    $response = $this->actingAs($user)->getJson(route('sessions.index'))->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(collect($response->json('data'))->pluck('id')->all())
        ->toEqualCanonicalizing($own->pluck('id')->all());

    foreach ($foreign as $session) {
        $response->assertDontSee($session->notes);
    }
});

test('the_owner_reads_and_writes_their_own_session', function () {
    $user = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson(route('sessions.show', $session))
        ->assertOk()
        ->assertJsonPath('data.id', $session->id)
        ->assertJsonMissingPath('data.user_id');

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), [
            'notes' => 'anotações do treino',
            'elapsed_seconds' => 120,
        ])
        ->assertOk();

    expect($session->fresh()->notes)->toBe('anotações do treino')
        ->and($session->fresh()->elapsed_seconds)->toBe(120);

    $this->actingAs($user)
        ->deleteJson(route('sessions.destroy', $session))
        ->assertNoContent();

    expect(TrainingSession::find($session->id))->toBeNull();
});

test('session_routes_ignore_a_user_id_sent_by_the_client', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), [
            'user_id' => $other->id,
            'notes' => 'ainda minha',
        ])
        ->assertOk();

    expect($session->fresh()->user_id)->toBe($user->id);
});

test('the_policy_denies_every_ability_over_a_foreign_session', function (string $ability) {
    $session = foreignSession();

    expect(Gate::forUser(User::factory()->create())->allows($ability, $session))->toBeFalse()
        ->and(Gate::forUser($session->user)->allows($ability, $session))->toBeTrue();
})->with(['view', 'update', 'delete']);

test('the_owned_by_scope_keeps_the_query_on_the_authenticated_user', function () {
    $user = User::factory()->create();
    $own = TrainingSession::factory()->create(['user_id' => $user->id]);
    $foreign = foreignSession();

    $ids = TrainingSession::ownedBy($user)->pluck('id');

    expect($ids->all())->toBe([$own->id])
        ->and($ids)->not->toContain($foreign->id);
});
