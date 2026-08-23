<?php

use App\Models\ChecklistItem;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * A FormRequest do autosave (Phase 8.3): catálogo por slug, integridade das
 * arestas e os limites do payload — todos inclusivos, todos com erro no campo.
 */
beforeEach(function () {
    seedCatalog();

    TrainingSession::factory()->create(['user_id' => User::factory()->create()->id]);
});

/**
 * A única sessão do teste — cada exemplo trabalha sobre a que o `beforeEach` criou.
 */
function autosaveTarget(): TrainingSession
{
    return TrainingSession::query()->sole();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function autosave(array $overrides = []): TestResponse
{
    $session = autosaveTarget();

    return test()->actingAs($session->user)
        ->putJson(route('sessions.update', $session), autosaveBody($overrides));
}

/**
 * Um diagrama de `$count` nós, ids `n0..n{count-1}`, do tamanho que o teste pedir.
 *
 * @return array<int, array<string, mixed>>
 */
function nodesOfSize(int $count): array
{
    return collect(range(0, $count - 1))
        ->map(fn (int $index): array => [
            'id' => 'n'.$index,
            'type' => 'api',
            'label' => 'Bloco '.$index,
            'x' => 10 * $index,
            'y' => 20,
        ])
        ->all();
}

/**
 * @return array<int, array<string, mixed>>
 */
function edgesOfSize(int $count, int $nodeCount): array
{
    return collect(range(0, $count - 1))
        ->map(fn (int $index): array => [
            'id' => 'e'.$index,
            'from' => 'n'.($index % $nodeCount),
            'to' => 'n'.(($index + 1) % $nodeCount),
            'kind' => 'query',
            'label' => '',
            'dashed' => false,
            'bidir' => false,
            'order' => null,
        ])
        ->all();
}

/**
 * Uma aresta legítima entre `n1` e `n2`, com o número que o teste quiser.
 *
 * @return array<string, mixed>
 */
function edgeNumbered(string $id, mixed $order): array
{
    return ['id' => $id, 'from' => 'n1', 'to' => 'n2', 'kind' => 'query', 'label' => '', 'dashed' => false, 'bidir' => false, 'order' => $order];
}

test('rejects_unknown_component_type', function () {
    autosave(['nodes' => [['id' => 'n1', 'type' => 'telefone-sem-fio', 'label' => 'X', 'x' => 1, 'y' => 2]], 'edges' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nodes.0.type');
});

test('rejects_unknown_link_kind', function () {
    autosave(['edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => 'telepatia', 'label' => '', 'dashed' => false, 'bidir' => false]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('edges.0.kind');
});

test('accepts_an_edge_without_a_kind', function () {
    autosave(['edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => '', 'label' => '', 'dashed' => false, 'bidir' => false]]])
        ->assertOk();

    expect(autosaveTarget()->edges[0]['kind'])->toBe('');
});

test('rejects_edge_pointing_to_absent_node', function (string $field) {
    $edge = ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => 'query', 'label' => '', 'dashed' => false, 'bidir' => false];
    $edge[$field] = 'n99';

    autosave(['edges' => [$edge]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('edges.0.'.$field);
})->with(['from', 'to']);

test('rejects_self_referencing_edge', function () {
    autosave(['edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n1', 'kind' => 'query', 'label' => '', 'dashed' => false, 'bidir' => false]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('edges.0.to');
});

test('rejects_more_than_200_nodes', function () {
    autosave(['nodes' => nodesOfSize(201), 'edges' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nodes');
});

test('rejects_more_than_400_edges', function () {
    autosave(['nodes' => nodesOfSize(200), 'edges' => edgesOfSize(401, 200)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('edges');
});

test('rejects_label_longer_than_60', function (string $field) {
    $payload = match ($field) {
        'nodes.0.label' => ['nodes' => [['id' => 'n1', 'type' => 'api', 'label' => str_repeat('a', 61), 'x' => 1, 'y' => 2]], 'edges' => []],
        default => ['edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => 'query', 'label' => str_repeat('a', 61), 'dashed' => false, 'bidir' => false]]],
    };

    autosave($payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with(['nodes.0.label', 'edges.0.label']);

test('rejects_notes_longer_than_5000', function () {
    autosave(['notes' => str_repeat('n', 5001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notes');
});

test('accepts_payload_exactly_at_every_limit', function () {
    $nodes = nodesOfSize(200);
    $nodes[0]['label'] = str_repeat('a', 60);

    $edges = edgesOfSize(400, 200);
    $edges[0]['label'] = str_repeat('b', 60);

    autosave([
        'nodes' => $nodes,
        'edges' => $edges,
        'notes' => str_repeat('n', 5000),
    ])->assertOk();

    $saved = autosaveTarget();

    expect($saved->nodes)->toHaveCount(200)
        ->and($saved->edges)->toHaveCount(400)
        ->and($saved->nodes[0]['label'])->toHaveLength(60)
        ->and($saved->edges[0]['label'])->toHaveLength(60)
        ->and($saved->notes)->toHaveLength(5000);
});

test('normalizes_negative_estimate_values_to_zero', function () {
    autosave(['estimate' => [
        'mode' => 'user',
        'dau' => -1000,
        'act' => -1,
        'per_month' => -50,
        'ratio' => -100,
        'size' => -1.5,
        'peak' => -3,
        'ret' => -3,
    ]])->assertOk();

    expect(autosaveTarget()->estimate)->toBe([
        'mode' => 'user',
        'dau' => 0,
        'act' => 0,
        'per_month' => 0,
        'ratio' => 0,
        'size' => 0,
        'peak' => 0,
        'ret' => 0,
    ]);
});

test('rejects_an_estimate_mode_outside_the_catalog', function () {
    autosave(['estimate' => ['mode' => 'por-chute', 'dau' => 1, 'act' => 1, 'per_month' => 1, 'ratio' => 1, 'size' => 1, 'peak' => 1, 'ret' => 1]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('estimate.mode');
});

test('rejects_a_negative_or_fractional_elapsed_time', function (mixed $elapsed) {
    autosave(['elapsed_seconds' => $elapsed])
        ->assertStatus(422)
        ->assertJsonValidationErrors('elapsed_seconds');
})->with([
    'negativo' => [-1],
    'texto' => ['muito tempo'],
    'fracionário' => [12.5],
]);

test('rejects_a_node_without_numeric_coordinates', function (string $axis) {
    autosave([
        'nodes' => [['id' => 'n1', 'type' => 'api', 'label' => 'X', 'x' => 10, 'y' => 20, $axis => 'meio da tela']],
        'edges' => [],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nodes.0.'.$axis);
})->with(['x', 'y']);

test('rejects_a_non_boolean_edge_flag', function (string $flag) {
    $edge = ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => 'query', 'label' => '', 'dashed' => false, 'bidir' => false];
    $edge[$flag] = 'talvez';

    autosave(['edges' => [$edge]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('edges.0.'.$flag);
})->with(['dashed', 'bidir']);

test('validation_errors_are_keyed_by_field', function () {
    $response = autosave([
        'nodes' => [['id' => 'n1', 'type' => 'nao-existe', 'label' => str_repeat('a', 61), 'x' => 'aqui', 'y' => 2]],
        'edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n404', 'kind' => 'telepatia', 'label' => str_repeat('b', 61), 'dashed' => 'talvez', 'bidir' => false]],
        'notes' => str_repeat('n', 5001),
        'checks' => ['3:1' => true],
    ])->assertStatus(422);

    expect(array_keys($response->json('errors')))->toEqualCanonicalizing([
        'nodes.0.type',
        'nodes.0.label',
        'nodes.0.x',
        'edges.0.to',
        'edges.0.kind',
        'edges.0.label',
        'edges.0.dashed',
        'notes',
        'checks',
    ]);

    $errors = $response->json('errors');

    expect($errors['notes'][0])->toContain('5.000')
        ->and($errors['nodes.0.label'][0])->toContain('60')
        ->and($errors['nodes.0.type'][0])->toContain('catálogo')
        ->and($errors['checks'][0])->toContain('3:1');
});

test('the_checklist_accepts_the_ids_of_the_catalog', function () {
    $items = ChecklistItem::query()->take(3)->pluck('id');

    autosave(['checks' => $items->mapWithKeys(fn (int $id): array => [$id => true])->all()])
        ->assertOk();

    expect(array_keys(autosaveTarget()->checks))->toEqualCanonicalizing($items->all());
});

test('rejects_an_order_outside_the_allowed_range', function (mixed $order, string $fragment) {
    $response = autosave(['edges' => [edgeNumbered('e1', $order)]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('edges.0.order');

    expect($response->json('errors')['edges.0.order'][0])->toContain($fragment);
})->with([
    'zero' => [0, 'começa em 1'],
    'negativo' => [-3, 'começa em 1'],
    'acima do teto' => [401, '400'],
    'fracionário' => [2.5, 'número inteiro'],
    'texto' => ['primeiro', 'número inteiro'],
    'lista' => [[1], 'número inteiro'],
]);

test('rejects_two_edges_with_the_same_order', function () {
    $response = autosave(['edges' => [
        edgeNumbered('e1', 3),
        edgeNumbered('e2', 7),
        edgeNumbered('e3', 3),
    ]])->assertStatus(422);

    expect(array_keys($response->json('errors')))->toEqualCanonicalizing(['edges.0.order', 'edges.2.order'])
        ->and($response->json('errors')['edges.0.order'][0])->toBe('Duas ligações não podem ter o mesmo número de ordem.')
        ->and($response->json('errors')['edges.2.order'][0])->toBe('Duas ligações não podem ter o mesmo número de ordem.');
});

test('accepts_an_order_inside_the_allowed_range', function (mixed $order) {
    autosave(['edges' => [edgeNumbered('e1', $order)]])->assertOk();

    expect(autosaveTarget()->edges[0]['order'])->toBe($order);
})->with([
    'sem número' => [null],
    'primeiro' => [1],
    'no meio' => [200],
    'no teto' => [400],
]);

/**
 * Densidade é responsabilidade do cliente: o servidor grava o que chega.
 */
test('accepts_a_sparse_order_and_writes_it_as_it_arrives', function () {
    autosave(['edges' => [
        edgeNumbered('e1', 1),
        edgeNumbered('e2', 3),
        edgeNumbered('e3', 7),
    ]])->assertOk();

    expect(array_column(autosaveTarget()->edges, 'order'))->toBe([1, 3, 7]);
});

test('accepts_an_edge_that_never_mentions_the_order', function () {
    autosave(['edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'kind' => 'query', 'label' => '', 'dashed' => false, 'bidir' => false]]])
        ->assertOk();

    expect(autosaveTarget()->edges[0])->not->toHaveKey('order');
});

test('rejects_a_non_boolean_show_connection_order', function () {
    autosave(['show_connection_order' => 'talvez'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('show_connection_order');
});

/**
 * O contrato revogado não volta pela porta dos fundos: uma chave que a
 * FormRequest não conhece é descartada, nunca gravada.
 */
test('an_unknown_root_key_is_dropped_instead_of_persisted', function () {
    autosave(['modo_de_numeracao' => 'telepatia'])->assertOk();

    expect(array_keys(autosaveTarget()->getAttributes()))->not->toContain('modo_de_numeracao');
});
