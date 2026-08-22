<?php

/**
 * Os componentes compartilhados da Phase 6.3: botão, chip de estado, folha
 * modal e o toast — o canal único dos avisos previstos nas histórias.
 */
it('dá ao botão as três variações do protótipo', function (string $variant) {
    $button = frontendSource('components/prancheta/PranchetaButton.vue');

    expect($button)
        ->toContain("variant?: 'default' | 'primary' | 'icon';")
        ->toContain($variant.':');
})->with(['default', 'primary', 'icon']);

it('fecha a folha modal por clique fora e por Esc', function () {
    $sheet = frontendSource('components/prancheta/ModalSheet.vue');

    expect($sheet)
        ->toContain('@pointerdown="onOverlayPointerDown"')
        ->toContain('if (event.target === event.currentTarget)')
        ->toContain("if (event.key === 'Escape')")
        ->toContain("document.addEventListener('keydown', onKeydown);")
        ->toContain("document.removeEventListener('keydown', onKeydown);");
});

it('devolve o foco ao gatilho quando a folha modal fecha', function () {
    $sheet = frontendSource('components/prancheta/ModalSheet.vue');

    expect($sheet)
        ->toContain('trigger.value =')
        ->toContain('document.activeElement')
        ->toContain('trigger.value?.focus();');
});

it('anuncia a folha modal como diálogo para leitores de tela', function () {
    expect(frontendSource('components/prancheta/ModalSheet.vue'))
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain(':aria-labelledby="titleId"');
});

it('mostra o toast por 2600 ms e o remove sozinho', function () {
    $toast = frontendSource('composables/useToast.ts');

    expect($toast)
        ->toContain('export const TOAST_DURATION_MS = 2600;')
        ->toContain('setTimeout(() => dismiss(id), TOAST_DURATION_MS);');
});

it('empilha os toasts sem quebrar o layout e os anuncia como status', function () {
    $host = frontendSource('components/prancheta/ToastHost.vue');

    expect($host)
        ->toContain('role="status"')
        ->toContain('aria-live="polite"')
        ->toContain('v-for="entry in toasts"')
        ->toContain('flex-col');
});

it('usa o toast como canal único dos avisos das histórias', function (string $warning) {
    expect(frontendSource('prancheta/warnings.ts'))->toContain($warning.':');
})->with([
    'limite de nós' => ['nodeLimitReached'],
    'limite de arestas' => ['edgeLimitReached'],
    'export com canvas vazio' => ['exportEmptyCanvas'],
    'versão do servidor mais nova' => ['serverVersionIsNewer'],
]);

it('emite o aviso de canvas vazio pelo toast ao exportar sem desenho', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain("warn('exportEmptyCanvas');")
        ->toContain('<ToastHost />');
});

it('aceita os três estados do indicador de salvamento no chip', function (string $state) {
    $chip = frontendSource('components/prancheta/StateChip.vue');

    expect($chip)
        ->toContain("export type ChipState = 'dirty' | 'saving' | 'saved';")
        ->toContain($state.':');
})->with(['dirty', 'saving', 'saved']);

it('rotula os três estados do chip em português', function (string $label) {
    expect(frontendSource('components/prancheta/StateChip.vue'))->toContain($label);
})->with(['não salvo', 'salvando…', 'salvo']);
