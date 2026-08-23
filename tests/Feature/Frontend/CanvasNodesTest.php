<?php

/**
 * Os blocos no palco (Phase 9.2): o que cada bloco mostra, como ele reage ao
 * mouse e as regras do rótulo.
 */
it('mostra ícone, rótulo e tipo em cada bloco', function (string $testId) {
    expect(frontendSource('components/prancheta/CanvasNode.vue'))
        ->toContain('data-testid="'.$testId.'"');
})->with(['node', 'node-icon', 'node-label', 'node-type', 'node-handle']);

it('usa a cor da categoria no ícone e no nome do tipo', function () {
    $node = frontendSource('components/prancheta/CanvasNode.vue');

    expect($node)
        ->toContain("'--nc': props.color")
        ->toContain("color: 'var(--nc)'");
});

it('escreve o rótulo em Archivo semibold e o tipo em IBM Plex Mono maiúsculo', function () {
    $node = frontendSource('components/prancheta/CanvasNode.vue');

    expect($node)
        ->toContain('font-semibold')
        ->toContain('font-sd-ui')
        ->toContain('font-mono')
        ->toContain('uppercase');

    expect(pranchetaCss())
        ->toContain('--font-sd-ui: var(--ui);')
        ->toContain('--font-sd-mono: var(--mono);');
});

it('mostra as quatro bolinhas no hover e com o bloco selecionado', function () {
    $node = frontendSource('components/prancheta/CanvasNode.vue');

    expect($node)
        ->toContain("v-for=\"handle in ['t', 'r', 'b', 'l']\"")
        ->toContain('opacity-0')
        ->toContain('group-hover:opacity-100')
        ->toContain("'opacity-100': selected");
});

it('destaca o bloco selecionado diferente do hover', function () {
    $node = frontendSource('components/prancheta/CanvasNode.vue');

    expect($node)
        ->toContain('hover:shadow-sd-2')
        ->toContain("'0 0 0 2px color-mix(in srgb, var(--nc) 34%, transparent), var(--shadow-2)'")
        ->toContain(':data-selected="selected"');
});

it('mede a altura real do bloco e devolve ao motor para a aresta se ajustar', function () {
    expect(frontendSource('components/prancheta/CanvasNode.vue'))
        ->toContain('useResizeObserver(root,')
        ->toContain("emit('measure', (entry.target as HTMLElement).offsetHeight)");

    expect(frontendSource('pages/Board.vue'))
        ->toContain('@measure="engine.measure(node.id, $event)"');

    expect(frontendSource('canvas/geometry.ts'))
        ->toContain('const fromHeight = nodeHeight(from, heights);')
        ->toContain('const toHeight = nodeHeight(to, heights);');
});

it('arrasta com snap de 4 px e redesenha as arestas durante o arrasto', function () {
    expect(frontendSource('composables/useStageInteraction.ts'))
        ->toContain('engine.beginDrag(id, worldX, worldY);')
        ->toContain('engine.dragTo(worldX, worldY);')
        ->toContain('engine.endDrag();');

    expect(frontendSource('canvas/diagram.ts'))
        ->toContain('const nextX = snap(x);')
        ->toContain('const nextY = snap(y);');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('const geometry = engine.geometry(edge);');
});

it('abre a edição do rótulo no duplo clique, com Enter confirmando e Esc cancelando', function () {
    $node = frontendSource('components/prancheta/CanvasNode.vue');

    expect($node)
        ->toContain('@dblclick.stop="beginEdit"')
        ->toMatch("/if \(event\.key === 'Enter'\) \{\s*event\.preventDefault\(\);\s*finishEdit\(true\);/")
        ->toMatch("/if \(event\.key === 'Escape'\) \{\s*event\.preventDefault\(\);\s*finishEdit\(false\);/")
        ->toContain('element.textContent = props.node.label;');
});

it('bloqueia o excedente de 60 caracteres na digitação', function () {
    $node = frontendSource('components/prancheta/CanvasNode.vue');

    expect($node)
        ->toContain('@beforeinput="onBeforeInput"')
        ->toContain('event.preventDefault();');

    expect(frontendSource('canvas/labels.ts'))
        ->toContain('return MAX_LABEL_LENGTH - (current.length - replacing);');
});

it('volta ao nome curto quando o rótulo é esvaziado', function () {
    expect(frontendSource('canvas/labels.ts'))
        ->toContain("return label === '' ? clampLabel(fallback) : label;");

    expect(frontendSource('canvas/engine.ts'))
        ->toMatch('/renameNode\(\s*this\.state,\s*id,\s*label,\s*this\.shortName\(node\.type\),?\s*\)/');
});

it('empilha desfazer ao mover e ao renomear, mas não num arrasto parado', function () {
    $engine = frontendSource('canvas/engine.ts');

    expect($engine)
        ->toMatch('/if \(moved && !drag\.moved\) \{\s*drag\.moved = true;\s*this\.history\.push\(previous\);/')
        ->toMatch('/if \(changed\) \{\s*this\.history\.push\(previous\);/');
});
