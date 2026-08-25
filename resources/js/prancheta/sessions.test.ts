import { describe, expect, it, vi } from 'vitest';
import { problemOptionsFixture, sessionSummariesFixture } from './fixtures';
import {
    byRecency,
    CONFIRM_DELETE_LABEL,
    CURRENT_BADGE,
    deleteIntent,
    deleteLabel,
    DELETE_LABEL,
    formatSessionDate,
    FREE_BOARD_LABEL,
    sessionCountLabel,
    sessionRows,
    UNKNOWN_DATE,
} from './sessions';

const PROBLEMS = problemOptionsFixture();

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
