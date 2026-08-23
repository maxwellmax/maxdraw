<?php

use App\Models\LinkType;

/**
 * As ligações e os tipos (Phase 11): o traço, o selo, as bandeiras manuais e o
 * arrasto que cria a seta. O comportamento é testado pelo Vitest; o que a suíte
 * PHP guarda aqui é o contrato — o catálogo de tipos batendo com o do banco, a
 * cor saindo sempre da categoria da origem e a cobertura que a fase exige.
 */
it('lê os nove tipos do catálogo do servidor, sem tabela paralela no cliente', function () {
    seedCatalog();

    expect(frontendSource('canvas/links.ts'))
        ->toMatch('/export type LinkType = \{/')
        ->toContain('dash_array: string | null;')
        ->toContain('is_bidirectional_default: boolean;')
        ->not->toContain("'http'")
        ->not->toContain("'retry'");

    expect(frontendSource('canvas/engine.ts'))
        ->toContain('setLinkTypes(linkTypes: readonly LinkType[]): void {');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('props.catalog.link_types');

    expect(LinkType::query()->count())->toBe(9);
});

it('congela na fixture o traço e a mão dupla de cada tipo do seeder', function () {
    seedCatalog();

    preg_match_all(
        "/\[\s*'(\w+)',\s*'[^']*',\s*'([^']*)',\s*(null|'[^']*'),\s*(true|false),\s*'([^']*)',?\s*\]/",
        frontendSource('canvas/fixtures.ts'),
        $rows,
        PREG_SET_ORDER
    );

    $fixture = collect($rows)->mapWithKeys(fn (array $row): array => [$row[1] => [
        'badge_label' => $row[2],
        'dash_array' => $row[3] === 'null' ? null : trim($row[3], "'"),
        'is_bidirectional_default' => $row[4] === 'true',
        'gloss' => $row[5],
    ]]);

    expect($fixture)->toHaveCount(9);

    expect(LinkType::query()->get()
        ->mapWithKeys(fn (LinkType $type): array => [$type->slug => [
            'badge_label' => $type->badge_label,
            'dash_array' => $type->dash_array,
            'is_bidirectional_default' => $type->is_bidirectional_default,
            'gloss' => $type->gloss,
        ]])
        ->all()
    )->toBe($fixture->all());
});

it('tira a cor da seta da categoria da origem, nunca do tipo', function () {
    expect(frontendSource('canvas/edges.ts'))
        ->toContain("return colorOf(index, nodeById(nodes, edge.from)?.type ?? '');");

    expect(frontendSource('canvas/links.ts'))
        ->not->toContain('color_token')
        ->not->toContain('--c-');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('color: engine.edgeColor(edge),');
});

it('desenha o fantasma e destaca o alvo durante o arrasto de ligação', function () {
    expect(frontendSource('composables/useStageInteraction.ts'))
        ->toContain('engine.beginLink(nodeElement.dataset.nodeId, worldX, worldY);')
        ->toContain('nodeUnder(event.clientX, event.clientY),')
        ->toContain('const result = engine.endLink();')
        ->toContain('engine.cancelLink();');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('data-testid="link-ghost"')
        ->toContain(':link-target="engine.isLinkTarget(node.id)"');

    expect(frontendSource('components/prancheta/CanvasNode.vue'))
        ->toContain("'0 0 0 3px var(--accent-soft)'")
        ->toContain("'var(--accent)'");
});

it('avisa por toast quando a ligação de número 401 é recusada', function () {
    expect(frontendSource('composables/useStageInteraction.ts'))
        ->toContain('options.onRefused(result.reason);');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('onRefused: warnAbout,')
        ->toContain("if (reason === 'edgeLimitReached' || reason === 'nodeLimitReached') {");

    expect(frontendSource('prancheta/warnings.ts'))
        ->toContain('edgeLimitReached:');
});

