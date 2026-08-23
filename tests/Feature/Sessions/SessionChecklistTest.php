<?php

use App\Models\ChecklistItem;
use App\Models\Phase;
use App\Models\TrainingSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * O checklist do roteiro (Phase 15.1): a marcação é um mapa de
 * `checklist_items.id` para verdadeiro, e é o id — não a posição do item no
 * roteiro — que faz a marcação sobreviver a uma edição do catálogo (US-6.2).
 */
beforeEach(function () {
    seedCatalog();
});

/**
 * O item de uma fase pela posição dele no roteiro.
 */
function checklistItemAt(int $phasePosition, int $itemPosition): ChecklistItem
{
    return ChecklistItem::query()
        ->where('phase_id', Phase::query()->where('position', $phasePosition)->value('id'))
        ->where('position', $itemPosition)
        ->sole();
}

test('checks_are_keyed_by_checklist_item_id', function () {
    $user = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);
    $item = checklistItemAt(2, 3);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['checks' => [$item->id => true]]))
        ->assertOk();

    expect($session->refresh()->checks)->toBe([$item->id => true]);

    // Um item novo entra na frente da fase e empurra os outros: a marcação
    // continua no mesmo texto, porque a chave é o id e não a posição.
    ChecklistItem::query()
        ->where('phase_id', $item->phase_id)
        ->orderByDesc('position')
        ->get()
        ->each(fn (ChecklistItem $existing) => $existing->update(['position' => $existing->position + 1]));

    ChecklistItem::create([
        'phase_id' => $item->phase_id,
        'position' => 1,
        'content' => 'Item novo no topo da fase',
        'is_active' => true,
    ]);

    $moved = ChecklistItem::query()->findOrFail($item->id);

    expect($session->refresh()->checks)->toBe([$item->id => true])
        ->and($moved->content)->toBe($item->content)
        ->and($moved->position)->toBe($item->position + 1);
});

test('checks_survive_round_trip_through_autosave', function () {
    $user = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);

    $checks = [
        checklistItemAt(1, 1)->id => true,
        checklistItemAt(3, 5)->id => true,
        checklistItemAt(5, 6)->id => true,
    ];

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['checks' => $checks]))
        ->assertOk();

    expect($session->refresh()->checks)->toBe($checks);

    $this->actingAs($user)
        ->getJson(route('sessions.show', $session))
        ->assertOk()
        ->assertJsonPath('data.checks', collect($checks)->mapWithKeys(
            fn (bool $done, int $id): array => [(string) $id => $done]
        )->all());

    $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->where('session.checks', collect($checks)->mapWithKeys(
                fn (bool $done, int $id): array => [(string) $id => $done]
            )->all())
            ->etc());
});

test('unknown_checklist_key_is_rejected', function (mixed $key) {
    $user = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['checks' => [$key => true]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('checks');

    expect($session->refresh()->checks)->toBe([]);
})->with([
    'id fora do catálogo' => [999999],
    'chave posicional do protótipo' => ['1:3'],
    'texto do item' => ['QPS médio e QPS de pico'],
]);

test('the_catalog_serves_the_25_items_split_by_phase', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->count('catalog.phases', 5)
            ->has('catalog.phases.0.checklist_items', 4)
            ->has('catalog.phases.1.checklist_items', 5)
            ->has('catalog.phases.2.checklist_items', 5)
            ->has('catalog.phases.3.checklist_items', 5)
            ->has('catalog.phases.4.checklist_items', 6)
            ->has('catalog.phases.0.checklist_items.0', fn (AssertableInertia $item) => $item
                ->where('id', checklistItemAt(1, 1)->id)
                ->where('content', checklistItemAt(1, 1)->content))
            ->etc());

    expect(ChecklistItem::query()->count())->toBe(25);
});
