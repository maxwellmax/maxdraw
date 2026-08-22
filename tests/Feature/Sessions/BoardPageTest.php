<?php

use App\Models\Component;
use App\Models\LinkType;
use App\Models\Problem;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionCreator;
use Inertia\Testing\AssertableInertia;

/**
 * O carregamento inicial da prancheta (Phase 8.1): sessão corrente e catálogo
 * completo na mesma resposta Inertia, com o cronômetro sempre pausado.
 */
beforeEach(function () {
    seedCatalog();
});

test('board_returns_current_session_and_catalog', function () {
    $user = User::factory()->create();
    $problem = Problem::query()->where('slug', 'url')->sole();

    $session = TrainingSession::factory()
        ->withProblem($problem)
        ->withDiagram(3, 2)
        ->create([
            'user_id' => $user->id,
            'notes' => 'anotações do treino',
            'elapsed_seconds' => 742,
            'checks' => ['1' => true],
        ]);

    $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->where('session.id', $session->id)
            ->where('session.problem_id', $problem->id)
            ->where('session.notes', 'anotações do treino')
            ->where('session.elapsed_seconds', 742)
            ->where('session.duration_minutes', 45)
            ->where('session.seq_mode', 'out')
            ->where('session.checks', ['1' => true])
            ->count('session.nodes', 3)
            ->count('session.edges', 2)
            ->where('session.estimate.mode', 'user')
            ->has('catalog.problems')
            ->has('catalog.component_categories')
            ->has('catalog.link_types')
            ->has('catalog.phases')
            ->has('catalog.sequence_modes')
            ->has('catalog.session_durations')
            ->has('catalog.estimate_modes')
            ->etc());
});

test('user_without_session_gets_a_fresh_empty_one', function () {
    $user = User::factory()->create();

    expect(TrainingSession::query()->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('session.problem_id', null)
            ->where('session.duration_minutes', 45)
            ->where('session.seq_mode', 'out')
            ->where('session.elapsed_seconds', 0)
            ->where('session.notes', null)
            ->where('session.nodes', [])
            ->where('session.edges', [])
            ->where('session.checks', [])
            ->where('session.estimate', SessionCreator::DEFAULT_ESTIMATE)
            ->etc());

    $created = TrainingSession::query()->sole();

    expect($created->user_id)->toBe($user->id)
        ->and($created->sessionDuration->minutes)->toBe(45)
        ->and($created->sequenceMode->slug)->toBe('out');
});

test('current_session_is_the_most_recently_opened', function () {
    $user = User::factory()->create();

    TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subDays(3)]);
    $current = TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subMinute()]);
    TrainingSession::factory()->create(['user_id' => $user->id, 'last_opened_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('session.id', $current->id)->etc());

    expect(TrainingSession::query()->count())->toBe(3);
});

test('catalog_payload_contains_expected_counts', function () {
    $catalog = $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->viewData('page')['props']['catalog'];

    expect($catalog['problems'])->toHaveCount(14)
        ->and(collect($catalog['component_categories'])->flatMap(fn (array $category): array => $category['components']))->toHaveCount(28)
        ->and($catalog['component_categories'])->toHaveCount(6)
        ->and($catalog['link_types'])->toHaveCount(9)
        ->and($catalog['phases'])->toHaveCount(5)
        ->and(collect($catalog['phases'])->flatMap(fn (array $phase): array => $phase['checklist_items']))->toHaveCount(25)
        ->and($catalog['sequence_modes'])->toHaveCount(3)
        ->and($catalog['session_durations'])->toHaveCount(3)
        ->and($catalog['estimate_modes'])->toHaveCount(2);
});

test('the_catalog_carries_the_three_lists_of_every_problem', function () {
    $catalog = $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->viewData('page')['props']['catalog'];

    $shortener = collect($catalog['problems'])->firstWhere('slug', 'url');

    expect($shortener['requirements'])->not->toBeEmpty()
        ->and($shortener['scale'])->not->toBeEmpty()
        ->and($shortener['topics'])->not->toBeEmpty()
        ->and($shortener['level'])->toBe('base')
        ->and($shortener['context'])->not->toBeEmpty();
});

test('the_clock_arrives_paused_at_the_stored_elapsed_seconds', function () {
    $user = User::factory()->create();
    TrainingSession::factory()->create(['user_id' => $user->id, 'elapsed_seconds' => 1234]);

    $session = $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->viewData('page')['props']['session'];

    expect($session['elapsed_seconds'])->toBe(1234)
        ->and(array_keys($session))->not->toContain('running', 'is_running', 'started_at');
});

test('the_catalog_only_serves_active_rows_except_what_the_diagram_uses', function () {
    $user = User::factory()->create();

    $session = TrainingSession::factory()->create([
        'user_id' => $user->id,
        'nodes' => [['id' => 'n1', 'type' => 'cdn', 'label' => 'CDN', 'x' => 10, 'y' => 20]],
        'edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n1', 'kind' => 'batch', 'label' => '', 'dashed' => false, 'bidir' => false]],
    ]);

    Component::query()->whereIn('slug', ['cdn', 'waf'])->update(['is_active' => false]);
    LinkType::query()->whereIn('slug', ['batch', 'retry'])->update(['is_active' => false]);

    $catalog = $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->viewData('page')['props']['catalog'];

    $components = collect($catalog['component_categories'])->flatMap(fn (array $category): array => $category['components'])->pluck('slug');
    $kinds = collect($catalog['link_types'])->pluck('slug');

    expect($components)->toContain('cdn')
        ->and($components)->not->toContain('waf')
        ->and($kinds)->toContain('batch')
        ->and($kinds)->not->toContain('retry')
        ->and($session->fresh()->nodes)->toHaveCount(1);
});

test('the_board_never_serves_the_session_of_another_user', function () {
    $user = User::factory()->create();
    TrainingSession::factory()->create(['user_id' => $user->id]);

    $foreign = TrainingSession::factory()->create([
        'user_id' => User::factory()->create()->id,
        'notes' => 'segredo do outro usuário',
        'last_opened_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($user)->get(route('board'))->assertOk();

    $response->assertDontSee($foreign->notes);

    expect($response->viewData('page')['props']['session']['id'])->not->toBe($foreign->id);
});

test('the_board_payload_never_leaks_the_owner_column', function () {
    $user = User::factory()->create();
    TrainingSession::factory()->create(['user_id' => $user->id]);

    $session = $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->viewData('page')['props']['session'];

    expect($session)->not->toHaveKey('user_id')
        ->and($session)->not->toHaveKey('session_duration_id')
        ->and($session)->not->toHaveKey('sequence_mode_id');
});