it('monta a barra flutuante da seta com o menu dos nove tipos mais "Sem tipo"', function (string $testId) {
    expect(frontendSource('components/prancheta/EdgeFloatBar.vue').frontendSource('components/prancheta/LinkKindMenu.vue'))
        ->toContain('data-testid="'.$testId.'"');
})->with([
    'floatbar',
    'edge-kind',
    'edge-label',
    'edge-dashed',
    'edge-bidir',
    'edge-reverse',
    'edge-delete',
    'kind-menu',
    'kind-option',
    'kind-sample',
]);

it('mostra o padrão de traço real na amostra de cada tipo', function () {
    expect(frontendSource('components/prancheta/LinkKindMenu.vue'))
        ->toContain(':style="{ background: dashSample(type.dash_array) }"')
        ->toContain('Sem tipo');

    expect(frontendSource('canvas/links.ts'))
        ->toContain('`repeating-linear-gradient(90deg, currentColor 0 ${on}px, transparent ${on}px ${on + off}px)`');
});

it('prende a barra flutuante ao meio da seta, acompanhando pan e zoom', function () {
    expect(frontendSource('canvas/engine.ts'))
        ->toContain('return toScreen(this.view, x, y - FLOATBAR_LIFT);');

    expect(frontendSource('canvas/view.ts'))
        ->toContain('export function floatBarSpot(anchor: Point, stage: Size, bar: Size): Point {');

    expect(frontendSource('components/prancheta/EdgeFloatBar.vue'))
        ->toContain('floatBarSpot(props.anchor, props.stage, bar.value)');
});

it('põe o rótulo livre ao lado do selo, separado por um ponto médio', function () {
    $chip = frontendSource('components/prancheta/EdgeChip.vue');

    expect($chip)
        ->toContain('data-testid="edge-chip-badge"')
        ->toContain('data-testid="edge-chip-separator"')
        ->toContain('data-testid="edge-chip-label"')
        ->toContain('>·</span');
});

it('tira borda e fundo do chip da seta sem tipo e sem rótulo', function () {
    expect(frontendSource('canvas/edges.ts'))
        ->toContain("bare: badge === '' && edge.label === '',");

    expect(frontendSource('components/prancheta/EdgeChip.vue'))
        ->toContain("'border-none bg-transparent p-0',");
});

it('barra o excedente de 60 caracteres no rótulo da seta', function () {
    expect(frontendSource('components/prancheta/EdgeChip.vue'))
        ->toContain('@beforeinput="onBeforeInput"');

    expect(frontendSource('canvas/diagram.ts'))
        ->toContain('const next = clampLabel(label);');

    expect(frontendSource('canvas/labels.ts'))
        ->toContain('return raw.trim().slice(0, MAX_LABEL_LENGTH);');
});

it('empilha desfazer no caminho único de mutação da aresta', function () {
    expect(frontendSource('canvas/engine.ts'))
        ->toMatch('/private mutateEdge\(change: \(\) => boolean\): boolean \{\s*const previous = snapshot\(this\.state\);\s*const changed = change\(\);\s*if \(changed\) \{\s*this\.history\.push\(previous\);/');
});

it('faz tipo, rótulo e bandeiras passarem por esse caminho', function (string $method) {
    expect(frontendSource('canvas/engine.ts'))
        ->toMatch('/'.$method.'\([^)]*\): boolean \{\s*return this\.mutateEdge\(/');
})->with(['setEdgeKind', 'setEdgeLabel', 'toggleEdgeFlag', 'reverseEdge']);

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(canvasTestNames())->toContain($name);
})->with([
    'edgeOk_rejects_self_loops_and_dangling_edges',
    'dashOf_matches_each_of_the_nine_types',
    'manual_dashed_overrides_type_default',
    'edgeColor_follows_source_category',
    'choosing_ws_sets_bidirectional',
    'no_link_type_introduces_a_new_color',
    'edge_label_is_capped_at_60_characters',
    'reversing_edge_swaps_endpoints_and_recolors_badge',
    'bare_edge_renders_no_chip_background',
    'type_and_flag_changes_push_undo',
]);
