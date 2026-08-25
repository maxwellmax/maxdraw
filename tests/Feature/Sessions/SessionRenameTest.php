<?php

use App\Models\ChecklistItem;
use App\Models\Problem;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Nomear e renomear são a mesma operação (US-11.1): o mesmo `PUT` do autosave,
 * com um corpo que traz só o nome — aparado na FormRequest, gravado pelo
 * `SessionStateWriter` e devolvido pelo Resource, sem tocar em mais nada.
 */
beforeEach(function () {
    seedCatalog();
});

/**
 * Uma sessão do usuário com o nome que o exemplo pedir.
 */
function sessionNamed(User $user, ?string $name): TrainingSession
{
    return TrainingSession::factory()->create(['user_id' => $user->id, 'name' => $name]);
}

test('the_name_survives_the_round_trip_and_a_second_rename_replaces_it', function () {
    $user = User::factory()->create();
    $session = sessionNamed($user, null);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), ['name' => 'Feed — 2ª tentativa'])
        ->assertOk()
        ->assertJsonStructure(['id', 'updated_at']);

    expect($this->actingAs($user)->getJson(route('sessions.index'))->assertOk()->json('data.0.name'))
        ->toBe('Feed — 2ª tentativa');

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), ['name' => 'Encurtador de URL'])
        ->assertOk();

    expect($session->fresh()->name)->toBe('Encurtador de URL');
});

test('the_name_is_stored_as_the_request_normalized_it', function (mixed $sent, ?string $stored) {
    $user = User::factory()->create();
    $session = sessionNamed($user, 'Nome anterior');

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), ['name' => $sent])
        ->assertOk();

    expect($session->fresh()->name)->toBe($stored);
})->with('validNames');

dataset('validNames', fn (): array => [
    'apara as pontas' => ['  Feed  ', 'Feed'],
    'só espaços é o mesmo que sessão sem nome' => ['   ', null],
    'string vazia é o mesmo que sessão sem nome' => ['', null],
    'nulo explícito apaga o nome' => [null, null],
    'sessenta caracteres cabem no teto' => [str_repeat('a', 60), str_repeat('a', 60)],
    'o teto se mede depois do aparo' => ['  '.str_repeat('a', 60).'  ', str_repeat('a', 60)],
]);

test('an_autosave_without_the_key_leaves_the_stored_name_untouched', function () {
    $user = User::factory()->create();
    $session = sessionNamed($user, 'Feed de rede social');

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody())
        ->assertOk();

    expect($session->fresh()->name)->toBe('Feed de rede social');
});

test('a_name_the_request_cannot_fix_is_refused_and_nothing_is_stored', function (mixed $sent) {
    $user = User::factory()->create();
    $session = sessionNamed($user, 'Nome anterior');

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), ['name' => $sent])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    expect($session->fresh()->name)->toBe('Nome anterior');
})->with('invalidNames');

dataset('invalidNames', fn (): array => [
    'um caractere acima do teto' => [str_repeat('a', 61)],
    'número não é nome' => [42],
    'array não é nome' => [['Feed']],
]);

test('the_name_over_the_limit_is_refused_with_the_portuguese_message', function () {
    $user = User::factory()->create();
    $session = sessionNamed($user, null);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), ['name' => str_repeat('a', 61)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name' => 'O nome da sessão tem no máximo 60 caracteres.']);
});

test('the_rename_touches_nothing_but_the_name_and_the_updated_at', function () {
    $user = User::factory()->create();

    $session = TrainingSession::factory()
        ->withProblem(Problem::query()->firstOrFail())
        ->create([
            'user_id' => $user->id,
            'name' => null,
            'notes' => 'anotações do treino',
            'elapsed_seconds' => 742,
            'show_connection_order' => false,
            'checks' => [ChecklistItem::query()->firstOrFail()->id => true],
            'nodes' => [['id' => 'n1', 'type' => 'api', 'label' => 'API REST', 'x' => 320, 'y' => 180]],
            'edges' => [],
            'last_opened_at' => now()->subHours(3),
        ]);

    $before = $session->fresh()->getAttributes();

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), ['name' => 'Feed'])
        ->assertOk();

    $after = $session->fresh()->getAttributes();

    expect(Arr::except($after, ['name', 'updated_at']))->toBe(Arr::except($before, ['name', 'updated_at']))
        ->and($after['name'])->toBe('Feed');
});

test('the_rename_keeps_the_order_of_the_session_sheet', function () {
    $user = User::factory()->create();

    $ids = collect(range(1, 3))
        ->map(fn (int $hours): int => TrainingSession::factory()->create([
            'user_id' => $user->id,
            'last_opened_at' => now()->subHours($hours),
        ])->id)
        ->all();

    $order = fn (): array => $this->actingAs($user)->getJson(route('sessions.index'))->assertOk()->json('data.*.id');

    $before = $order();

    $this->actingAs($user)
        ->putJson(route('sessions.update', TrainingSession::query()->findOrFail($ids[2])), ['name' => 'A mais antiga'])
        ->assertOk();

    expect($order())->toBe($before);
});

test('a_session_of_another_user_is_not_renamed_and_does_not_reveal_itself', function () {
    $intruder = User::factory()->create();
    $session = sessionNamed(User::factory()->create(), 'Feed do dono');

    $this->actingAs($intruder)
        ->putJson(route('sessions.update', $session), ['name' => 'invadido'])
        ->assertNotFound();

    expect($session->fresh()->name)->toBe('Feed do dono');
});
