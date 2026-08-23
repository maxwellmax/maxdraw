<?php

/**
 * A folha de sessões (Phase 19): listagem, abertura, criação e exclusão. Quem
 * testa o arranjo é o Vitest; o que a suíte PHP guarda aqui é o contrato — as
 * peças por `data-testid`, a gravação antes de trocar, a confirmação anterior
 * à requisição e a cobertura que a fase exige.
 */
it('coloca cada peça da folha de sessões no arquivo que a implementa', function (string $file, string $testId) {
    expect(frontendSource($file))->toContain('data-testid="'.$testId.'"');
})->with([
    ['components/prancheta/SessionList.vue', 'session-list'],
    ['components/prancheta/SessionList.vue', 'session-row'],
    ['components/prancheta/SessionList.vue', 'session-row-name'],
    ['components/prancheta/SessionList.vue', 'session-row-meta'],
    ['components/prancheta/SessionList.vue', 'session-current-badge'],
    ['components/prancheta/SessionList.vue', 'session-open'],
    ['components/prancheta/SessionList.vue', 'session-delete'],
    ['components/prancheta/SessionList.vue', 'session-empty'],
    ['pages/Board.vue', 'new-session'],
]);

it('escreve data, problema, duração escolhida e tempo usado em cada linha', function () {
    expect(frontendSource('components/prancheta/SessionList.vue'))
        ->toContain('{{ row.problemName }}')
        ->toContain('{{ row.date }} · {{ row.durationLabel }} ·')
        ->toContain('{{ row.elapsedLabel }}');

    expect(frontendSource('prancheta/sessions.ts'))
        ->toContain('date: formatSessionDate(session.last_opened_at),')
        ->toContain('durationLabel: formatDurationChoice(session.duration_minutes),')
        ->toContain('elapsedLabel: formatClock(session.elapsed_seconds),');
});

it('ordena da mais recente para a mais antiga nos dois lados', function () {
    expect(frontendSource('prancheta/sessions.ts'))
        ->toContain('return [...sessions].sort(byRecency).map((session) => ({');

    expect(file_get_contents(app_path('Http/Controllers/TrainingSessionController.php')))
        ->toContain("->orderByDesc('last_opened_at')")
        ->toContain("->orderByDesc('id')");
});

it('identifica a sessão corrente na lista', function () {
    expect(frontendSource('prancheta/sessions.ts'))
        ->toContain('current: session.id === currentId,')
        ->toContain("export const CURRENT_BADGE = 'corrente';");

    expect(frontendSource('components/prancheta/SessionList.vue'))
        ->toContain(':aria-current="row.current"')
        ->toContain('v-if="row.current"');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('sessionRows(savedSessions.value, props.catalog.problems, store.id),');
});

it('grava a sessão corrente antes de trocar e antes de criar outra', function () {
    $board = frontendSource('pages/Board.vue');

    expect($board)
        ->toContain('await saveNow();'."\n".'        await openSession(sessionId);')
        ->toContain('await saveNow();'."\n".'        await createSession();');

    expect(frontendSource('composables/useAutosave.ts'))
        ->toContain('saveNow: () => Promise<void>;')
        ->toContain('return { autosave, saveNow: () => autosave.save() };');
});

it('recarrega a prancheta desmontada quando a sessão corrente muda de id', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('router.visit(board.url(), { preserveState: false, preserveScroll: true });');
});

it('pede confirmação explícita antes de qualquer requisição de exclusão', function () {
    expect(frontendSource('prancheta/sessions.ts'))
        ->toContain("return armed === id ? { action: 'delete', id } : { action: 'arm', id };");

    expect(frontendSource('pages/Board.vue'))
        ->toContain("if (intent.action === 'arm') {")
        ->toContain('armedDeletion.value = intent.id;')
        ->toContain('void removeSession(intent.id);');
});

it('fala com o servidor pelo cliente HTTP do Inertia, com as rotas do Wayfinder', function () {
    expect(frontendSource('lib/sessionTransport.ts'))
        ->toContain('index.url()')
        ->toContain('open.url(id)')
        ->toContain('store.url()')
        ->toContain('destroy.url(id)')
        ->not->toContain('/api/sessions')
        ->not->toContain('Bearer');
});

it('deixa a folha de sessões fora do payload da sessão', function () {
    expect(frontendSource('prancheta/session.ts'))
        ->not->toContain('SessionSummary')
        ->not->toContain('last_opened_at');
});

it('avisa por toast quando o servidor recusa listar, trocar ou excluir', function (string $warning) {
    expect(frontendSource('prancheta/warnings.ts'))->toContain($warning.':');

    expect(frontendSource('pages/Board.vue'))->toContain("warn('".$warning."')");
})->with(['sessionListFailed', 'sessionSwitchFailed', 'sessionDeleteFailed']);

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(pranchetaTestNames())->toContain($name);
})->with([
    'delete_requires_explicit_confirmation',
    'session_list_is_ordered_by_recency',
    'each_row_shows_date_problem_duration_and_time_used',
    'the_current_session_is_flagged_in_the_list',
    'a_session_without_a_problem_reads_as_a_free_board',
]);
