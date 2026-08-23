<?php

use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

/**
 * O contrato da ordem explícita das conexões (RF-13, RF-19, RNF-05): o desenho
 * revogado — o lookup de modo de numeração e as funções que derivavam número do
 * desenho — não pode sobreviver em canto nenhum do repositório, e o campo novo
 * não pode ter mexido em nenhum limite de payload.
 */
$revokedVocabulary = ['sequence_mode', 'seq_mode', 'outSeq', 'flowSeq'];

/**
 * Os sete diretórios que a varredura cobre.
 *
 * @return array<int, string>
 */
function sweptDirectories(): array
{
    return [
        app_path(),
        database_path(),
        resource_path('js'),
        base_path('routes'),
        base_path('tests'),
        base_path('docs/agents'),
        base_path('.ai/rules'),
    ];
}

/**
 * O registro histórico da remoção — os únicos arquivos que ainda podem nomear o
 * lookup extinto, porque é a remoção dele que eles executam ou provam: as três
 * migrations da cadeia (a que criou a tabela, a que criou a FK e a que derruba
 * as duas) e os dois testes de migration que guardam RF-14/RF-14a. Somam-se a
 * eles este arquivo, que precisa citar o vocabulário para varrê-lo.
 *
 * @return array<int, string>
 */
function removalRecordFiles(): array
{
    return [
        'database/migrations/2026_08_22_210033_create_sequence_modes_table.php',
        'database/migrations/2026_08_22_210054_create_training_sessions_table.php',
        'database/migrations/2026_08_23_085323_drop_sequence_modes_table.php',
        'tests/Feature/Migrations/LookupTablesMigrationTest.php',
        'tests/Feature/Migrations/TrainingSessionsMigrationTest.php',
        'tests/Feature/OrderContractTest.php',
    ];
}

/**
 * Os arquivos dos sete diretórios que citam um dos termos revogados, como
 * caminho relativo à raiz do projeto.
 *
 * @param  array<int, string>  $vocabulary
 * @return array<int, string>
 */
function filesMentioning(array $vocabulary): array
{
    $found = [];

    foreach (sweptDirectories() as $directory) {
        foreach (Finder::create()->files()->in($directory) as $file) {
            $contents = (string) file_get_contents($file->getRealPath());

            foreach ($vocabulary as $term) {
                if (str_contains($contents, $term)) {
                    $found[] = str($file->getRealPath())->after(base_path().DIRECTORY_SEPARATOR)->replace(DIRECTORY_SEPARATOR, '/')->value();

                    continue 2;
                }
            }
        }
    }

    sort($found);

    return $found;
}

/**
 * O valor de uma constante privada da FormRequest do autosave.
 */
function serverLimit(string $constant): string
{
    preg_match(
        '/private const '.$constant.' = (\d+);/',
        (string) file_get_contents(app_path('Http/Requests/TrainingSessionUpdateRequest.php')),
        $matches,
    );

    return $matches[1] ?? '';
}

it('não deixa o vocabulário revogado em nenhum dos sete diretórios varridos', function () use ($revokedVocabulary) {
    expect(filesMentioning($revokedVocabulary))->toBe(removalRecordFiles());
});

it('varre cada termo revogado, um a um', function (string $term) {
    expect(array_diff(filesMentioning([$term]), removalRecordFiles()))->toBe([]);
})->with($revokedVocabulary);

it('não tem mais o lookup de modo de numeração no schema', function () {
    expect(Schema::hasTable('sequence_modes'))->toBeFalse()
        ->and(Schema::hasColumn('training_sessions', 'sequence_mode_id'))->toBeFalse()
        ->and(Schema::hasColumn('training_sessions', 'show_connection_order'))->toBeTrue();
});

it('mantém os limites de payload que a ordem não deveria mexer', function (string $constant, string $value) {
    expect(serverLimit($constant))->toBe($value);
})->with([
    'nós' => ['MAX_NODES', '200'],
    'arestas' => ['MAX_EDGES', '400'],
    'rótulo' => ['MAX_LABEL', '60'],
    'notas' => ['MAX_NOTES', '5000'],
]);

it('espelha no cliente os três limites que o motor também precisa conhecer', function (string $constant, string $exported, string $value) {
    expect(frontendSource('canvas/limits.ts'))->toContain('export const '.$exported.' = '.$value.';')
        ->and(serverLimit($constant))->toBe($value);
})->with([
    'nós' => ['MAX_NODES', 'MAX_NODES', '200'],
    'arestas' => ['MAX_EDGES', 'MAX_EDGES', '400'],
    'rótulo' => ['MAX_LABEL', 'MAX_LABEL_LENGTH', '60'],
]);

it('deixa o limite das notas só no servidor, onde ele sempre morou', function () {
    expect(frontendSource('canvas/limits.ts'))->not->toContain('5000');
});

it('registra a regra da numeração explícita e o limite da constante de legenda', function (string $excerpt) {
    expect(file_get_contents(base_path('.ai/rules/canvas.md')))->toContain($excerpt);
})->with([
    'uma sequência por diagrama' => ['há uma sequência por diagrama'],
    'ordem explícita na aresta' => ['cada aresta guarda o próprio número em `edge.order`'],
    'densificação como único ponto de saída' => ['Toda mutação de ordem sai por `densify()`: é o único ponto de saída'],
    'BFS determinística' => ['`autoNumber()` é uma BFS determinística'],
    'fallback de três degraus' => ['fallback de três degraus'],
    'bidir sem semântica de grafo' => ['`bidir` é bandeira de desenho, não tem semântica de grafo'],
    'órfã mantém número e não desenha badge' => ['mantém o número dela, conta para `N` e apenas não desenha badge'],
    'toggle não empilha desfazer' => ['O toggle `showConnectionOrder` NÃO empilha'],
    'limite da constante de legenda' => ['constante única no cliente sim, catálogo paralelo no cliente não'],
    'a que par o limite se aplica' => ['`ORDER_NAME`/`ORDER_GLOSS`'],
]);

it('registra em requests a faixa e a unicidade de `order`, sem densidade', function (string $excerpt) {
    expect(file_get_contents(base_path('.ai/rules/requests.md')))->toContain($excerpt);
})->with([
    'faixa' => ['`sometimes|nullable|integer|min:1|max:MAX_EDGES`'],
    'unicidade' => ['orderIsUniqueInTheDiagram()'],
    'densidade é do cliente' => ['O que o servidor NÃO valida é densidade'],
]);
