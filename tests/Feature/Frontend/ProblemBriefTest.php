<?php

use App\Models\Problem;
use App\Models\ProblemItem;

/**
 * O enunciado, o seletor e o gabarito (Phase 16). Quem testa o comportamento é
 * o Vitest; o que a suíte PHP guarda aqui é o contrato — as peças por
 * `data-testid`, a ligação com o store, o atraso do protótipo e a fidelidade
 * da fixture ao catálogo de verdade.
 */
it('coloca cada peça do enunciado no arquivo que a implementa', function (string $file, string $testId) {
    expect(frontendSource($file))->toContain('data-testid="'.$testId.'"');
})->with([
    ['components/prancheta/ProblemPicker.vue', 'problem-list'],
    ['components/prancheta/ProblemPicker.vue', 'problem-card'],
    ['components/prancheta/ProblemPicker.vue', 'problem-card-name'],
    ['components/prancheta/ProblemPicker.vue', 'problem-card-summary'],
    ['components/prancheta/ProblemPicker.vue', 'problem-card-level'],
    ['components/prancheta/ProblemBrief.vue', 'enunciado'],
    ['components/prancheta/ProblemBrief.vue', 'problem-empty'],
    ['components/prancheta/ProblemBrief.vue', 'problem-title'],
    ['components/prancheta/ProblemBrief.vue', 'problem-tag'],
    ['components/prancheta/ProblemBrief.vue', 'problem-level'],
    ['components/prancheta/ProblemBrief.vue', 'problem-context'],
    ['components/prancheta/ProblemBrief.vue', 'topics-reveal'],
    ['components/prancheta/ProblemBrief.vue', 'topics-summary'],
    ['components/prancheta/ProblemBrief.vue', 'topics-body'],
    ['components/prancheta/ProblemItemList.vue', 'problem-items'],
    ['components/prancheta/ProblemItemList.vue', 'problem-item'],
]);

it('mostra nome, etiqueta de tema e nível em cada cartão da folha', function () {
    $picker = frontendSource('components/prancheta/ProblemPicker.vue');

    expect($picker)
        ->toContain('{{ card.name }}')
        ->toContain('{{ card.tag }} · {{ card.levelName }}')
        ->toContain('const cards = computed(() => problemCards(props.problems));');

    expect(frontendSource('prancheta/problems.ts'))
        ->toContain('summary: firstSentence(problem.context),')
        ->toContain('tone: levelTone(problem.level_position),');
});

it('grava o problema escolhido na sessão corrente e fecha a folha', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('function pickProblem(problemId: number | null): void {')
        ->toContain('store.setProblemId(problemId);')
        ->toContain('openSheet.value = null;')
        ->toContain('@pick="pickProblem"');

    expect(frontendSource('prancheta/session.ts'))
        ->toContain('setProblemId(problemId: number | null): void {')
        ->toContain('problem_id: state.problemId,');
});

it('abre a folha sozinha na sessão sem problema e sem blocos, com o atraso do protótipo', function () {
    expect(frontendSource('prancheta/problems.ts'))
        ->toContain('export const PICKER_DELAY_MS = 450;')
        ->toContain('return problemId === null && nodeCount === 0;');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('if (!opensPickerOnLoad(store.problemId, store.nodes.length)) {')
        ->toContain('PICKER_DELAY_MS,');
});

it('deixa fechar a folha sem escolher, mantendo a prancheta livre', function () {
    $board = frontendSource('pages/Board.vue');

    expect($board)
        ->toContain('data-testid="sheet-close"')
        ->toContain('data-testid="free-board"')
        ->toContain('@click="pickProblem(null)"');
});

it('escreve o nome do problema corrente na barra superior, ou o convite', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain(':problem-name="problem?.name ?? null"');

    expect(frontendSource('components/prancheta/BoardTopBar.vue'))
        ->toContain("problemName ?? 'Escolher problema'");

    expect(frontendSource('prancheta/problems.ts'))
        ->toContain("export const NO_PROBLEM_LABEL = 'Escolher problema';");
});

it('renderiza o contexto em serifada e as listas na fonte da interface', function () {
    expect(frontendSource('components/prancheta/ProblemBrief.vue'))
        ->toContain('font-sd-serif');

    expect(frontendSource('components/prancheta/ProblemItemList.vue'))
        ->not->toContain('font-sd-serif');
});

it('separa requisitos funcionais de escala e restrições', function () {
    $brief = frontendSource('components/prancheta/ProblemBrief.vue');

    expect($brief)
        ->toContain('Requisitos funcionais')
        ->toContain('Escala e restrições')
        ->toContain(':items="problem.requirements"')
        ->toContain(':items="problem.scale" variant="scale"');
});

it('explica a prancheta livre quando não há problema escolhido', function () {
    expect(frontendSource('components/prancheta/ProblemBrief.vue'))
        ->toContain('Nenhum problema escolhido. Abra')
        ->toContain('ou use a prancheta livre.');
});

it('mantém o enunciado acessível na aba dele durante todo o treino', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('<template #enunciado>')
        ->toContain('<ProblemBrief :problem="problem" />');

    expect(frontendSource('components/prancheta/DrillPanel.vue'))
        ->toContain('<slot v-else-if="tab.id === \'enunciado\'" name="enunciado" />')
        ->toContain('v-show="modelValue === tab.id"');
});

it('nasce colapsado, rotulado para abrir só depois de terminar', function () {
    $brief = frontendSource('components/prancheta/ProblemBrief.vue');

    expect($brief)
        ->toContain('<details')
        ->toContain('<summary')
        ->not->toContain('open="true"')
        ->not->toContain(':open=');

    expect(frontendSource('prancheta/problems.ts'))
        ->toContain("'Tópicos que este problema cobra — abra só depois de terminar';");
});

it('não guarda em lugar nenhum se o gabarito já foi aberto', function () {
    expect(frontendSource('components/prancheta/ProblemBrief.vue'))
        ->not->toContain('localStorage')
        ->not->toContain('sessionStorage');

    expect(frontendSource('prancheta/session.ts'))
        ->not->toContain('topics')
        ->not->toContain('reveal');
});

it('a fixture do enunciado é fiel ao catálogo seedado', function (string $slug) {
    seedCatalog();

    $fixture = frontendSource('prancheta/fixtures.ts');
    $problem = Problem::query()->with(['problemLevel', 'problemItems.problemItemType'])->where('slug', $slug)->sole();

    expect($fixture)
        ->toContain("slug: '{$slug}',")
        ->toContain("name: '".addcslashes($problem->name, "'")."',")
        ->toContain("tag: '".addcslashes($problem->tag, "'")."',")
        ->toContain("level: '{$problem->problemLevel->slug}',")
        ->toContain("level_name: '".addcslashes($problem->problemLevel->name, "'")."',")
        ->toContain('level_position: '.$problem->problemLevel->position.',');

    $problem->problemItems->each(
        fn (ProblemItem $item) => expect($fixture)->toContain("'".addcslashes($item->content, "'")."',")
    );
})->with(['url', 'feed', 'drive']);
