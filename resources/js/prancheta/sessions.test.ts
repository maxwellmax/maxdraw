import { describe, expect, it, vi } from 'vitest';
import { problemOptionsFixture, sessionSummariesFixture } from './fixtures';
import type { SessionSummary } from './sessions';
import {
    byRecency,
    commitSessionRename,
    CONFIRM_DELETE_LABEL,
    CURRENT_BADGE,
    deleteIntent,
    deleteLabel,
    DELETE_LABEL,
    formatSessionDate,
    FREE_BOARD_LABEL,
    SESSION_NAME_MAX_LENGTH,
    sessionCountLabel,
    sessionRows,
    UNKNOWN_DATE,
} from './sessions';

const PROBLEMS = problemOptionsFixture();

const NAMED_WITH_PROBLEM = 9;

const UNNAMED_FREE_BOARD = 7;

const BLANK_NAME_WITH_PROBLEM = 4;

function sessionsNamed(id: number, name: string | null): SessionSummary[] {
    return sessionSummariesFixture().map((session) =>
        session.id === id ? { ...session, name } : session,
    );
}

function rowOf(sessions: readonly SessionSummary[], id: number) {
    return sessionRows(sessions, PROBLEMS, null).find((row) => row.id === id)!;
}

function nameOf(
    sessions: readonly SessionSummary[],
    id: number,
): string | null {
    return sessions.find((session) => session.id === id)?.name ?? null;
}

function ackTransport(
    updatedAt: string | null = '2026-03-14T12:30:00.000000Z',
) {
    return vi.fn(async () => ({ updated_at: updatedAt }));
}

describe('lista de sessões', () => {
    it('session_list_is_ordered_by_recency', () => {
        const rows = sessionRows(sessionSummariesFixture(), PROBLEMS, null);

        expect(rows.map((row) => row.id)).toEqual([9, 7, 4]);
    });

    it('ties_are_broken_by_the_newest_id', () => {
        const [first, second] = sessionSummariesFixture();
        const sameMoment = {
            ...second,
            id: 12,
            last_opened_at: first.last_opened_at,
        };

        expect(
            [first, sameMoment].sort(byRecency).map((session) => session.id),
        ).toEqual([12, 7]);
    });

    it('each_row_shows_date_problem_duration_and_time_used', () => {
        const rows = sessionRows(sessionSummariesFixture(), PROBLEMS, null);

        expect(rows[0]).toEqual({
            id: 9,
            name: 'Feed — 2ª tentativa',
            title: 'Feed — 2ª tentativa',
            metaLabel:
                'Feed de rede social · 14 mar 2026 · 60 min · 12:22 · 2 blocos',
            problemName: 'Feed de rede social',
            date: '14 mar 2026',
            durationLabel: '60 min',
            elapsedLabel: '12:22',
            blockCount: 2,
            current: false,
        });

        expect(rows[2].date).toBe('28 fev 2026');
        expect(rows[2].durationLabel).toBe('30 min');
        expect(rows[2].elapsedLabel).toBe('60:05');
    });

    it('a_session_without_a_problem_reads_as_a_free_board', () => {
        const rows = sessionRows(sessionSummariesFixture(), PROBLEMS, null);

        expect(rows[1].problemName).toBe(FREE_BOARD_LABEL);
        expect(FREE_BOARD_LABEL).toBe('Prancheta livre');
    });

    it('the_current_session_is_flagged_in_the_list', () => {
        const rows = sessionRows(sessionSummariesFixture(), PROBLEMS, 7);

        expect(rows.filter((row) => row.current).map((row) => row.id)).toEqual([
            7,
        ]);
        expect(CURRENT_BADGE).toBe('corrente');
    });

    it('an_unreadable_date_never_breaks_the_row', () => {
        expect(formatSessionDate(null)).toBe(UNKNOWN_DATE);
        expect(formatSessionDate('nem data')).toBe(UNKNOWN_DATE);
    });

    it('the_sheet_counts_what_is_saved', () => {
        expect(sessionCountLabel(0)).toBe('0 sessões salvas neste espaço.');
        expect(sessionCountLabel(1)).toBe('1 sessão salva neste espaço.');
        expect(sessionCountLabel(3)).toBe('3 sessões salvas neste espaço.');
    });
});

describe('exclusão de sessão', () => {
    it('delete_requires_explicit_confirmation', () => {
        const remove = vi.fn();

        let armed: number | null = null;

        const click = (id: number): void => {
            const intent = deleteIntent(armed, id);

            if (intent.action === 'arm') {
                armed = intent.id;

                return;
            }

            armed = null;
            remove(intent.id);
        };

        click(9);

        expect(armed).toBe(9);
        expect(remove).not.toHaveBeenCalled();

        click(9);

        expect(remove).toHaveBeenCalledExactlyOnceWith(9);
        expect(armed).toBeNull();
    });

    it('confirming_another_row_only_moves_the_arm', () => {
        expect(deleteIntent(9, 4)).toEqual({ action: 'arm', id: 4 });
        expect(deleteIntent(null, 4)).toEqual({ action: 'arm', id: 4 });
        expect(deleteIntent(4, 4)).toEqual({ action: 'delete', id: 4 });
    });

    it('the_armed_row_says_what_the_next_click_does', () => {
        expect(deleteLabel(null, 9)).toBe(DELETE_LABEL);
        expect(deleteLabel(9, 9)).toBe(CONFIRM_DELETE_LABEL);
        expect(deleteLabel(4, 9)).toBe(DELETE_LABEL);
    });
});

