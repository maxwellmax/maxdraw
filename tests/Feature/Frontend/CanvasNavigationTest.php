<?php

/**
 * Navegação, exclusão e estado vazio do palco (Phase 9.3): pan, zoom ancorado,
 * enquadrar tudo, grade, `Del` e os atalhos de teclado.
 */
it('faz pan arrastando no vazio', function () {
    $interaction = frontendSource('composables/useStageInteraction.ts');

    expect($interaction)
        ->toContain('engine.select(null);')
        ->toContain("kind: 'pan',")
        ->toContain('x: gesture.viewX + (event.clientX - gesture.x),')
        ->toContain('y: gesture.viewY + (event.clientY - gesture.y),');
});

it('dá zoom com a roda, ancorado na posição do cursor', function () {
    expect(frontendSource('composables/useStageInteraction.ts'))
        ->toContain('engine.wheel(event.deltaY, x, y);')
        ->toContain('{ passive: false }');

    expect(frontendSource('canvas/view.ts'))
        ->toContain('x: ax - (ax - view.x) * ratio,')
        ->toContain('y: ay - (ay - view.y) * ratio,')
        ->toContain('Math.exp(-deltaY * WHEEL_ZOOM_RATIO)');
});

it('não faz pan nem zoom sobre a legenda e a barra de zoom', function () {
    expect(frontendSource('composables/useStageInteraction.ts'))
        ->toContain('const OVERLAYS = \'[data-testid="legend"],[data-testid="zoombar"]\';')
        ->toContain('target.closest(OVERLAYS)');
});

it('mostra a escala em porcentagem com mais, menos e enquadrar', function () {
    $zoombar = frontendSource('components/prancheta/ZoomBar.vue');

    expect($zoombar)
        ->toContain('`${Math.round(props.scale * 100)}%`')
        ->toContain('data-testid="zoom-in"')
        ->toContain('data-testid="zoom-out"')
        ->toContain('data-testid="zoom-fit"');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('@zoom-in="engine.zoomBy(1.2)"')
        ->toContain('@zoom-out="engine.zoomBy(1 / 1.2)"')
        ->toContain('@fit="engine.fit()"');
});

it('reserva a largura da legenda ao enquadrar tudo', function () {
    expect(frontendSource('canvas/view.ts'))
        ->toContain('export const LEGEND_WIDTH = 238;')
        ->toContain('? options.legendWidth + LEGEND_GUTTER')
        ->toContain('const usableWidth = Math.max(size.width - reserved, MIN_FIT_WIDTH);');

    // Legenda vazia ou recolhida não cobre o palco, então não desconta nada (US-5.2).
    expect(frontendSource('pages/Board.vue'))
        ->toContain('engine.setLegendWidth(empty || !open ? 0 : LEGEND_WIDTH)');
});

it('faz a grade acompanhar pan e zoom', function () {
    expect(frontendSource('components/prancheta/StageCanvas.vue'))
        ->toContain('backgroundSize: `${GRID_SIZE * props.scale}px ${GRID_SIZE * props.scale}px`,')
        ->toContain('backgroundPosition: `${props.offsetX}px ${props.offsetY}px`,');

    expect(frontendSource('pages/Board.vue'))
        ->toContain(':scale="engine.view.k"')
        ->toContain(':offset-x="engine.view.x"')
        ->toContain(':offset-y="engine.view.y"');
});

it('apaga o item selecionado com Del e limpa a seleção', function () {
    expect(frontendSource('composables/useStageInteraction.ts'))
        ->toMatch("/if \(event\.key === 'Delete' \|\| event\.key === 'Backspace'\)/")
        ->toContain('engine.deleteSelection();');

    expect(frontendSource('canvas/engine.ts'))
        ->toContain('this.selection = null;');

    expect(frontendSource('canvas/diagram.ts'))
        ->toContain('(edge) => edge.from !== id && edge.to !== id,');
});

it('desfaz com Ctrl+Z e refaz com Ctrl+Shift+Z', function () {
    expect(frontendSource('composables/useStageInteraction.ts'))
        ->toMatch("/\(event\.metaKey \|\| event\.ctrlKey\) &&\s*event\.key\.toLowerCase\(\) === 'z'/")
        ->toMatch('/if \(event\.shiftKey\) \{\s*engine\.redo\(\);\s*\} else \{\s*engine\.undo\(\);/');
});

it('cala os atalhos enquanto o foco está num campo ou num rótulo em edição', function () {
    $interaction = frontendSource('composables/useStageInteraction.ts');

    expect($interaction)
        ->toContain('if (isTyping(event.target)) {')
        ->toContain("target.tagName === 'INPUT'")
        ->toContain("target.tagName === 'TEXTAREA'")
        ->toContain('target.isContentEditable');
});

it('explica o palco vazio e some com o primeiro bloco', function () {
    $stage = frontendSource('components/prancheta/StageCanvas.vue');

    expect($stage)
        ->toContain('v-if="empty"')
        ->toContain('data-testid="empty-state"')
        ->toContain('Canvas vazio')
        ->toContain('Escolha um componente na barra da esquerda')
        ->toContain('>Del<')
        ->toContain('>duplo clique<');

    expect(frontendSource('pages/Board.vue'))->toContain(':empty="engine.isEmpty"');

    expect(frontendSource('canvas/engine.ts'))
        ->toContain('return this.state.nodes.length === 0;');
});

it('deixa desenhar sem problema escolhido', function () {
    $board = frontendSource('pages/Board.vue');

    expect($board)
        ->toContain("const openSheet = ref<'problem' | 'sessions' | null>(null);")
        ->not->toContain('problem_id');

    expect($board)->toMatch('/function exportSvg\(\): void \{\s*if \(engine\.isEmpty\) \{/');
});
