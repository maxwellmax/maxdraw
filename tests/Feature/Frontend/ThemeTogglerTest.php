<?php

/**
 * O alternador de tema é uma preferência do navegador: mora em
 * `localStorage['sd-theme']`, segue o sistema enquanto ninguém escolher, e
 * nunca chega ao servidor.
 */
it('grava a preferência sob a chave sd-theme', function () {
    expect(frontendSource('prancheta/theme.ts'))
        ->toContain("export const THEME_STORAGE_KEY = 'sd-theme';");

    expect(frontendSource('composables/useTheme.ts'))
        ->toContain('localStorage.setItem(THEME_STORAGE_KEY, theme);')
        ->toContain('localStorage.removeItem(THEME_STORAGE_KEY);');
});

it('cicla do sistema para o oposto dele e depois alterna', function () {
    $theme = frontendSource('prancheta/theme.ts');

    expect($theme)
        ->toContain("return current === 'dark' ? 'light' : 'dark';")
        ->toContain("return systemPrefersDark ? 'light' : 'dark';");
});

it('segue o prefers-color-scheme enquanto não houver preferência gravada', function () {
    $theme = frontendSource('prancheta/theme.ts');

    expect($theme)
        ->toContain("return stored ?? (systemPrefersDark ? 'dark' : 'light');");

    expect(frontendSource('composables/useTheme.ts'))
        ->toContain("window.matchMedia('(prefers-color-scheme: dark)')")
        ->toContain("media?.addEventListener('change'");
});

it('aplica o tema pelo atributo data-theme do documento', function () {
    expect(frontendSource('composables/useTheme.ts'))
        ->toContain("document.documentElement.setAttribute('data-theme', theme);")
        ->toContain("document.documentElement.removeAttribute('data-theme');");
});

it('relê as variáveis de cor antes do próximo quadro a cada troca', function () {
    $composable = frontendSource('composables/useTheme.ts');

    expect($composable)
        ->toContain('requestAnimationFrame(() => {')
        ->toContain('vars.value = readThemeVars();')
        ->toContain('getComputedStyle(document.documentElement)')
        ->toContain('refreshVars();');
});

it('inclui no relido tudo que as arestas usam para se desenhar', function (string $token) {
    expect(frontendSource('prancheta/theme.ts'))->toContain("'".$token."',");
})->with([
    '--c-client', '--c-edge', '--c-compute', '--c-data', '--c-async', '--c-ops',
    '--ink-3', '--accent', '--panel', '--line', '--ink', '--ink-2', '--paper', '--line-2',
]);

it('aplica o tema salvo antes da primeira pintura', function () {
    expect(file_get_contents(resource_path('views/app.blade.php')))
        ->toContain("localStorage.getItem('sd-theme')")
        ->toContain("document.documentElement.setAttribute('data-theme', theme);");

    expect(frontendSource('app.ts'))->toContain('initializePranchetaTheme();');
});

it('não manda a preferência de tema para o servidor', function () {
    expect(frontendSource('composables/useTheme.ts'))
        ->not->toContain('@inertiajs')
        ->not->toContain('document.cookie')
        ->not->toContain('router.');

    expect(file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php')))
        ->not->toContain('sd-theme');
});

it('liga o botão de tema da barra superior ao alternador', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('const { toggle: toggleTheme } = useTheme();')
        ->toContain('@toggle-theme="toggleTheme"');
});
