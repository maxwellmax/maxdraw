<?php

use App\Models\TrainingSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * O fechamento do isolamento por usuário (US-1.4): leitura, escrita e exclusão
 * cruzadas em **todas** as rotas de sessão, mais o carregamento inicial da
 * prancheta — o caminho que entrega estado sem passar por `/api/sessions`.
 *
 * O segredo do outro usuário é ASCII de propósito: `assertDontSee` procura a
 * string literal no corpo, e um acento viraria `\uXXXX` no JSON, deixando a
 * asserção passar sem ter olhado para nada.
 */
const FOREIGN_SECRET = 'SEGREDO-DO-OUTRO-USUARIO';

/**
 * Retrato da linha para comparar depois. As chaves saem ordenadas porque o
 * Eloquent devolve o registro relido numa ordem diferente da do recém-criado, e
 * o que interessa é o conteúdo.
 *
 * @return array<string, mixed>
 */
function sessionRowSnapshot(?TrainingSession $session): array
{
    return is_null($session) ? [] : collect($session->getAttributes())->sortKeys()->all();
}

function sessionOfAnotherUser(): TrainingSession
{
    return TrainingSession::factory()->create([
        'user_id' => User::factory()->create()->id,
        'notes' => FOREIGN_SECRET,
    ]);
}

test('cross_user_read_write_delete_is_blocked_on_every_session_route', function (string $method, string $routeName, bool $needsSession) {
    seedCatalog();

    $intruder = User::factory()->create();
    $session = sessionOfAnotherUser();
    $owner = $session->user;
    $before = sessionRowSnapshot($session);

    $response = $this->actingAs($intruder)->json(
        $method,
        $needsSession ? route($routeName, $session) : route($routeName),
        ['user_id' => $owner->id, 'notes' => 'invadido', 'elapsed_seconds' => 999],
    );

    match ($needsSession) {
        true => expect($response->status())->toBeIn([403, 404]),
        false => expect($response->status())->toBeIn([200, 201]),
    };

    $response->assertDontSee(FOREIGN_SECRET);

    expect(sessionRowSnapshot($session->fresh()))->toBe($before)
        ->and(TrainingSession::query()->where('user_id', $owner->id)->pluck('id')->all())->toBe([$session->id])
        ->and(TrainingSession::ownedBy($intruder)->pluck('id')->all())->not->toContain($session->id);
})->with('sessionRoutes');

test('inertia_boot_never_leaks_foreign_sessions', function () {
    seedCatalog();

    $user = User::factory()->create();
    $own = TrainingSession::factory()->create(['user_id' => $user->id, 'notes' => 'MINHA-SESSAO']);

    $foreign = collect(range(1, 3))->map(fn (): TrainingSession => sessionOfAnotherUser());

    $response = $this->actingAs($user)->get(route('board'))->assertOk();

    $response->assertDontSee(FOREIGN_SECRET);

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Board')
        ->where('session.id', $own->id)
        ->where('session.notes', 'MINHA-SESSAO')
        ->missing('session.user_id')
        ->has('catalog')
    );

    foreach ($foreign as $session) {
        expect($response->viewData('page')['props']['session']['id'])->not->toBe($session->id);
    }
})->group('isolation');

test('no_authenticated_route_accepts_a_client_sent_user_id', function () {
    seedCatalog();

    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);

    $created = $this->actingAs($user)
        ->postJson(route('sessions.store'), ['user_id' => $other->id])
        ->assertCreated();

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), ['user_id' => $other->id, 'notes' => 'ainda minha'])
        ->assertOk();

    expect(TrainingSession::findOrFail($created->json('data.id'))->user_id)->toBe($user->id)
        ->and($session->fresh()->user_id)->toBe($user->id)
        ->and(TrainingSession::query()->where('user_id', $other->id)->exists())->toBeFalse();
});
