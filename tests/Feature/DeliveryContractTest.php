<?php

use App\Models\TrainingSession;
use Illuminate\Support\Facades\Schema;

/**
 * O fechamento do MVP (Phase 20): o `README.md` de quem clona o repositório e o
 * registro de paridade com o protótipo. O registro só vale enquanto aponta para
 * código que existe — por isso cada linha da tabela é conferida contra o disco,
 * e cada divergência deliberada é conferida contra a implementação que a causou.
 */
function readme(): string
{
    return file_get_contents(base_path('README.md'));
}

/**
 * Cada Core Workflow do `project-description`, com o título que a tabela de
 * paridade usa e o código que o implementa.
 *
 * @return array<int, array{0: string, 1: array<int, string>}>
 */
function workflowSources(): array
{
    return [
        1 => ['Registrar-se, entrar e retomar o treino', [
            'app/Http/Controllers/BoardController.php',
            'app/Services/CurrentSessionResolver.php',
            'app/Services/CatalogService.php',
            'resources/js/pages/Board.vue',
        ]],
        2 => ['Escolher o problema e iniciar a sessão', [
            'resources/js/components/prancheta/ProblemPicker.vue',
            'resources/js/components/prancheta/ProblemBrief.vue',
            'resources/js/prancheta/problems.ts',
        ]],
        3 => ['Desenhar o diagrama', [
            'resources/js/canvas/nodes.ts',
            'resources/js/canvas/edges.ts',
            'resources/js/canvas/geometry.ts',
            'resources/js/canvas/layout.ts',
            'resources/js/canvas/view.ts',
            'resources/js/canvas/undo.ts',
            'resources/js/components/prancheta/StageCanvas.vue',
            'resources/js/components/prancheta/ComponentPalette.vue',
        ]],
        4 => ['Tipar as ligações e ordenar a sequência', [
            'resources/js/canvas/links.ts',
            'resources/js/canvas/sequence.ts',
            'resources/js/components/prancheta/EdgeFloatBar.vue',
            'resources/js/components/prancheta/LinkKindMenu.vue',
            'resources/js/components/prancheta/SequenceMenu.vue',
        ]],
        5 => ['Ler a legenda automática', [
            'resources/js/canvas/legend.ts',
            'resources/js/prancheta/legend.ts',
            'resources/js/components/prancheta/LegendPanel.vue',
            'resources/js/composables/useLegend.ts',
        ]],
        6 => ['Cronometrar as fases e marcar o checklist', [
            'resources/js/prancheta/clock.ts',
            'resources/js/prancheta/roteiro.ts',
            'resources/js/components/prancheta/DrillClock.vue',
            'resources/js/components/prancheta/PhaseAccordion.vue',
        ]],
        7 => ['Fazer as estimativas de capacidade', [
            'resources/js/prancheta/estimate.ts',
            'resources/js/components/prancheta/EstimatePanel.vue',
        ]],
        8 => ['Salvar automaticamente', [
            'resources/js/prancheta/autosave.ts',
            'resources/js/composables/useAutosave.ts',
            'app/Services/SessionStateWriter.php',
        ]],
        9 => ['Exportar o diagrama em SVG', [
            'resources/js/canvas/svg.ts',
            'resources/js/prancheta/export.ts',
        ]],
        10 => ['Encerrar o treino e conferir o gabarito', [
            'resources/js/components/prancheta/ProblemItemList.vue',
            'resources/js/prancheta/problems.ts',
        ]],
        11 => ['Gerenciar sessões', [
            'resources/js/components/prancheta/SessionList.vue',
            'resources/js/prancheta/sessions.ts',
            'app/Http/Controllers/TrainingSessionController.php',
        ]],
    ];
}

dataset('coreWorkflows', fn (): array => collect(workflowSources())
    ->mapWithKeys(fn (array $workflow, int $number): array => [
        'workflow '.$number => [$number, $workflow[0], $workflow[1]],
    ])
    ->all()
);

/**
 * @param  array<int, string>  $sources
 */
it('percorre os onze Core Workflows no registro de paridade', function (int $number, string $title, array $sources) {
    expect(readme())->toContain('| '.$number.' | '.$title.' |');

    foreach ($sources as $source) {
        expect(base_path($source))->toBeFile();
    }
})->with('coreWorkflows');

