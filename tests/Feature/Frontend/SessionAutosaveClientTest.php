<?php

/**
 * O autosave no cliente (Phase 10): o store da sessão, o duplo debounce, o
 * indicador e a resiliência. Quem testa o comportamento é o Vitest; o que a
 * suíte PHP guarda aqui é o contrato — a pureza do pacote, os números da fase,
 * o payload combinado com o servidor e a cobertura que a fase exige.
 */
it('mantém o pacote da prancheta livre de framework e de DOM', function (string $file) {
    expect(frontendSource('prancheta/'.$file))
        ->not->toContain("from 'vue'")
        ->not->toContain("from '@inertiajs")
        ->not->toContain('document.')
        ->not->toContain('window.');
})->with(fn () => pranchetaFiles());

it('congela os tempos do autosave', function (string $declaration) {
    expect(frontendSource('prancheta/autosave.ts'))->toContain($declaration);
})->with([
    'inércia da gravação local' => ['export const LOCAL_SAVE_DELAY_MS = 800;'],
    'inércia do envio ao servidor' => ['export const SERVER_SAVE_DELAY_MS = 3000;'],
    'teto do envio' => ['export const SERVER_SAVE_MAX_WAIT_MS = 15000;'],
    'espera do rate limit' => ['export const RATE_LIMIT_DELAY_MS = 20000;'],
    'primeira tentativa' => ['export const RETRY_BASE_MS = 3000;'],
    'teto do recuo' => ['export const RETRY_MAX_MS = 48000;'],
]);

/**
 * `name` é a única chave que o servidor valida e o autosave não manda: o rename
 * tem envio dedicado (`renameSession()`), e pôr o nome no `SessionBody` o
 * tornaria campo sujo, reenviado a cada gravação do diagrama (RF-04/CT-01).
 */
it('envia o mesmo payload que a TrainingSessionUpdateRequest valida', function () {
    preg_match(
        '/export type SessionBody = \{(.*?)\n\};/s',
        frontendSource('prancheta/session.ts'),
        $body
    );

    preg_match_all('/^\s+([a-z_]+):/m', $body[1], $client);

    preg_match_all(
        "/^            '([a-z_]+)' => \[/m",
        file_get_contents(app_path('Http/Requests/TrainingSessionUpdateRequest.php')),
        $server
    );

    expect(collect($client[1])->sort()->values()->all())
        ->toBe(collect($server[1])->reject(fn (string $field): bool => $field === 'name')->sort()->values()->all());
});

it('monta o payload do servidor num arquivo só', function (string $path) {
    expect(file_get_contents($path))->not->toContain('elapsed_seconds');
})->with(fn () => collect(
    array_merge(
        glob(dirname(__DIR__, 3).'/resources/js/pages/*.vue'),
        glob(dirname(__DIR__, 3).'/resources/js/components/prancheta/*.vue'),
        glob(dirname(__DIR__, 3).'/resources/js/composables/*.ts'),
        glob(dirname(__DIR__, 3).'/resources/js/lib/*.ts'),
    )
)->mapWithKeys(fn (string $path): array => [basename($path) => [$path]])->all());

it('usa o cliente HTTP do Inertia, com a rota do Wayfinder e sem token de API', function () {
    expect(frontendSource('lib/sessionTransport.ts'))
        ->toContain("import { http } from '@inertiajs/vue3';")
        ->toContain('http.getClient().request({')
        ->toContain("method: 'put',")
        ->toContain('url: update.url(id),')
        ->not->toContain('/api/sessions')
        ->not->toContain('Bearer')
        ->not->toContain('Authorization');
});

it('deriva o indicador do estado do payload, com os três rótulos do protótipo', function () {
    expect(frontendSource('prancheta/autosave.ts'))
        ->toContain("export type SaveState = 'dirty' | 'saving' | 'saved';")
        ->and(frontendSource('components/prancheta/StateChip.vue'))
        ->toContain("dirty: 'não salvo'")
        ->toContain("saving: 'salvando…'")
        ->toContain("saved: 'salvo'");
});

it('liga a prancheta ao store, ao autosave e ao botão de salvar agora', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('createSessionStore(props.session)')
        ->toContain('useAutosave(store)')
        ->toMatch('/new CanvasEngine\(\s*store\.state,/')
        ->toContain(':save-state="autosave.chip"')
        ->toContain('@save="saveNow"');
});

it('avisa por toast quando a versão do servidor é mais nova', function () {
    expect(frontendSource('composables/useAutosave.ts'))
        ->toContain("warn('serverVersionIsNewer')")
        ->and(frontendSource('prancheta/warnings.ts'))
        ->toContain('serverVersionIsNewer:');
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(pranchetaTestNames())->toContain($name);
})->with([
    'any_state_change_marks_session_dirty',
    'view_and_selection_changes_do_not_mark_dirty',
    'local_save_fires_after_800ms_of_quiet',
    'server_save_fires_after_3s_of_quiet',
    'burst_of_changes_resets_both_timers',
    'unload_triggers_a_final_local_save',
    'failed_save_keeps_dirty_and_schedules_retry',
    'rate_limited_save_backs_off_and_retries',
    'newer_server_version_warns_instead_of_overwriting',
    'local_state_is_reapplied_after_reload',
    'newer_local_state_wins_on_boot',
]);
