<?php

use Illuminate\Support\Facades\Route;

/**
 * Os diretórios versionados varridos atrás de referências à rota removida.
 *
 * @return array<int, string>
 */
function scannedSourceDirectories(): array
{
    return ['app', 'bootstrap', 'config', 'resources', 'routes', 'tests'];
}

/**
 * Os arquivos de código versionados desses diretórios. `resources/js/routes`,
 * `actions` e `wayfinder` ficam de fora porque são artefatos gerados pelo
 * Wayfinder (gitignored), e este próprio arquivo também, já que nomeia a rota
 * removida para poder afirmar que ela sumiu.
 *
 * @return array<int, string>
 */
function scannedSourceFiles(): array
{
    $generated = [
        resource_path('js/routes'),
        resource_path('js/actions'),
        resource_path('js/wayfinder'),
    ];

    $files = [];

    foreach (scannedSourceDirectories() as $directory) {
        $tree = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            $path = $file->getPathname();

            if ($path === __FILE__) {
                continue;
            }

            if (! in_array($file->getExtension(), ['php', 'ts', 'js', 'vue', 'css'], true)) {
                continue;
            }

            foreach ($generated as $generatedDirectory) {
                if (str_starts_with($path, $generatedDirectory.DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }

            $files[] = $path;
        }
    }

    return $files;
}

it('apaga os arquivos do starter kit', function (string $path) {
    expect(file_exists($path))->toBeFalse();
})->with([
    'página Welcome' => fn () => resource_path('js/pages/Welcome.vue'),
    'página Dashboard' => fn () => resource_path('js/pages/Dashboard.vue'),
    'teste legado do dashboard' => fn () => base_path('tests/Feature/DashboardTest.php'),
]);

it('não registra mais a rota do dashboard', function () {
    expect(Route::getRoutes()->getByName('dashboard'))->toBeNull();
});

it('não deixa nenhuma referência à rota removida no código versionado', function () {
    $matches = [];

    foreach (scannedSourceFiles() as $path) {
        if (stripos(file_get_contents($path), 'dashboard') !== false) {
            $matches[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($matches)->toBe([]);
});

it('renderiza só a prancheta fora do layout de aplicação', function () {
    expect(frontendSource('app.ts'))
        ->not->toContain('Welcome')
        ->toMatch("/case name === 'Board':\s*\n\s*return null;/");
});

it('aponta o shell autenticado para a prancheta', function (string $file) {
    expect(frontendSource($file))
        ->toContain("import { board } from '@/routes';")
        ->toContain("title: 'Prancheta',")
        ->toContain('href: board(),')
        ->toContain(':href="board()"');
})->with([
    'barra lateral' => 'components/AppSidebar.vue',
    'cabeçalho' => 'components/AppHeader.vue',
]);

it('mantém exatamente um item de navegação principal no shell', function (string $file) {
    $source = frontendSource($file);
    $items = substr(
        $source,
        strpos($source, 'const mainNavItems'),
        strpos($source, '];', strpos($source, 'const mainNavItems')) - strpos($source, 'const mainNavItems'),
    );

    expect(substr_count($items, 'title:'))->toBe(1);
})->with([
    'barra lateral' => 'components/AppSidebar.vue',
    'cabeçalho' => 'components/AppHeader.vue',
]);

it('leva a verificação por passkey para a prancheta quando o servidor não manda destino', function () {
    expect(frontendSource('components/PasskeyVerify.vue'))
        ->toContain("router.visit(response.redirect ?? '/prancheta');");
});
