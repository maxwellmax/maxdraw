<?php

use App\Models\SequenceMode;
use App\Models\TrainingSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * A numeração das setas (Phase 12): os três modos derivados do desenho, o botão
 * que os troca e a reordenação das saídas. O comportamento é testado pelo
 * Vitest; o que a suíte PHP guarda aqui é o contrato — os modos vindo do
 * catálogo do servidor, o modo persistido na sessão e a cobertura da fase.
 */
it('guarda a ordem explícita da conexão no campo order da aresta', function () {
    expect(frontendSource('canvas/types.ts'))
        ->toMatch('/export type Edge = \{\s*id: string;\s*from: string;\s*to: string;\s*kind: string \| null;\s*label: string;\s*dashed: boolean;\s*bidir: boolean;\s*order: number \| null;\s*\}/');

    expect(frontendSource('canvas/order.ts'))
        ->toContain('export function densify(')
        ->toContain('export function numberedCount(')
        ->toContain('export function setOrder(')
        ->toContain('export function clearOrder(')
        ->toContain('export function autoNumber(');

    expect(frontendSource('canvas/sequence.ts'))
        ->toContain('export function outSeq(')
        ->toContain('export function flowSeq(')
        ->toContain('export function seqMap(');
});

it('só numera as saídas do bloco que tem mais de uma', function () {
    expect(frontendSource('canvas/sequence.ts'))
        ->toMatch('/const total = outputs\.get\(edge\.from\) \?\? 0;\s*if \(total < 2\) \{\s*continue;/');
});

it('começa a travessia do fluxo pelo cliente sem entrada', function () {
    expect(frontendSource('canvas/sequence.ts'))
        ->toMatch('/const entries = nodes\.filter\(\(node\) => !hasInput\.has\(node\.id\)\);\s*return \[\s*\.\.\.entries\.filter\(\(node\) => isClientComponent\(index, node\.type\)\),\s*\.\.\.entries\.filter\(\(node\) => !isClientComponent\(index, node\.type\)\),\s*\.\.\.nodes,\s*\];/');

    expect(frontendSource('canvas/catalog.ts'))
        ->toContain("export const CLIENT_CATEGORY = 'client';");
});

it('lê os três modos do catálogo do servidor, sem lista paralela no cliente', function () {
    seedCatalog();

    $names = SequenceMode::query()->active()->pluck('name', 'slug');

    expect($names->all())->toBe([
        'out' => 'Ordem de saída de cada bloco',
        'flow' => 'Sequência do fluxo inteiro',
        'off' => 'Sem números',
    ]);

    $client = frontendSource('canvas/sequence.ts');

    foreach ($names as $name) {
        expect($client)->not->toContain($name);
    }

    expect($client)->toContain("export const SEQUENCE_MENU: SequenceMode[] = ['off', 'out', 'flow'];");

    expect(frontendSource('pages/Board.vue'))
        ->toContain('props.catalog.sequence_modes')
        ->toContain(':sequence-modes="engine.sequenceModes"');
});

it('desenha o número dentro do chip, na cor da categoria da origem', function () {
    expect(frontendSource('components/prancheta/EdgeChip.vue'))
        ->toContain('data-testid="edge-chip-seq"')
        ->toContain('rounded-full')
        ->toContain("border: '1.2px solid var(--ec)',")
        ->toContain('v-text="seq.index"');

    $chip = frontendSource('components/prancheta/EdgeChip.vue');
    $template = substr($chip, strpos($chip, '<template>'));

    $positions = array_map(
        fn (string $testId): int => strpos($template, 'data-testid="'.$testId.'"'),
        ['edge-chip', 'edge-chip-seq', 'edge-chip-badge', 'edge-chip-label'],
    );

    expect($positions)->toBe(array_values(array_filter($positions)))
        ->and($positions)->toBe(collect($positions)->sort()->values()->all());

    expect(frontendSource('pages/Board.vue'))
        ->toContain('const numbers = engine.seqMap();')
        ->toContain('seq: numbers[edge.id] ?? null,')
        ->toContain(':seq="wire.seq"');
});

it('abre o menu dos três modos no botão da barra de zoom', function () {
    expect(frontendSource('components/prancheta/ZoomBar.vue'))
        ->toContain('data-testid="sequence-mode"')
        ->toContain('<SequenceMenu')
        ->toContain("const numbering = computed(() => props.sequenceMode !== 'off');")
        ->toContain("emit('pick-sequence', mode);")
        ->toContain('onClickOutside(seqButton, () => (menuOpen.value = false));');

    expect(frontendSource('components/prancheta/SequenceMenu.vue'))
        ->toContain('data-testid="sequence-menu"')
        ->toContain('Numeração das setas')
        ->toContain('data-testid="sequence-option"')
        ->toContain("mode === option.mode\n                    ? 'font-semibold text-sd-accent'");
});

it('mostra o controle de reordenação na barra flutuante da seta', function () {
    expect(frontendSource('components/prancheta/EdgeFloatBar.vue'))
        ->toContain('<template v-if="seq">')
        ->toContain('data-testid="edge-seq-back"')
        ->toContain('data-testid="edge-seq-position"')
        ->toContain('data-testid="edge-seq-forward"')
        ->toContain(':disabled="seq.index <= 1"')
        ->toContain(':disabled="seq.index >= seq.total"')
        ->toContain("emit('move-seq', -1)")
        ->toContain("emit('move-seq', 1)")
        ->toContain('{{ seq.index }}/{{ seq.total }}');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('seq: engine.outSeq()[edge.id] ?? null,')
        ->toContain('@move-seq="engine.moveSeq(edgeBar.edge.id, $event)"');
});

it('empilha desfazer ao reordenar, e não ao trocar de modo', function () {
    expect(frontendSource('canvas/engine.ts'))
        ->toMatch('/moveSeq\(id: string, direction: number\): boolean \{\s*return this\.mutateEdge\(/')
        ->toMatch('/setSequenceMode\(mode: SequenceMode\): boolean \{\s*const next = sequenceModeOf\(mode\);\s*if \(this\.state\.seqMode === next\) \{\s*return false;\s*\}\s*this\.state\.seqMode = next;\s*return true;\s*\}/');
});

it('grava o modo na sessão e o devolve ao reabrir a prancheta', function () {
    seedCatalog();

    $user = User::factory()->create();
    $session = TrainingSession::factory()->withDiagram(3, 2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson(route('sessions.update', $session), autosaveBody(['seq_mode' => 'flow']))
        ->assertOk();

    expect($session->refresh()->sequenceMode->slug)->toBe('flow');

    $this->actingAs($user)
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->where('session.seq_mode', 'flow')
            ->count('catalog.sequence_modes', 3)
            ->etc());
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(canvasTestNames())->toContain($name);
})->with([
    'outSeq_skips_blocks_with_a_single_output',
    'outSeq_numbers_by_array_order',
    'outSeq_ignores_dangling_edges',
    'outSeq_reports_correct_total_per_block',
    'flowSeq_starts_from_client_nodes_without_inputs',
    'flowSeq_follows_output_order_depth_first',
    'flowSeq_terminates_on_cyclic_graphs',
    'flowSeq_covers_orphan_components_at_the_end',
    'flowSeq_numbers_every_live_edge_exactly_once',
    'seqMap_returns_empty_when_off',
    'invalid_mode_normalizes_to_out',
    'moveSeq_swaps_with_sibling_output_only',
    'moveSeq_refuses_at_both_ends',
    'reordering_changes_flow_traversal_order',
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
]);
