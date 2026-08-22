<?php

use App\Models\ChecklistItem;
use App\Models\SequenceMode;
use App\Models\SessionDuration;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\Event;

/**
 * O autosave (Phase 8.3): o estado inteiro numa transação só, com a resposta
 * enxuta do contrato — `id` e `updated_at`, nada além disso.
 */
beforeEach(function () {
    seedCatalog();
});

function ownedSession(User $user): TrainingSession
{
    return TrainingSession::factory()->create(['user_id' => $user->id]);
}

test('update_persists_all_columns_in_one_transaction', function () {
    $user = User::factory()->create();
    $session = ownedSession($user);
    $item = ChecklistItem::query()->first();

    $transactions = ['begun' => 0, 'committed' => 0];

    Event::listen(TransactionBeginning::class, function () use (&$transactions): void {
        $transactions['begun']++;
    });

    Event::listen(TransactionCommitted::class, function () use (&$transactions): void {
        $transactions['committed']++;
    });

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['checks' => [$item->id => true]]))
        ->assertOk();

    expect($transactions)->toBe(['begun' => 1, 'committed' => 1]);

    $session->refresh();

    expect($session->nodes)->toHaveCount(2)
        ->and($session->nodes[0]['type'])->toBe('api')
        ->and($session->edges)->toHaveCount(1)
        ->and($session->edges[0]['kind'])->toBe('query')
        ->and($session->checks)->toBe([$item->id => true])
        ->and($session->estimate['mode'])->toBe('month')
        ->and($session->estimate['per_month'])->toBe(500000000)
        ->and($session->notes)->toBe('anotações do treino')
        ->and($session->elapsed_seconds)->toBe(742)
        ->and($session->duration_minutes)->toBe(60)
        ->and($session->session_duration_id)->toBe(SessionDuration::query()->where('minutes', 60)->value('id'))
        ->and($session->sequenceMode->slug)->toBe('flow')
        ->and($session->sequence_mode_id)->toBe(SequenceMode::query()->where('slug', 'flow')->value('id'));
});

test('update_normalizes_invalid_seq_mode_to_out', function (mixed $seqMode) {
    $user = User::factory()->create();
    $session = ownedSession($user);

    $session->update(['sequence_mode_id' => SequenceMode::query()->where('slug', 'flow')->value('id')]);

    $body = autosaveBody();

    if (is_null($seqMode)) {
        unset($body['seq_mode']);
    } else {
        $body['seq_mode'] = $seqMode;
    }

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), $body)
        ->assertOk();

    expect($session->fresh()->sequenceMode->slug)->toBe('out');
})->with([
    'ausente' => [null],
    'inexistente no catálogo' => ['telepatia'],
    'string vazia' => [''],
    'número' => [7],
]);

test('update_rejects_unknown_checklist_item_key', function (mixed $key) {
    $user = User::factory()->create();
    $session = ownedSession($user);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['checks' => [$key => true]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('checks');

    expect($session->fresh()->checks)->toBe([]);
})->with([
    'id que não existe' => [999999],
    'chave fase:índice do protótipo' => ['3:1'],
]);

test('update_returns_id_and_updated_at', function () {
    $user = User::factory()->create();
    $session = ownedSession($user);

    $response = $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody())
        ->assertOk()
        ->assertJsonPath('id', $session->id);

    expect(array_keys($response->json()))->toBe(['id', 'updated_at'])
        ->and($response->json('updated_at'))->toBe($session->fresh()->updated_at->toJSON());
});

test('update_of_foreign_session_is_forbidden', function () {
    $session = TrainingSession::factory()->create([
        'user_id' => User::factory()->create()->id,
        'notes' => 'segredo do outro usuário',
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->putJson(route('sessions.update', $session), autosaveBody());

    expect($response->status())->toBeIn([403, 404])
        ->and($session->fresh()->notes)->toBe('segredo do outro usuário')
        ->and($session->fresh()->nodes)->toBe([]);

    $response->assertDontSee($session->notes);
});

test('the_autosave_keeps_the_order_of_the_edges_as_it_arrives', function () {
    $user = User::factory()->create();
    $session = ownedSession($user);

    $edges = [
        ['id' => 'e2', 'from' => 'n2', 'to' => 'n1', 'kind' => 'cache', 'label' => '', 'dashed' => false, 'bidir' => false],
        ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => 'query', 'label' => '', 'dashed' => false, 'bidir' => false],
    ];

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['edges' => $edges]))
        ->assertOk();

    expect(collect($session->fresh()->edges)->pluck('id')->all())->toBe(['e2', 'e1']);
});
