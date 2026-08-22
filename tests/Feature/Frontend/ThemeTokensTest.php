<?php

/**
 * A paleta da prancheta é o contrato visual do protótipo congelado. Estes
 * testes comparam token a token com `.spec/init/design/pranchetasystemdesign.html`:
 * qualquer divergência de hex, sombra ou raio quebra aqui.
 */

/**
 * Os tokens que a Phase 6.1 exige, sem os três de tipografia.
 *
 * @return array<int, string>
 */
function requiredThemeTokens(): array
{
    return [
        '--paper', '--panel', '--panel-2', '--panel-3',
        '--ink', '--ink-2', '--ink-3',
        '--line', '--line-2', '--grid',
        '--accent', '--accent-2', '--accent-ink', '--accent-soft',
        '--ok', '--warn', '--crit',
        '--c-client', '--c-edge', '--c-compute', '--c-data', '--c-async', '--c-ops',
        '--shadow-1', '--shadow-2',
        '--r',
    ];
}

/**
 * @return array<int, string>
 */
function categoryTokens(): array
{
    return ['--c-client', '--c-edge', '--c-compute', '--c-data', '--c-async', '--c-ops'];
}

const LIGHT_SELECTOR = ':root';
const DARK_MEDIA_SELECTOR = ':root:not\(\[data-theme=[\'"]light[\'"]\]\)';
const DARK_ATTRIBUTE_SELECTOR = ':root\[data-theme=[\'"]dark[\'"]\]';

it('declara todos os tokens exigidos no :root do tema claro', function (string $token) {
    expect(cssTokens(pranchetaCss(), LIGHT_SELECTOR))->toHaveKey($token);
})->with(requiredThemeTokens());

it('reproduz o :root do protótipo token a token', function () {
    expect(cssTokens(pranchetaCss(), LIGHT_SELECTOR))
        ->toBe(cssTokens(prototypeCss(), LIGHT_SELECTOR));
});

it('reproduz o tema escuro do protótipo nos dois seletores', function (string $selector) {
    $ported = cssTokens(pranchetaCss(), $selector);

    expect($ported)->not->toBeEmpty()
        ->and($ported)->toBe(cssTokens(prototypeCss(), $selector));
})->with([
    'media query' => [DARK_MEDIA_SELECTOR],
    'atributo explícito' => [DARK_ATTRIBUTE_SELECTOR],
]);

it('mantém os dois blocos de tema escuro idênticos entre si', function () {
    expect(cssTokens(pranchetaCss(), DARK_MEDIA_SELECTOR))
        ->toBe(cssTokens(pranchetaCss(), DARK_ATTRIBUTE_SELECTOR));
});

it('não define nenhuma cor apenas dentro da media query', function () {
    $light = cssTokens(pranchetaCss(), LIGHT_SELECTOR);

    foreach (array_keys(cssTokens(pranchetaCss(), DARK_MEDIA_SELECTOR)) as $token) {
        expect($light)->toHaveKey($token);
    }
});

it('dá às seis categorias cores distintas dentro de cada tema', function (string $selector) {
    $tokens = cssTokens(pranchetaCss(), $selector);
    $colors = array_map(fn (string $token): string => $tokens[$token], categoryTokens());

    expect(array_unique($colors))->toHaveCount(6);
})->with([
    'claro' => [LIGHT_SELECTOR],
    'escuro' => [DARK_ATTRIBUTE_SELECTOR],
]);

it('usa hex diferentes para a mesma categoria nos dois temas', function (string $token) {
    expect(cssTokens(pranchetaCss(), LIGHT_SELECTOR)[$token])
        ->not->toBe(cssTokens(pranchetaCss(), DARK_ATTRIBUTE_SELECTOR)[$token]);
})->with(categoryTokens());

it('carrega a paleta a partir do app.css e a expõe ao Tailwind', function () {
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($appCss)->toContain("@import './prancheta.css';")
        ->and(pranchetaCss())->toContain('@theme inline');

    foreach (requiredThemeTokens() as $token) {
        if (str_starts_with($token, '--shadow') || $token === '--r') {
            continue;
        }

        expect(pranchetaCss())->toContain('--color-sd'.substr($token, 1).': var('.$token.');');
    }
});

it('não deixa o starter kit sobrescrever o --accent da prancheta', function () {
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($appCss)->not->toMatch('/^\s+--accent(-foreground)?:/m')
        ->and($appCss)->toContain('--color-accent: var(--ui-accent);');
});

it('mantém as três famílias tipográficas do protótipo', function (string $token) {
    expect(cssTokens(pranchetaCss(), LIGHT_SELECTOR))->toHaveKey($token);
})->with(['--ui', '--mono', '--serif']);