describe('título da linha', () => {
    it('the_session_name_wins_the_title_over_the_problem', () => {
        const row = rowOf(sessionSummariesFixture(), NAMED_WITH_PROBLEM);

        expect(row.title).toBe('Feed — 2ª tentativa');
        expect(row.problemName).toBe('Feed de rede social');
    });

    it('a_named_free_board_is_titled_by_its_name', () => {
        const sessions = sessionsNamed(UNNAMED_FREE_BOARD, '  Treino solto  ');

        expect(rowOf(sessions, UNNAMED_FREE_BOARD).title).toBe('Treino solto');
    });

    it('a_session_without_a_name_is_titled_by_the_problem', () => {
        const blank = rowOf(sessionSummariesFixture(), BLANK_NAME_WITH_PROBLEM);
        const nulled = rowOf(
            sessionsNamed(BLANK_NAME_WITH_PROBLEM, null),
            BLANK_NAME_WITH_PROBLEM,
        );

        expect(blank.title).toBe('Encurtador de URL');
        expect(nulled.title).toBe('Encurtador de URL');
    });

    it('a_nameless_free_board_is_titled_prancheta_livre', () => {
        expect(rowOf(sessionSummariesFixture(), UNNAMED_FREE_BOARD).title).toBe(
            FREE_BOARD_LABEL,
        );
    });
});

describe('metadados da linha', () => {
    it('the_problem_opens_the_metadata_of_a_named_row', () => {
        const row = rowOf(sessionSummariesFixture(), NAMED_WITH_PROBLEM);

        expect(row.metaLabel.startsWith('Feed de rede social · ')).toBe(true);
        expect(row.title).not.toContain('Feed de rede social');
    });

    it('a_named_free_board_opens_its_metadata_with_prancheta_livre', () => {
        const sessions = sessionsNamed(UNNAMED_FREE_BOARD, 'Treino solto');
        const row = rowOf(sessions, UNNAMED_FREE_BOARD);

        expect(row.metaLabel.startsWith(`${FREE_BOARD_LABEL} · `)).toBe(true);
        expect(row.title).toBe('Treino solto');
    });

    it('a_nameless_row_keeps_the_metadata_it_has_today', () => {
        const row = rowOf(sessionSummariesFixture(), UNNAMED_FREE_BOARD);

        expect(row.metaLabel).toBe('11 mar 2026 · 45 min · 00:00 · 0 blocos');
        expect(row.metaLabel).not.toContain(FREE_BOARD_LABEL);
    });
});

describe('renomear sessão', () => {
    it('renaming_a_session_saves_the_trimmed_name', async () => {
        const transport = ackTransport();

        const result = await commitSessionRename(
            sessionSummariesFixture(),
            UNNAMED_FREE_BOARD,
            '  Ensaio de cache  ',
            transport,
        );

        expect(transport).toHaveBeenCalledExactlyOnceWith(UNNAMED_FREE_BOARD, {
            name: 'Ensaio de cache',
        });
        expect(result.status).toBe('saved');
        expect(nameOf(result.sessions, UNNAMED_FREE_BOARD)).toBe(
            'Ensaio de cache',
        );
        expect(result.status === 'saved' && result.updatedAt).toBe(
            '2026-03-14T12:30:00.000000Z',
        );
    });

    it('clearing_the_name_sends_null_once', async () => {
        const transport = ackTransport(null);

        const result = await commitSessionRename(
            sessionSummariesFixture(),
            NAMED_WITH_PROBLEM,
            '   ',
            transport,
        );

        expect(transport).toHaveBeenCalledExactlyOnceWith(NAMED_WITH_PROBLEM, {
            name: null,
        });
        expect(nameOf(result.sessions, NAMED_WITH_PROBLEM)).toBeNull();
        expect(rowOf(result.sessions, NAMED_WITH_PROBLEM).title).toBe(
            'Feed de rede social',
        );
    });

    it('a_failed_rename_warns_once_and_keeps_the_previous_name', async () => {
        const transport = vi.fn(async () => {
            throw new Error('rede fora');
        });
        const warn = vi.fn();

        const result = await commitSessionRename(
            sessionSummariesFixture(),
            NAMED_WITH_PROBLEM,
            'Feed — 3ª tentativa',
            transport,
        );

        if (result.status === 'warned') {
            warn(result.warning);
        }

        expect(transport).toHaveBeenCalledOnce();
        expect(warn).toHaveBeenCalledExactlyOnceWith('sessionRenameFailed');
        expect(nameOf(result.sessions, NAMED_WITH_PROBLEM)).toBe(
            'Feed — 2ª tentativa',
        );
    });

    it('a_name_over_the_limit_warns_and_is_never_cut', async () => {
        const transport = ackTransport();
        const warn = vi.fn();
        const tooLong = 'n'.repeat(SESSION_NAME_MAX_LENGTH + 1);

        const result = await commitSessionRename(
            sessionSummariesFixture(),
            NAMED_WITH_PROBLEM,
            tooLong,
            transport,
        );

        if (result.status === 'warned') {
            warn(result.warning);
        }

        expect(transport).not.toHaveBeenCalled();
        expect(warn).toHaveBeenCalledExactlyOnceWith('sessionNameTooLong');
        expect(nameOf(result.sessions, NAMED_WITH_PROBLEM)).toBe(
            'Feed — 2ª tentativa',
        );
        expect(result.sessions).toEqual(sessionSummariesFixture());
    });
});
