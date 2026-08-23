<?php

/**
 * Os ícones do app: o mark maxdraw servido como favicon.svg, favicon.ico e
 * apple-touch-icon.png, gerados por `scripts/generate-brand-icons.sh` e
 * versionados. O desenho dentro do .ico e do .png não é inspecionável no
 * runner, então o contrato aqui é caminho, dimensão, opacidade e diferença
 * binária em relação aos arquivos do skeleton.
 */

/**
 * O hash dos três ícones como o skeleton do Laravel os versionava — o ponto de
 * partida que a geração da marca precisa ter deixado para trás.
 *
 * @return array<string, string>
 */
function skeletonIconHashes(): array
{
    return [
        'favicon.svg' => '242f4f8f93f5fbc6c8aeec500c9bac02dcfe68daba166d484c5cdc986c88d8ee',
        'favicon.ico' => '4606a56e6ef3f5ec39201497f57069d5457ce9cea25227134d0ba378788e9070',
        'apple-touch-icon.png' => '4001aa032ff113e1a268a9bbf1ab0fd9949439f9f54a85895956eb323aba977d',
    ];
}

it('serve o favicon vetorial como cópia do mark maxdraw', function () {
    expect(file_get_contents(public_path('favicon.svg')))
        ->toContain('M9 9h14M9 9v14M9 23h14M23 9v14')
        ->toBe(file_get_contents(base_path('.spec/init/design/logo/maxdraw-mark.svg')));
});

it('entrega o ícone do iOS em 180×180', function () {
    [$width, $height] = getimagesize(public_path('apple-touch-icon.png'));

    expect([$width, $height])->toBe([180, 180]);
});

it('achata o ícone do iOS sobre o fundo da marca, sem pixel transparente', function (int $x, int $y) {
    $image = imagecreatefrompng(public_path('apple-touch-icon.png'));
    $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));

    expect($color['alpha'])->toBe(0);
})->with([
    'canto superior esquerdo' => [0, 0],
    'canto superior direito' => [179, 0],
    'canto inferior esquerdo' => [0, 179],
    'canto inferior direito' => [179, 179],
    'centro' => [90, 90],
]);

it('deixa de servir os ícones do skeleton', function (string $icon) {
    expect(hash_file('sha256', public_path($icon)))->not->toBe(skeletonIconHashes()[$icon]);
})->with(array_keys(skeletonIconHashes()));

it('mantém as três tags do documento apontando para os mesmos caminhos', function (string $tag) {
    expect(file_get_contents(resource_path('views/app.blade.php')))->toContain($tag);
})->with([
    '<link rel="icon" href="/favicon.ico" sizes="any">',
    '<link rel="icon" href="/favicon.svg" type="image/svg+xml">',
    '<link rel="apple-touch-icon" href="/apple-touch-icon.png">',
]);

it('nomeia a aplicação como maxdraw', function () {
    expect(config('app.name'))->toBe('maxdraw');
});

it('não deixa o nome do skeleton sobrar como fallback', function (string $file, string $fallback) {
    expect(file_get_contents(base_path($file)))
        ->toContain($fallback)
        ->not->toContain('Laravel');
})->with([
    'nome da aplicação' => ['.env.example', 'APP_NAME="maxdraw"'],
    'título do documento' => ['resources/views/app.blade.php', "config('app.name', 'maxdraw')"],
    'título das páginas Inertia' => ['resources/js/app.ts', "import.meta.env.VITE_APP_NAME || 'maxdraw'"],
]);

it('aponta a navegação para o repositório do projeto, não para o do skeleton', function (string $component) {
    expect(file_get_contents(resource_path("js/components/{$component}.vue")))
        ->toContain('https://github.com/maxwellmax/maxdraw')
        ->not->toContain('laravel/vue-starter-kit')
        ->not->toContain('laravel.com/docs');
})->with([
    'cabeçalho' => ['AppHeader'],
    'barra lateral' => ['AppSidebar'],
]);
