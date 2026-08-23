<?php

/**
 * O motor do canvas (Phase 9.1): o pacote TypeScript que concentra geometria,
 * colisão, snap e desfazer. Ele é testado de verdade pelo Vitest; o que a suíte
 * PHP guarda aqui é o contrato — pureza do pacote, os números do protótipo e a
 * cobertura que a fase exige.
 */
it('mantém o motor livre de framework e de DOM', function (string $file) {
    expect(frontendSource('canvas/'.$file))
        ->not->toContain("from 'vue'")
        ->not->toContain("from '@inertiajs")
        ->not->toContain('document.')
        ->not->toContain('window.');
})->with(fn () => canvasFiles());

it('exporta os tipos do diagrama', function (string $type) {
    expect(frontendSource('canvas/types.ts'))->toMatch('/export type '.$type.'\s*=/');
})->with(['Node', 'Edge', 'SessionState', 'View']);

it('congela os números do protótipo', function (string $file, string $declaration) {
    expect(frontendSource('canvas/'.$file))->toContain($declaration);
})->with([
    'largura do bloco' => ['geometry.ts', 'export const NODE_WIDTH = 132;'],
    'altura padrão do bloco' => ['nodes.ts', 'export const NODE_HEIGHT = 86;'],
    'recorte na origem' => ['geometry.ts', 'export const SOURCE_PAD = 7;'],
    'recorte no destino' => ['geometry.ts', 'export const TARGET_PAD = 10;'],
    'proporção do controle' => ['geometry.ts', 'export const CONTROL_RATIO = 0.45;'],
    'controle mínimo' => ['geometry.ts', 'export const CONTROL_MIN = 26;'],
    'controle máximo' => ['geometry.ts', 'export const CONTROL_MAX = 110;'],
    'comprimento da ponta' => ['geometry.ts', 'export const HEAD_LENGTH = 9.5;'],
    'largura da ponta' => ['geometry.ts', 'export const HEAD_WIDTH = 3.9;'],
    'passo da grade' => ['layout.ts', 'export const GRID_STEP = 4;'],
    'colisão vertical' => ['layout.ts', 'export const COLLISION_Y = 80;'],
    'limite de nós' => ['limits.ts', 'export const MAX_NODES = 200;'],
    'limite de arestas' => ['limits.ts', 'export const MAX_EDGES = 400;'],
    'limite do rótulo' => ['limits.ts', 'export const MAX_LABEL_LENGTH = 60;'],
    'limite da pilha de desfazer' => ['undo.ts', 'export const UNDO_LIMIT = 60;'],
    'passo da grade do palco' => ['view.ts', 'export const GRID_SIZE = 26;'],
]);

it('mede a colisão horizontal em 122 px, dez a menos que a largura do bloco', function () {
    expect(frontendSource('canvas/layout.ts'))
        ->toContain('export const COLLISION_X = NODE_WIDTH - 10;');
});

it('varre os deslocamentos do protótipo na ordem', function () {
    preg_match(
        '/FREE_SPOT_OFFSETS: readonly Point\[\] = \[(.*?)\];/s',
        frontendSource('canvas/layout.ts'),
        $matches
    );

    preg_match_all('/\[(-?\d+), (-?\d+)\]/', $matches[1], $offsets, PREG_SET_ORDER);

    expect(array_map(fn (array $offset): array => [(int) $offset[1], (int) $offset[2]], $offsets))
        ->toBe([
            [0, 0], [0, 110], [160, 0], [160, 110], [-160, 0], [0, -110],
            [-160, 110], [160, -110], [320, 0], [0, 220], [-320, 0], [320, 110],
        ]);
});

it('usa no cliente os mesmos limites que o servidor valida', function (string $constant, string $exported) {
    $request = file_get_contents(app_path('Http/Requests/TrainingSessionUpdateRequest.php'));

    preg_match('/private const '.$constant.' = (\d+);/', $request, $server);
    preg_match('/export const '.$exported.' = (\d+);/', frontendSource('canvas/limits.ts'), $client);

    expect($client[1])->toBe($server[1]);
})->with([
    'nós' => ['MAX_NODES', 'MAX_NODES'],
    'arestas' => ['MAX_EDGES', 'MAX_EDGES'],
    'rótulo' => ['MAX_LABEL', 'MAX_LABEL_LENGTH'],
]);

it('guarda na pilha de desfazer só nós e arestas', function () {
    expect(frontendSource('canvas/undo.ts'))
        ->toContain('JSON.stringify({ nodes: diagram.nodes, edges: diagram.edges })')
        ->and(frontendSource('canvas/types.ts'))
        ->toMatch('/export type DiagramSnapshot = \{\s*nodes: Node\[\];\s*edges: Edge\[\];\s*\}/');
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(canvasTestNames())->toContain($name);
})->with([
    'clipPt_lands_on_rectangle_border',
    'clipPt_returns_center_when_target_equals_center',
    'bez_uses_horizontal_control_when_dx_dominates',
    'bez_uses_vertical_control_when_dy_dominates',
    'bez_clamps_control_between_26_and_110',
    'ptAt_at_zero_and_one_matches_endpoints',
    'head_points_along_the_tangent',
    'freeSpot_avoids_overlap_with_existing_nodes',
    'freeSpot_returns_first_free_offset_in_order',
    'snap_rounds_to_multiples_of_four',
    'addNode_refuses_beyond_200_nodes',
    'addEdge_refuses_beyond_400_edges',
    'new_node_label_defaults_to_component_short_name',
    'undo_stack_caps_at_60_states',
    'undo_restores_previous_nodes_and_edges',
    'redo_stack_is_cleared_by_new_action',
    'toggling_connection_order_does_not_push_undo',
    'pan_and_zoom_do_not_push_undo',
    'label_is_capped_at_60_characters',
    'empty_label_falls_back_to_short_name',
    'move_snaps_to_4px_grid',
    'deleting_a_node_removes_its_incident_edges',
    'delete_clears_selection',
]);
