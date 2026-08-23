<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * O shell da prancheta: a rota que o entrega e as peças de layout que todas as
 * fases seguintes preenchem. As peças são verificadas pelo `data-testid` que
 * cada uma carrega — é o contrato que as fases 9 a 19 vão consumir.
 */
it('serve a prancheta pela rota nomeada board', function () {
    seedCatalog();

    $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Board'));
});

it('renderiza a prancheta fora do layout de aplicação do starter kit', function () {
    expect(frontendSource('app.ts'))->toMatch("/case name === 'Board':\s*\n\s*return null;/");
});

it('coloca cada peça do shell no arquivo que a implementa', function (string $file, string $testId) {
    expect(frontendSource($file))->toContain('data-testid="'.$testId.'"');
})->with([
    ['pages/Board.vue', 'board-shell'],
    ['components/prancheta/BoardTopBar.vue', 'topbar'],
    ['components/prancheta/BoardTopBar.vue', 'problem-picker'],
    ['components/prancheta/BoardTopBar.vue', 'problem-name'],
    ['components/prancheta/BoardTopBar.vue', 'sessions-button'],
    ['components/prancheta/BoardTopBar.vue', 'topbar-spacer'],
    ['components/prancheta/BoardTopBar.vue', 'save-chip'],
    ['components/prancheta/BoardTopBar.vue', 'theme-button'],
    ['components/prancheta/BoardTopBar.vue', 'export-button'],
    ['components/prancheta/BoardTopBar.vue', 'save-button'],
    ['components/prancheta/ComponentRail.vue', 'rail'],
    ['components/prancheta/ComponentRail.vue', 'rail-head'],
    ['components/prancheta/ComponentRail.vue', 'rail-hint'],
    ['components/prancheta/ComponentRail.vue', 'palette'],
    ['components/prancheta/StageCanvas.vue', 'stage'],
    ['components/prancheta/StageCanvas.vue', 'grid'],
    ['components/prancheta/StageCanvas.vue', 'world'],
    ['components/prancheta/StageCanvas.vue', 'wires'],
    ['components/prancheta/StageCanvas.vue', 'nodes-layer'],
    ['components/prancheta/StageCanvas.vue', 'labels-layer'],
    ['components/prancheta/StageCanvas.vue', 'empty-state'],
    ['components/prancheta/ZoomBar.vue', 'zoombar'],
    ['components/prancheta/ZoomBar.vue', 'zoom-in'],
    ['components/prancheta/ZoomBar.vue', 'zoom-out'],
    ['components/prancheta/ZoomBar.vue', 'zoom-fit'],
    ['components/prancheta/ZoomBar.vue', 'order-toggle'],
    ['components/prancheta/ZoomBar.vue', 'order-auto'],
    ['components/prancheta/LegendPanel.vue', 'legend'],
    ['components/prancheta/LegendPanel.vue', 'legend-toggle'],
    ['components/prancheta/LegendPanel.vue', 'legend-body'],
    ['components/prancheta/DrillPanel.vue', 'drill'],
    ['components/prancheta/DrillClock.vue', 'clock'],
    ['components/prancheta/DrillClock.vue', 'clock-elapsed'],
    ['components/prancheta/DrillClock.vue', 'duration-picker'],
    ['components/prancheta/DrillTabs.vue', 'tabs'],
]);

it('monta o shell com os quatro blocos do protótipo', function () {
    $board = frontendSource('pages/Board.vue');

    expect($board)
        ->toContain('<BoardTopBar')
        ->toContain('<ComponentRail')
        ->toContain('<StageCanvas')
        ->toContain('<DrillPanel');
});

it('mantém a ordem da barra superior do protótipo', function () {
    $topbar = frontendSource('components/prancheta/BoardTopBar.vue');
    $template = substr($topbar, strpos($topbar, '<template>'));

    $positions = array_map(
        fn (string $testId): int => strpos($template, 'data-testid="'.$testId.'"'),
        ['problem-picker', 'sessions-button', 'topbar-spacer', 'save-chip', 'theme-button', 'export-button', 'save-button'],
    );

    $sorted = $positions;
    sort($sorted);

    expect($positions)->toBe($sorted);
});

it('dá ao palco a grade, o mundo transformável e as três camadas', function () {
    $stage = frontendSource('components/prancheta/StageCanvas.vue');

    expect($stage)
        ->toContain('transform: `translate(')
        ->toContain('<slot name="wires" />')
        ->toContain('<slot name="nodes" />')
        ->toContain('<slot name="labels" />')
        ->toContain('<ZoomBar')
        ->toContain('<LegendPanel');
});

it('ocupa a viewport inteira sem rolagem do body', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('h-dvh')
        ->toContain('grid-cols-[216px_1fr_348px]')
        ->toContain('grid-rows-[48px_1fr]')
        ->toContain('overflow-hidden');

    expect(pranchetaCss())->toMatch('/body:has\(\[data-testid=[\'"]board-shell[\'"]\]\)/');
});

it('faz cada painel rolar por dentro em vez de esticar a página', function (string $file, string $selector) {
    expect(frontendSource($file))->toContain($selector);
})->with([
    'trilho' => ['components/prancheta/ComponentRail.vue', 'overflow-y-auto'],
    'palco' => ['components/prancheta/StageCanvas.vue', 'overflow-hidden'],
    'painel de treino' => ['components/prancheta/DrillPanel.vue', 'overflow-hidden'],
    'aba do painel de treino' => ['components/prancheta/DrillPanel.vue', 'overflow-y-auto'],
    'corpo da legenda' => ['components/prancheta/LegendPanel.vue', 'overflow-y-auto'],
]);
