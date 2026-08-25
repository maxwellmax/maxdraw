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
    ['components/prancheta/SessionList.vue', 'session-rename'],
    ['components/prancheta/SessionList.vue', 'session-rename-input'],
    ['pages/Board.vue', 'new-session'],
]);

it('escreve data, problema, duração escolhida e tempo usado em cada linha', function () {
    expect(frontendSource('components/prancheta/SessionList.vue'))
        ->toContain('{{ row.title }}')
        ->toContain('{{ row.metaLabel }}');

    expect(frontendSource('prancheta/sessions.ts'))
        ->toContain('title: sessionTitle(session, problems),')
        ->toContain('metaLabel: sessionMetaLabel(session, problems),')
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

/**
 * O gesto de renomear (US-11.1): botão na linha, campo pré-preenchido, Enter e
 * saída do campo gravam, `Escape` fecha sem gravar. Sem `@vue/test-utils` no
 * projeto, quem guarda o desenho do gesto é a asserção de fonte.
 */
it('renomeia na própria linha, com Enter, saída do campo e cancelamento por Escape', function () {
    expect(frontendSource('components/prancheta/SessionList.vue'))
        ->toContain('PranchetaInput')
        ->toContain("draft.value = row.name ?? '';")
        ->toContain('@keydown.enter.prevent="finishEdit(row.id, true)"')
        ->toContain('@blur="finishEdit(row.id, true)"')
        ->toContain('@keydown.esc.stop.prevent="finishEdit(row.id, false)"')
        ->toContain("emit('rename', sessionId, typed);")
        ->not->toContain('alert(');
});

it('avisa por toast a falha do rename e o nome acima do teto', function (string $warning) {
    expect(frontendSource('prancheta/warnings.ts'))->toContain($warning.':');

    expect(frontendSource('prancheta/sessions.ts'))->toContain("warnedRename('".$warning."'");

    expect(frontendSource('pages/Board.vue'))
        ->toContain('warn(result.warning);')
        ->not->toContain('alert(');
})->with(['sessionRenameFailed', 'sessionNameTooLong']);

it('manda o nome pelo envio dedicado, na rota do Wayfinder', function () {
    expect(frontendSource('lib/sessionTransport.ts'))
        ->toContain('export async function renameSession(')
        ->toContain('body: { name: string | null },')
        ->toContain('url: update.url(id),')
        ->not->toContain('/api/sessions');
});

it('renomeia sem navegar e sem relistar, realimentando só o baseline da corrente', function () {
    preg_match(
        '/async function saveSessionName\(.*?\n\}\n/s',
        frontendSource('pages/Board.vue'),
        $handler
    );

    expect($handler[0])
        ->toContain('await commitSessionRename(')
        ->toContain('renameSession,')
        ->toContain('if (sessionId !== store.id) {')
        ->toContain('store.setServerUpdatedAt(result.updatedAt);')
        ->toContain('autosave.saveLocal();')
        ->not->toContain('router.visit')
        ->not->toContain('loadSessions');
});

it('mantém o nome da sessão fora do corpo do autosave', function () {
    $source = frontendSource('prancheta/session.ts');

    preg_match('/export type SessionBody = \{.*?\n\};/s', $source, $body);
    preg_match('/export function bodyFrom\(.*?\n\}/s', $source, $builder);

    expect($body[0])->not->toContain('name');
    expect($builder[0])->not->toContain('name');
});

it('cobre o rename sem ferramental de teste novo', function () {
    expect(file_get_contents(base_path('package.json')))
        ->not->toContain('@vue/test-utils')
        ->not->toContain('happy-dom');

    expect(file_get_contents(base_path('vitest.config.ts')))
        ->toContain("environment: 'node',")
        ->toContain("'resources/js/canvas/**/*.test.ts',")
        ->toContain("'resources/js/prancheta/**/*.test.ts',");
});

it('usa no nome da sessão o mesmo limite que o servidor valida', function () {
    preg_match(
        '/private const MAX_SESSION_NAME = (\d+);/',
        file_get_contents(app_path('Http/Requests/TrainingSessionUpdateRequest.php')),
        $server
    );

    preg_match(
        '/export const SESSION_NAME_MAX_LENGTH = (\d+);/',
        frontendSource('prancheta/sessions.ts'),
        $client
    );

    expect($client[1])->toBe($server[1])->toBe('60');
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(pranchetaTestNames())->toContain($name);
})->with([
    'delete_requires_explicit_confirmation',
    'session_list_is_ordered_by_recency',
    'each_row_shows_date_problem_duration_and_time_used',
    'the_current_session_is_flagged_in_the_list',
    'a_session_without_a_problem_reads_as_a_free_board',
    'the_session_name_wins_the_title_over_the_problem',
    'a_named_free_board_is_titled_by_its_name',
    'a_session_without_a_name_is_titled_by_the_problem',
    'a_nameless_free_board_is_titled_prancheta_livre',
    'the_problem_opens_the_metadata_of_a_named_row',
    'a_named_free_board_opens_its_metadata_with_prancheta_livre',
    'a_nameless_row_keeps_the_metadata_it_has_today',
    'renaming_a_session_saves_the_trimmed_name',
    'clearing_the_name_sends_null_once',
    'a_failed_rename_warns_once_and_keeps_the_previous_name',
    'a_name_over_the_limit_warns_and_is_never_cut',
]);
