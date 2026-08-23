<?php

/**
 * A ordem explícita das conexões (Phase 12, reescrita pela feature
 * `ordem-explicita-de-conexoes`): o campo `order` da aresta, a sequência única
 * do diagrama e os controles que a editam. O comportamento é testado pelo
 * Vitest; o que a suíte PHP guarda aqui é o contrato — a forma do tipo, os
 * `data-testid` do toolbar e do painel, e a cobertura da fase.
 */
it('guarda a ordem explícita da conexão no campo order da aresta', function () {
    expect(frontendSource('canvas/types.ts'))
        ->toMatch('/export type Edge = \{\s*id: string;\s*from: string;\s*to: string;\s*kind: string \| null;\s*label: string;\s*dashed: boolean;\s*bidir: boolean;\s*order: number \| null;\s*\}/');

    expect(frontendSource('canvas/order.ts'))
        ->toContain('export function densify(')
        ->toContain('export function numberedCount(')
        ->toContain('export function setOrder(')
        ->toContain('export function clearOrder(')
        ->toContain('export function autoNumber(')
        ->toContain('export function clampOrderInput(');
});

it('começa a numeração automática pelo cliente sem entrada', function () {
    expect(frontendSource('canvas/order.ts'))
        ->toMatch('/const entries = nodes\.filter\(\(node\) => !hasInput\.has\(node\.id\)\);\s*return \[\s*\.\.\.entries\.filter\(\(node\) => isClientComponent\(index, node\.type\)\),\s*\.\.\.entries\.filter\(\(node\) => !isClientComponent\(index, node\.type\)\),\s*\.\.\.nodes,\s*\];/');

    expect(frontendSource('canvas/catalog.ts'))
        ->toContain("export const CLIENT_CATEGORY = 'client';");
});

it('desenha o número dentro do chip, num pill sólido na cor da origem', function () {
    $chip = frontendSource('components/prancheta/EdgeChip.vue');

    expect($chip)
        ->toContain('order?: number | null;')
        ->toContain('{ order: null },')
        ->toContain('data-testid="edge-chip-seq"')
        ->toContain('v-if="order !== null"')
        ->toContain('rounded-full')
        ->toContain('v-text="order"');

    // Pill sólido: o fundo é a cor da seta e o número sai na cor do papel.
    preg_match('/const pillStyle = computed\(\(\) => \(\{(.*?)\n\}\)\);/s', $chip, $pill);

    expect($pill[1])
        ->toContain("background: 'var(--ec)',")
        ->toContain("color: 'var(--paper)',")
        ->toContain('minWidth: `${seqPillWidth(props.order ?? 1)}px`,');

    // O rótulo continua sendo caixa de texto: a distinção não é só de cor.
    expect($chip)->toContain('data-testid="edge-chip-label"')
        ->and(substr_count($chip, 'bg-sd-accent-soft'))->toBe(1);

    $template = substr($chip, strpos($chip, '<template>'));

    $positions = array_map(
        fn (string $testId): int => strpos($template, 'data-testid="'.$testId.'"'),
        ['edge-chip', 'edge-chip-seq', 'edge-chip-badge', 'edge-chip-label'],
    );

    expect($positions)->toBe(array_values(array_filter($positions)))
        ->and($positions)->toBe(collect($positions)->sort()->values()->all());
});

it('desenha a partir do campo order da aresta, respeitando a bandeira', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('order: engine.showConnectionOrder ? edge.order : null,')
        ->toContain(':order="wire.order"');

    expect(frontendSource('canvas/engine.ts'))
        ->toMatch('/get showConnectionOrder\(\): boolean \{\s*return this\.state\.showConnectionOrder;\s*\}/')
        ->toMatch('/setShowConnectionOrder\(value: boolean\): boolean \{\s*if \(this\.state\.showConnectionOrder === value\) \{\s*return false;\s*\}\s*this\.state\.showConnectionOrder = value;\s*return true;\s*\}/');

    // A bandeira é estado de sessão, não conteúdo do diagrama.
    expect(frontendSource('canvas/types.ts'))
        ->toMatch('/export type SessionState = \{\s*nodes: Node\[\];\s*edges: Edge\[\];\s*showConnectionOrder: boolean;\s*\}/')
        ->toMatch('/export type DiagramSnapshot = \{\s*nodes: Node\[\];\s*edges: Edge\[\];\s*\}/');

    expect(frontendSource('canvas/undo.ts'))
        ->toContain('JSON.stringify({ nodes: diagram.nodes, edges: diagram.edges })');
});

it('põe o toggle de exibição e a numeração automática no toolbar do palco', function () {
    $zoombar = frontendSource('components/prancheta/ZoomBar.vue');

    expect($zoombar)
        ->toContain('data-testid="order-toggle"')
        ->toContain(':aria-pressed="showConnectionOrder"')
        ->toContain("@click=\"\$emit('toggle-order')\"")
        ->toContain('data-testid="order-auto"')
        ->toContain("@click=\"\$emit('auto-order')\"")
        ->not->toContain('data-testid="sequence-mode"')
        ->not->toContain('SequenceMenu');

    expect(frontendSource('components/prancheta/StageCanvas.vue'))
        ->toContain('showConnectionOrder?: boolean;')
        ->toContain(':show-connection-order="showConnectionOrder"')
        ->toContain("'toggle-order': [];")
        ->toContain("'auto-order': [];");
});

