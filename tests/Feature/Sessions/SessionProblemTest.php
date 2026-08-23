<?php

use App\Models\Problem;
use App\Models\TrainingSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Escolher o problema (Phase 16.1): a escolha entra na sessão corrente pelo
 * mesmo autosave que grava o resto, e o enunciado volta inteiro no catálogo
 * do carregamento seguinte (US-2.1).
 */
beforeEach(function () {
    seedCatalog();
});

test('choosing_a_problem_records_it_on_the_current_session', function () {
    $user = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);
    $problem = Problem::query()->where('slug', 'feed')->sole();

    expect($session->problem_id)->toBeNull();

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['problem_id' => $problem->id]))
        ->assertOk();

    expect($session->refresh()->problem_id)->toBe($problem->id);
});

test('the_free_board_clears_the_problem_of_the_current_session', function () {
    $user = User::factory()->create();
    $session = TrainingSession::factory()
        ->withProblem(Problem::query()->where('slug', 'feed')->sole())
        ->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['problem_id' => null]))
        ->assertOk();

    expect($session->refresh()->problem_id)->toBeNull();
});

test('a_problem_outside_the_catalog_is_refused', function () {
    $user = User::factory()->create();
    $session = TrainingSession::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['problem_id' => 99999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('problem_id');

    expect($session->refresh()->problem_id)->toBeNull();
});

test('the_board_carries_the_whole_brief_of_the_chosen_problem', function () {
    $user = User::factory()->create();
    $problem = Problem::query()->where('slug', 'url')->sole();

    TrainingSession::factory()->withProblem($problem)->create(['user_id' => $user->id]);

    $props = $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('session.problem_id', $problem->id)
            ->etc())
        ->viewData('page')['props'];

    $brief = collect($props['catalog']['problems'])->firstWhere('id', $problem->id);

    expect($brief['name'])->toBe('Encurtador de URL')
        ->and($brief['tag'])->toBe('Chave-valor · Cache')
        ->and($brief['level'])->toBe('base')
        ->and($brief['level_name'])->toBe('Base')
        ->and($brief['level_position'])->toBe(1)
        ->and($brief['context'])->toStartWith('Um serviço que recebe uma URL longa')
        ->and($brief['requirements'])->toHaveCount(5)
        ->and($brief['scale'])->toHaveCount(5)
        ->and($brief['topics'])->toHaveCount(8);
});

test('every_problem_of_the_picker_carries_name_tag_and_level', function () {
    $problems = $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->viewData('page')['props']['catalog']['problems'];

    expect($problems)->toHaveCount(14);

    foreach ($problems as $problem) {
        expect($problem['name'])->not->toBeEmpty()
            ->and($problem['tag'])->not->toBeEmpty()
            ->and($problem['level_name'])->toBeIn(['Base', 'Intermediário', 'Avançado'])
            ->and($problem['level_position'])->toBeIn([1, 2, 3]);
    }
});

test('the_three_lists_arrive_in_the_order_they_were_stored', function () {
    $problem = Problem::query()->with('problemItems.problemItemType')->where('slug', 'url')->sole();

    $brief = collect(
        $this->actingAs(User::factory()->create())
            ->get(route('board'))
            ->assertOk()
            ->viewData('page')['props']['catalog']['problems']
    )->firstWhere('slug', 'url');

    foreach (['requirement' => 'requirements', 'scale' => 'scale', 'topic' => 'topics'] as $type => $key) {
        expect($brief[$key])->toBe(
            $problem->problemItems
                ->filter(fn ($item): bool => $item->problemItemType->slug === $type)
                ->sortBy('position')
                ->pluck('content')
                ->values()
                ->all()
        );
    }
});
