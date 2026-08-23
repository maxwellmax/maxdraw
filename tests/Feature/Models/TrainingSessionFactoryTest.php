<?php

use App\Models\Component;
use App\Models\EstimateMode;
use App\Models\LinkType;
use App\Models\SessionDuration;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * As regras do autosave (project-phases Phase 8.3) na forma em que a FormRequest
 * daquela fase vai aplicá-las: limites de payload, catálogo por slug e
 * integridade das arestas.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function autosaveRules(array $payload): array
{
    $nodeIds = collect($payload['nodes'])->pluck('id')->all();

    return [
        'nodes' => ['present', 'array', 'max:200'],
        'nodes.*.id' => ['required', 'string', 'distinct'],
        'nodes.*.type' => ['required', 'string', Rule::in(Component::query()->pluck('slug')->all())],
        'nodes.*.label' => ['nullable', 'string', 'max:60'],
        'nodes.*.x' => ['required', 'numeric'],
        'nodes.*.y' => ['required', 'numeric'],
        'edges' => ['present', 'array', 'max:400'],
        'edges.*.id' => ['required', 'string', 'distinct'],
        'edges.*.from' => ['required', 'string', Rule::in($nodeIds)],
        'edges.*.to' => ['required', 'string', Rule::in($nodeIds)],
        'edges.*.kind' => ['present', 'string', Rule::in(LinkType::query()->pluck('slug')->all())],
        'edges.*.label' => ['present', 'nullable', 'string', 'max:60'],
        'edges.*.dashed' => ['required', 'boolean'],
        'edges.*.bidir' => ['required', 'boolean'],
        'checks' => ['present', 'array'],
        'checks.*' => ['boolean'],
        'estimate' => ['required', 'array'],
        'estimate.mode' => ['required', 'string', Rule::in(EstimateMode::query()->pluck('slug')->all())],
        'estimate.dau' => ['required', 'numeric', 'min:0'],
        'estimate.act' => ['required', 'numeric', 'min:0'],
        'estimate.per_month' => ['required', 'numeric', 'min:0'],
        'estimate.ratio' => ['required', 'numeric', 'min:0'],
        'estimate.size' => ['required', 'numeric', 'min:0'],
        'estimate.peak' => ['required', 'numeric', 'min:0'],
        'estimate.ret' => ['required', 'numeric', 'min:0'],
        'notes' => ['nullable', 'string', 'max:5000'],
        'elapsed_seconds' => ['required', 'integer', 'min:0'],
        'duration_minutes' => ['required', 'integer', Rule::exists('session_durations', 'minutes')],
        'show_connection_order' => ['required', 'boolean'],
        'edges.*.order' => ['present', 'nullable', 'integer', 'min:1', 'max:400'],
    ];
}

/**
 * @return array<string, mixed>
 */
function autosavePayload(TrainingSession $session): array
{
    return [
        'nodes' => $session->nodes,
        'edges' => $session->edges,
        'checks' => $session->checks,
        'estimate' => $session->estimate,
        'notes' => $session->notes,
        'elapsed_seconds' => $session->elapsed_seconds,
        'duration_minutes' => $session->duration_minutes,
        'show_connection_order' => $session->show_connection_order,
    ];
}

test('training_session_factory_produces_valid_session', function (Closure $build) {
    EstimateMode::factory()->create(['slug' => 'user']);

    /** @var TrainingSession $session */
    $session = $build();

    $payload = autosavePayload($session);
    $validator = validator($payload, autosaveRules($payload));

    expect($validator->passes())->toBeTrue($validator->errors()->toJson());

    foreach ($session->edges as $edge) {
        expect($edge['from'])->not->toBe($edge['to']);
    }
})->with([
    'sessão nova' => [fn () => TrainingSession::factory()->create()],
    'com problema' => [fn () => TrainingSession::factory()->withProblem()->create()],
    'com diagrama' => [fn () => TrainingSession::factory()->withDiagram(6, 5)->create()],
    'no limite' => [fn () => TrainingSession::factory()->atLimit()->create()],
]);

test('training_session_factory_starts_the_session_empty', function () {
    $session = TrainingSession::factory()->create();

    expect($session->nodes)->toBe([])
        ->and($session->edges)->toBe([])
        ->and($session->checks)->toBe([])
        ->and($session->notes)->toBeNull()
        ->and($session->elapsed_seconds)->toBe(0)
        ->and($session->problem_id)->toBeNull()
        ->and($session->duration_minutes)->toBe(45)
        ->and($session->show_connection_order)->toBeTrue()
        ->and($session->last_opened_at->diffInSeconds(now()))->toBeLessThan(2)
        ->and($session->estimate)->toBe([
            'mode' => 'user',
            'dau' => 1000000,
            'act' => 10,
            'per_month' => 10000000,
            'ratio' => 100,
            'size' => 1,
            'peak' => 3,
            'ret' => 3,
        ]);
});

test('training_session_factory_resolves_lookups_by_slug', function () {
    SessionDuration::factory()->create(['minutes' => 30, 'position' => 1]);
    $default = SessionDuration::factory()->default()->create(['position' => 2]);

    $session = TrainingSession::factory()->create();

    expect($session->session_duration_id)->toBe($default->id);
});

test('training_session_factory_draws_every_edge_without_a_number', function () {
    $session = TrainingSession::factory()->withDiagram(6, 5)->create();

    expect($session->edges)->toHaveCount(5);

    foreach ($session->edges as $edge) {
        expect($edge)->toHaveKey('order')
            ->and($edge['order'])->toBeNull();
    }
});

test('training_session_factory_states_size_the_diagram', function () {
    $withDiagram = TrainingSession::factory()->withDiagram(4, 3)->create();
    $atLimit = TrainingSession::factory()->atLimit()->create();

    expect($withDiagram->nodes)->toHaveCount(4)
        ->and($withDiagram->edges)->toHaveCount(3)
        ->and($atLimit->nodes)->toHaveCount(200)
        ->and($atLimit->edges)->toHaveCount(400);
});

test('training_session_factory_refuses_edges_without_two_nodes', function () {
    expect(fn () => TrainingSession::factory()->withDiagram(1, 1))->toThrow(InvalidArgumentException::class);
});

test('user_factory_hashes_the_password', function () {
    $user = User::factory()->create();

    expect($user->password)->not->toBe('password')
        ->and(Hash::check('password', $user->password))->toBeTrue()
        ->and(auth()->attempt(['email' => $user->email, 'password' => 'password']))->toBeTrue();
});