it('documenta as duas trilhas de ambiente', function (string $marker) {
    expect(readme())->toContain($marker);
})->with([
    'título da trilha em container' => ['### Trilha 1 — Sail (container)'],
    'título da trilha nativa' => ['### Trilha 2 — nativa (sem container)'],
    'subida via Sail' => ['./vendor/bin/sail up -d'],
    'servidor nativo' => ['php artisan serve'],
    'vite nativo' => ['npm run dev'],
]);

it('explica que o catálogo é seedado e que editá-lo é editar o seeder', function () {
    expect(readme())
        ->toContain('O catálogo é seedado, não editável pela interface')
        ->toContain('**Mudar o catálogo é editar o seeder e rodar o seed de novo**')
        ->toContain('php artisan db:seed --class=CatalogSeeder');
});

it('aponta o protótipo congelado como referência visual e comportamental', function () {
    expect(readme())
        ->toContain('.spec/init/design/pranchetasystemdesign.html')
        ->toContain('protótipo congelado');

    expect(base_path('.spec/init/design/pranchetasystemdesign.html'))->toBeFile();
});

it('registra o que ficou fora do v1', function (string $item) {
    expect(readme())->toContain('## Fora do v1')
        ->and(readme())->toContain('**'.$item.'**');
})->with([
    'modo apresentação' => ['Modo apresentação'],
    'templates de partida' => ['Templates de partida'],
    'histórico entre sessões' => ['Histórico entre sessões'],
    'autolayout' => ['Autolayout'],
    'bff' => ['BFF'],
    'redis' => ['Redis'],
    'recuperação de senha' => ['Recuperação de senha'],
]);

it('registra o catálogo em banco no lugar das constantes do protótipo', function () {
    expect(readme())->toContain('Catálogo em **tabelas de banco**, populadas por seeder');

    foreach (catalogTables() as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(app_path('Concerns/ReadOnlyAtRuntime.php'))->toBeFile();
});

it('registra as sessões por usuário no lugar do localStorage do protótipo', function () {
    expect(readme())->toContain('**Sessões por usuário** no banco, escopadas ao autenticado');

    expect(Schema::hasColumn('training_sessions', 'user_id'))->toBeTrue()
        ->and(method_exists(TrainingSession::class, 'ownedBy'))->toBeTrue();
});

it('registra o debounce de 800 ms e 3 s no lugar dos 250 ms e 7 s do protótipo', function () {
    expect(readme())->toContain('Debounce de **800 ms** para o espelho local e **3 s** para o servidor');

    expect(frontendSource('prancheta/autosave.ts'))
        ->toContain('export const LOCAL_SAVE_DELAY_MS = 800;')
        ->toContain('export const SERVER_SAVE_DELAY_MS = 3000;');
});

it('registra o checks chaveado por id de item no lugar de fase:índice', function () {
    expect(readme())->toContain('`checks` chaveado por **`checklist_items.id`**');

    expect(file_get_contents(app_path('Http/Requests/TrainingSessionUpdateRequest.php')))
        ->toContain('O mapa `checks` é chaveado por `checklist_items.id`');
});

it('roda as duas suítes em CI com o mesmo conjunto de comandos', function (string $command) {
    $scripts = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR)['scripts'];

    $chain = [...$scripts['ci:check'], ...$scripts['test']];

    expect($chain)->toContain($command);
})->with([
    'eslint' => ['npm run lint:check'],
    'prettier' => ['npm run format:check'],
    'vue-tsc' => ['npm run types:check'],
    'vitest' => ['npm run test'],
    'pest' => ['@php artisan test'],
]);

it('deixa o CI chamar a mesma cadeia e subir o banco do zero', function () {
    $workflow = file_get_contents(base_path('.github/workflows/tests.yml'));

    expect($workflow)
        ->toContain('composer ci:check')
        ->toContain('php artisan migrate:fresh --seed --force')
        ->toContain('on:')
        ->toContain('push:')
        ->toContain('pull_request:');
});

it('mantém a suíte independente de estado prévio do banco', function () {
    $phpunit = file_get_contents(base_path('phpunit.xml'));

    expect($phpunit)
        ->toContain('<env name="DB_CONNECTION" value="sqlite"/>')
        ->toContain('<env name="DB_DATABASE" value=":memory:"/>');

    expect(file_get_contents(base_path('tests/Pest.php')))
        ->toContain('->use(RefreshDatabase::class)');
});
