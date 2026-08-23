<?php

/**
 * A identidade maxdraw nas telas: o mark no ícone do app, o lockup por tema nas
 * telas de conta, a barra da prancheta com a marca e o layout de conta sem o
 * bloco de marca do starter kit.
 */

/**
 * A folha que decide a variante do lockup, lida crua — a seleção é CSS, não JS.
 */
function brandCss(): string
{
    return file_get_contents(resource_path('css/brand.css'));
}

it('desenha o mark maxdraw no ícone do app, no lugar do logotipo do Laravel', function () {
    expect(frontendSource('components/AppLogoIcon.vue'))
        ->toContain('viewBox="0 0 32 32"')
        ->toContain('M9 9h14M9 9v14M9 23h14M23 9v14')
        ->toContain('width="12"')
        ->toContain('rx="3"')
        ->not->toContain('M17.2 5.633 8.6.855 0 5.633v26.51');
});

it('exibe o lockup uma única vez em cada tela de conta', function (string $page) {
    expect(substr_count(frontendSource($page), 'data-testid="brand-lockup"'))->toBe(1);

    expect(frontendSource($page))
        ->toContain("import BrandLockup from '@/components/BrandLockup.vue';");
})->with(['pages/auth/Login.vue', 'pages/auth/Register.vue']);

it('serve as telas de conta com o lockup montado', function (string $routeName) {
    $this->get(route($routeName))->assertOk();
})->with(['login', 'register']);

it('resolve a variante do lockup nos três estados de tema', function () {
    expect(brandCss())
        ->toContain(":root[data-theme='dark']")
        ->toContain(":root[data-theme='light']")
        ->toMatch('/@media \(prefers-color-scheme: dark\) \{\s*:root:not\(\[data-theme=\'light\'\]\)/');
});

it('escolhe a variante do lockup sem nenhum JavaScript', function () {
    expect(frontendSource('components/BrandLockup.vue'))
        ->toContain('/brand/maxdraw-lockup-dark.svg')
        ->toContain('/brand/maxdraw-lockup-light.svg')
        ->toContain('brand-lockup__dark')
        ->toContain('brand-lockup__light')
        ->not->toContain('useTheme')
        ->not->toContain('matchMedia')
        ->not->toContain('prefers-color-scheme');
});

it('versiona as duas variantes do lockup sob public/brand', function (string $asset) {
    expect(file_exists(public_path('brand/'.$asset)))->toBeTrue();

    expect(file_get_contents(public_path('brand/'.$asset)))
        ->toBe(file_get_contents(base_path('.spec/init/design/logo/'.$asset)));
})->with(['maxdraw-lockup-dark.svg', 'maxdraw-lockup-light.svg']);

it('descreve o lockup para quem não enxerga', function () {
    expect(frontendSource('components/BrandLockup.vue'))->toContain(':alt="alt"');
});

it('tira o bloco de marca do layout das telas de conta', function () {
    expect(frontendSource('layouts/auth/AuthSimpleLayout.vue'))
        ->not->toContain('AppLogoIcon')
        ->not->toContain('home()')
        ->toContain('data-testid="auth-shell"');
});

it('troca o ícone genérico da barra pela marca maxdraw com rótulo acessível', function () {
    expect(frontendSource('components/prancheta/BoardTopBar.vue'))
        ->not->toContain('<rect x="3" y="3" width="7" height="7" rx="1.5" />')
        ->not->toContain('>Prancheta<')
        ->toContain('<AppLogoIcon')
        ->toContain('aria-label="maxdraw — treino de system design"')
        ->toContain('data-testid="topbar"');
});

it('mantém a marca sem cor literal e sem variante dark nos arquivos de tema travado', function (string $file) {
    expect(frontendSource($file))
        ->not->toContain('dark:')
        ->not->toMatch('/#[0-9a-fA-F]{3,8}\b/');
})->with([
    'components/BrandLockup.vue',
    'components/prancheta/BoardTopBar.vue',
    'pages/auth/Login.vue',
    'pages/auth/Register.vue',
    'layouts/auth/AuthSimpleLayout.vue',
]);