it('troca os botões de empurrar por um campo de ordem e um de remover', function () {
    $bar = frontendSource('components/prancheta/EdgeFloatBar.vue');

    expect($bar)
        ->toContain('data-testid="edge-order-input"')
        ->toContain('type="number"')
        ->toContain(':value="order ?? \'\'"')
        ->toContain('@change="commitOrder"')
        ->toContain('@keyup.enter="commitOrder"')
        ->toContain('@blur="commitOrder"')
        ->toContain('data-testid="edge-order-clear"')
        ->toContain("@click=\"emit('clear-order')\"")
        ->not->toContain('data-testid="edge-seq-back"')
        ->not->toContain('data-testid="edge-seq-position"')
        ->not->toContain('data-testid="edge-seq-forward"');

    // O clamp mora no motor; o componente só liga o campo nele.
    expect($bar)->toContain("import { clampOrderInput } from '@/canvas/order';")
        ->toMatch('/const wanted = clampOrderInput\(\s*field\.value,\s*props\.numberedCount,\s*props\.order !== null,\s*\);/');
});

it('liga na prancheta os quatro controles da ordem', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('engine.setShowConnectionOrder(!engine.showConnectionOrder)')
        ->toContain('@auto-order="engine.autoNumberOrder()"')
        ->toContain('engine.setEdgeOrder(edgeBar.edge.id, $event)')
        ->toContain('@clear-order="engine.clearEdgeOrder(edgeBar.edge.id)"')
        ->toContain(':order="edgeBar.order"')
        ->toContain(':numbered-count="edgeBar.numberedCount"');
});

it('empilha desfazer ao mudar a ordem, e não ao acender os números', function () {
    expect(frontendSource('canvas/engine.ts'))
        ->toMatch('/setEdgeOrder\(id: string, k: number\): boolean \{\s*return this\.mutateEdge\(/')
        ->toMatch('/clearEdgeOrder\(id: string\): boolean \{\s*return this\.mutateEdge\(/')
        ->toMatch('/autoNumberOrder\(\): boolean \{\s*return this\.mutateEdge\(/');
});

it('apaga do disco o módulo e o menu do modo de numeração', function (string $path) {
    expect(resource_path('js/'.$path))->not->toBeFile();
})->with([
    'motor do modo' => ['canvas/sequence.ts'],
    'testes do modo' => ['canvas/sequence.test.ts'],
    'menu do modo' => ['components/prancheta/SequenceMenu.vue'],
]);

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(canvasTestNames())->toContain($name);
})->with([
    'densify_rewrites_sparse_orders_to_1_to_N',
    'densify_breaks_ties_by_array_position',
    'densify_includes_orphan_edges',
    'null_orders_do_not_count_toward_N',
    'setOrder_pushes_later_edges_forward',
    'setOrder_moving_forward_lands_at_the_requested_slot',
    'setOrder_numbers_an_edge_that_was_outside_the_sequence',
    'setOrder_clamps_out_of_range_values_to_the_nearest_end',
    'setOrder_refuses_an_unknown_edge_or_a_value_that_is_not_a_number',
    'clearOrder_removes_from_the_sequence_and_densifies',
    'clearOrder_is_a_no_op_on_an_edge_already_outside_the_sequence',
    'clampOrderInput_caps_a_numbered_edge_at_N',
    'clampOrderInput_caps_an_unnumbered_edge_at_N_plus_1',
    'clampOrderInput_treats_an_empty_field_as_a_no_op',
    'clampOrderInput_pulls_zero_and_negatives_up_to_the_first_slot',
    'no_operation_sequence_produces_a_duplicate_or_a_hole',
    'new_edge_starts_outside_the_sequence',
    'removeEdge_densifies_the_remaining_orders',
    'removeNode_densifies_once_after_removing_its_edges',
    'restore_keeps_sparse_orders_without_densifying',
    'undo_restores_the_order_map_after_setEdgeOrder',
    'undo_restores_the_order_map_after_clearEdgeOrder',
    'numberedCount_reports_the_current_N',
    'autoNumber_numbers_every_live_edge_exactly_once',
    'autoNumber_starts_from_client_nodes_without_inputs',
    'autoNumber_walks_breadth_first_from_each_root',
    'autoNumber_terminates_on_cyclic_graphs',
    'autoNumber_gives_a_bidir_edge_a_single_order_and_keeps_its_source_as_root',
    'autoNumber_ranks_a_numbered_orphan_after_every_live_edge',
    'autoNumber_keeps_the_relative_order_of_numbered_orphans',
    'autoNumber_is_deterministic_over_50_consecutive_runs',
    'autoNumber_renumbers_the_payload_limit_under_16ms',
    'autoNumberOrder_stacks_a_single_undo_step',
    'undo_does_not_revert_show_connection_order',
    'diagram_snapshot_serializes_only_nodes_and_edges',
]);

it('cobre no Vitest o contrato do cliente e o boot densificado', function (string $name) {
    expect(pranchetaTestNames())->toContain($name);
})->with([
    'session_body_carries_the_order_of_every_edge',
    'sparse_order_boots_dense_and_already_saved',
    'v1_draft_is_discarded_on_boot',
    'v2_draft_is_still_rehydrated',
]);
