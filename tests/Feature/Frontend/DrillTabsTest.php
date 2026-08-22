<?php

use Illuminate\Support\Str;

/**
 * A navegação por abas do painel de treino: quais abas existem, qual abre por
 * padrão e por que os painéis nunca são desmontados.
 */
it('declara as quatro abas do protótipo, na ordem', function () {
    $tabs = frontendSource('prancheta/tabs.ts');

    preg_match_all("/\{ id: '([a-z]+)', label: '([^']+)' \}/", $tabs, $matches, PREG_SET_ORDER);

    expect(array_map(fn (array $match): array => [$match[1], $match[2]], $matches))->toBe([
        ['roteiro', 'Roteiro'],
        ['enunciado', 'Enunciado'],
        ['calc', 'Estimativas'],
        ['notas', 'Notas'],
    ]);
});

it('abre no Roteiro ao carregar', function () {
    expect(frontendSource('prancheta/tabs.ts'))
        ->toContain("export const DEFAULT_DRILL_TAB: DrillTabId = 'roteiro';")
        ->and(frontendSource('pages/Board.vue'))
        ->toContain('ref<DrillTabId>(DEFAULT_DRILL_TAB)');
});

it('destaca visualmente só a aba ativa', function () {
    $tabs = frontendSource('components/prancheta/DrillTabs.vue');

    expect($tabs)
        ->toContain(':aria-selected="modelValue === tab.id"')
        ->toContain('aria-selected:border-b-sd-accent')
        ->toContain('aria-selected:text-sd-ink');
});

it('mantém os quatro painéis montados para não perder o que foi digitado', function () {
    $panel = frontendSource('components/prancheta/DrillPanel.vue');

    expect($panel)
        ->toContain('v-show="modelValue === tab.id"')
        ->and($panel)->not->toContain('v-if="modelValue === tab.id"');
});

it('navega pelas abas com o teclado', function () {
    $tabs = frontendSource('components/prancheta/DrillTabs.vue');

    expect($tabs)
        ->toContain('role="tablist"')
        ->toContain('role="tab"')
        ->toContain('@keydown="onKeydown"')
        ->toContain(':tabindex="modelValue === tab.id ? 0 : -1"');

    foreach (['ArrowRight', 'ArrowLeft', 'Home', 'End'] as $key) {
        expect($tabs)->toContain("case '{$key}':");
    }
});

it('liga cada aba ao painel que ela controla', function (string $tab) {
    expect(frontendSource('components/prancheta/DrillTabs.vue'))
        ->toContain(':aria-controls="`pane-${tab.id}`"')
        ->and(frontendSource('components/prancheta/DrillPanel.vue'))
        ->toContain('role="tabpanel"')
        ->toContain(':aria-labelledby="`tab-${tab.id}`"');

    expect(Str::contains(frontendSource('prancheta/tabs.ts'), "id: '{$tab}'"))->toBeTrue();
})->with(['roteiro', 'enunciado', 'calc', 'notas']);
