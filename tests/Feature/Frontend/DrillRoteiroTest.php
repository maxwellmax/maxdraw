<?php

use App\Models\ChecklistItem;
use App\Models\Phase;

/**
 * O roteiro e as notas (Phase 15): o acordeão das cinco fases com os 25 itens
 * marcáveis e o bloco de notas da sessão. Quem testa o comportamento é o
 * Vitest; o que a suíte PHP guarda aqui é o contrato — as peças por
 * `data-testid`, a ligação com o store, o limite combinado com o servidor e a
 * fidelidade da fixture ao catálogo.
 */
it('coloca cada peça do roteiro no arquivo que a implementa', function (string $file, string $testId) {
    expect(frontendSource($file))->toContain('data-testid="'.$testId.'"');
})->with([
    ['components/prancheta/PhaseAccordion.vue', 'phases'],
    ['components/prancheta/PhaseAccordion.vue', 'phase'],
    ['components/prancheta/PhaseAccordion.vue', 'phase-head'],
    ['components/prancheta/PhaseAccordion.vue', 'phase-number'],
    ['components/prancheta/PhaseAccordion.vue', 'phase-name'],
    ['components/prancheta/PhaseAccordion.vue', 'phase-minutes'],
    ['components/prancheta/PhaseAccordion.vue', 'phase-progress'],
    ['components/prancheta/PhaseAccordion.vue', 'phase-items'],
    ['components/prancheta/ChecklistItem.vue', 'chk'],
    ['components/prancheta/ChecklistItem.vue', 'chk-box'],
    ['components/prancheta/NotesPad.vue', 'notes'],
]);

it('mostra número, nome, progresso e os minutos da fatia em cada fase', function () {
    expect(frontendSource('components/prancheta/PhaseAccordion.vue'))
        ->toContain("String(phase.number).padStart(2, '0')")
        ->toContain('{{ phase.name }}')
        ->toContain('{{ phase.done }}/{{ phase.total }}')
        ->toContain('{{ phase.minutes }} min');

    expect(frontendSource('prancheta/roteiro.ts'))
        ->toContain('minutes: Math.round(slices[index].duration / 60),');
});

it('preenche os painéis do roteiro e das notas com os slots do DrillPanel', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('<template #roteiro>')
        ->toContain('<PhaseAccordion')
        ->toContain(':phases="roteiro"')
        ->toContain('@toggle-phase="pickPhase"')
        ->toContain('@toggle-item="toggleCheck"')
        ->toContain('<template #notas>')
        ->toContain('<NotesPad')
        ->toContain(':model-value="store.notes"')
        ->toContain('@update:model-value="store.setNotes($event)"');

    expect(frontendSource('components/prancheta/DrillPanel.vue'))
        ->toContain('<slot v-if="tab.id === \'roteiro\'" name="roteiro" />')
        ->toContain('<slot v-else name="notas" />');
});

it('abre sozinha a fase corrente e respeita a escolha manual até a virada', function () {
    expect(frontendSource('prancheta/roteiro.ts'))
        ->toContain('export const FOLLOW_CURRENT = null;')
        ->toContain('export const ALL_COLLAPSED = -1;')
        ->toContain('return openIndexOf(choice, current) === index ? ALL_COLLAPSED : index;');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('watch(phaseIndex, () => (phaseChoice.value = FOLLOW_CURRENT));')
        ->toContain('toggleChoice(');
});

it('mantém marcável o item de qualquer fase, inclusive as já passadas', function () {
    expect(frontendSource('components/prancheta/PhaseAccordion.vue'))
        ->toContain('v-show="phase.open"')
        ->not->toContain('v-if="phase.open"');

    expect(frontendSource('components/prancheta/ChecklistItem.vue'))
        ->toContain('@click="$emit(\'toggle\')"')
        ->not->toContain('disabled');
});

it('marca pelo id do item do catálogo, com ausência valendo desmarcado', function () {
    expect(frontendSource('prancheta/roteiro.ts'))
        ->toContain('return checks[String(itemId)] === true;');

    expect(frontendSource('prancheta/session.ts'))
        ->toContain('setCheck(itemId: number | string, done: boolean): void');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('store.setCheck(itemId, !store.checks[String(itemId)]);');
});

it('mantém a fixture do Vitest fiel aos itens do catálogo', function () {
    seedCatalog();

    preg_match_all(
        "/\{\s*id: (\d+),\s+content:\s+'([^']+)',?\s*\}/u",
        frontendSource('prancheta/fixtures.ts'),
        $fixture,
        PREG_SET_ORDER
    );

    $client = array_map(fn (array $match): array => [
        'id' => (int) $match[1],
        'content' => $match[2],
    ], $fixture);

    expect($client)->toBe(ChecklistItem::query()->orderBy('id')->get()
        ->map(fn (ChecklistItem $item): array => [
            'id' => $item->id,
            'content' => $item->content,
        ])
        ->all());
});

it('distribui os 25 itens em 4 / 5 / 5 / 5 / 6', function () {
    seedCatalog();

    expect(Phase::query()->active()->withCount('checklistItems')->get()
        ->map(fn (Phase $phase): int => $phase->checklist_items_count)
        ->all())->toBe([4, 5, 5, 5, 6]);
});

it('não guarda no cliente cópia do texto dos itens do catálogo', function () {
    seedCatalog();

    $client = frontendSource('prancheta/roteiro.ts')
        .frontendSource('components/prancheta/PhaseAccordion.vue')
        .frontendSource('components/prancheta/ChecklistItem.vue');

    foreach (ChecklistItem::query()->get() as $item) {
        expect($client)->not->toContain($item->content);
    }
});

it('usa nas notas o mesmo limite que o servidor valida', function () {
    preg_match(
        '/private const MAX_NOTES = (\d+);/',
        file_get_contents(app_path('Http/Requests/TrainingSessionUpdateRequest.php')),
        $server
    );

    preg_match(
        '/export const NOTES_MAX_LENGTH = (\d+);/',
        frontendSource('prancheta/notes.ts'),
        $client
    );

    expect($client[1])->toBe($server[1])->toBe('5000');
});

it('bloqueia a digitação no limite das notas com aviso, sem truncar em silêncio', function () {
    expect(frontendSource('prancheta/notes.ts'))
        ->toContain('notes: text.slice(0, NOTES_MAX_LENGTH),')
        ->toContain('blocked: text.length > NOTES_MAX_LENGTH,');

    expect(frontendSource('components/prancheta/NotesPad.vue'))
        ->toContain('const accepted = acceptNotes(field.value);')
        ->toContain("emit('blocked');")
        ->not->toContain('maxlength');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('@blocked="warn(\'notesLimitReached\')"');

    expect(frontendSource('prancheta/warnings.ts'))->toContain('notesLimitReached:');
});

it('digita nas notas sem enviar por tecla', function () {
    expect(frontendSource('components/prancheta/NotesPad.vue'))
        ->toContain('@input="onInput"')
        ->not->toContain('@keydown')
        ->not->toContain('@change')
        ->not->toContain('router.')
        ->not->toContain('useForm');
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(pranchetaTestNames())->toContain($name);
})->with([
    'toggling_an_item_marks_session_dirty',
    'typing_notes_marks_dirty_and_debounces',
]);
