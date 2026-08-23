<?php

use App\Models\TrainingSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * As notas da sessão (Phase 15.2): texto livre da sessão, com o limite de
 * 5.000 caracteres que o cliente também respeita antes de enviar (US-6.3).
 */
beforeEach(function () {
    seedCatalog();
});

test('notes_longer_than_5000_are_rejected_with_422', function () {
    $user = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id, 'notes' => 'o que eu já tinha escrito']);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['notes' => str_repeat('n', 5001)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('notes');

    expect($session->refresh()->notes)->toBe('o que eu já tinha escrito');

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['notes' => str_repeat('n', 5000)]))
        ->assertOk();

    expect($session->refresh()->notes)->toHaveLength(5000);
});

test('notes_belong_to_the_session_and_come_back_when_it_is_reopened', function () {
    $user = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['notes' => "particionar por user_id\ncache na borda"]))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->where('session.notes', "particionar por user_id\ncache na borda")
            ->etc());
});
