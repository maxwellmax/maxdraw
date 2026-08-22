<?php

use App\Models\User;

/**
 * A paleta de componentes (Phase 9.2): as 6 categorias com os 28 componentes,
 * na cor da categoria, e o clique que coloca o bloco já pronto para digitar o
 * nome.
 */
it('agrupa a paleta por categoria e desenha cada componente do grupo', function () {
    $palette = frontendSource('components/prancheta/ComponentPalette.vue');

    expect($palette)
        ->toContain('v-for="category in categories"')
        ->toContain('v-for="component in category.components"')
        ->toContain('data-testid="palette-category"')
        ->toContain('data-testid="palette-item"')
        ->toContain(':data-component="component.slug"')
        ->toContain('<CanvasIcon :icon-key="component.icon_key" />');
});

it('pinta cada item da paleta com a cor da categoria', function () {
    expect(frontendSource('components/prancheta/ComponentPalette.vue'))
        ->toContain('`var(${category.color_token})`');
});

it('entrega a paleta ao trilho pelo slot da Phase 6', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toMatch('/<ComponentRail>\s*<ComponentPalette/')
        ->toContain(':categories="catalog.component_categories"')
        ->toContain('@pick="placeNode"');
});

it('serve as 6 categorias com cor e os 28 componentes com ícone do motor', function () {
    seedCatalog();

    $categories = $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->viewData('page')['props']['catalog']['component_categories'];

    expect($categories)->toHaveCount(6)
        ->and(collect($categories)->pluck('slug')->all())
        ->toBe(['client', 'edge', 'compute', 'data', 'async', 'ops']);

    $components = collect($categories)->flatMap(fn (array $category): array => $category['components']);

    expect($components)->toHaveCount(28);

    foreach ($categories as $category) {
        expect($category['color_token'])->toStartWith('--c-');
    }

    foreach ($components as $component) {
        expect(canvasIconKeys())->toContain($component['icon_key'])
            ->and($component['short_name'])->not->toBeEmpty();
    }
});

it('nomeia o bloco novo com o nome curto do componente', function () {
    seedCatalog();

    $dlq = collect(
        $this->actingAs(User::factory()->create())
            ->get(route('board'))
            ->viewData('page')['props']['catalog']['component_categories']
    )
        ->flatMap(fn (array $category): array => $category['components'])
        ->firstWhere('slug', 'dlq');

    expect($dlq['short_name'])->toBe('DLQ')
        ->and($dlq['name'])->toBe('DLQ — fila de falhas')
        ->and(frontendSource('canvas/diagram.ts'))
        ->toContain('label: component.short_name');
});

it('coloca o bloco na área visível, sem sobrepor, e abre o nome focado', function () {
    $board = frontendSource('pages/Board.vue');

    expect($board)
        ->toContain('const result = engine.addNode(slug);')
        ->toContain('editNode(result.value.id);')
        ->toContain('nextTick(() => nodeComponents.get(id)?.beginEdit());');

    expect(frontendSource('canvas/engine.ts'))
        ->toContain('centerSpot(this.view, this.size, NODE_HEIGHT)');

    expect(frontendSource('canvas/diagram.ts'))
        ->toContain('const [x, y] = freeSpot(snap(at[0]), snap(at[1]), state.nodes);');
});

it('avisa por toast e não adiciona o bloco no limite de 200', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toMatch("/if \(result\.reason === 'nodeLimitReached'\) \{\s*warn\(result\.reason\);/");

    expect(frontendSource('prancheta/warnings.ts'))->toContain('nodeLimitReached:');

    expect(frontendSource('canvas/diagram.ts'))
        ->toMatch("/if \(state\.nodes\.length >= MAX_NODES\) \{\s*return \{ ok: false, reason: 'nodeLimitReached' \};/");
});

it('tira a cor do bloco da categoria, sem caminho para o usuário mudá-la', function () {
    expect(frontendSource('canvas/catalog.ts'))
        ->toContain('index.get(slug)?.category.color_token');

    expect(frontendSource('pages/Board.vue'))
        ->toContain(':color="engine.color(node.type)"');

    expect(frontendSource('components/prancheta/CanvasNode.vue'))
        ->not->toContain('color-picker')
        ->not->toContain('v-model="color"');
});
