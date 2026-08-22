<?php

/**
 * Trava a stack fechada na Phase 1: as versões que o starter kit oficial do
 * Laravel 13 entrega. Em especial o Inertia 3 — a `.spec` foi escrita quando o
 * kit ainda trazia Inertia 2, e um downgrade quebraria o layout raiz, que usa a
 * sintaxe de componentes v3.
 */
function packageJson(): array
{
    return json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
}

function composerJson(): array
{
    return json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
}

it('declara a versão esperada de cada dependência de frontend', function (string $package, string $constraint) {
    $manifest = packageJson();
    $dependencies = array_merge($manifest['dependencies'], $manifest['devDependencies']);

    expect($dependencies)->toHaveKey($package)
        ->and($dependencies[$package])->toStartWith($constraint);
})->with([
    'inertia' => ['@inertiajs/vue3', '^3.'],
    'vue' => ['vue', '^3.'],
    'tailwind' => ['tailwindcss', '^4.'],
    'typescript' => ['typescript', '^5.'],
    'vite' => ['vite', '^8.'],
]);

it('declara a versão esperada de cada dependência de backend', function (string $package, string $constraint) {
    $requirements = array_merge(composerJson()['require'], composerJson()['require-dev']);

    expect($requirements)->toHaveKey($package)
        ->and($requirements[$package])->toStartWith($constraint);
})->with([
    'php' => ['php', '^8.4'],
    'framework' => ['laravel/framework', '^13.'],
    'inertia' => ['inertiajs/inertia-laravel', '^3.'],
    'boost' => ['laravel/boost', '^2.'],
]);

it('mantém o entrypoint Inertia em TypeScript com strict ligado', function () {
    expect(base_path('resources/js/app.ts'))->toBeFile()
        ->and(base_path('resources/js/app.js'))->not->toBeFile();

    expect(file_get_contents(base_path('tsconfig.json')))
        ->toMatch('/^\s*"strict":\s*true/m');
});
