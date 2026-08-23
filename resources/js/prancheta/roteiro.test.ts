import { describe, expect, it } from 'vitest';
import { bounds, curPhase } from './clock';
import { phaseOptionsFixture, sessionRecordFixture } from './fixtures';
import {
    ALL_COLLAPSED,
    FOLLOW_CURRENT,
    isChecked,
    openIndexOf,
    phaseRows,
    toggleChoice,
} from './roteiro';
import { SessionStore } from './session';

const SESSION_ID = 21;

const SERVER_UPDATED_AT = '2026-08-23T12:00:00.000000Z';

const PHASES = phaseOptionsFixture();

const DURATION_MINUTES = 45;

/** O segundo em que a fase de índice `index` começa, para a duração padrão. */
function startOf(index: number): number {
    return index === 0 ? 0 : bounds(DURATION_MINUTES, PHASES)[index - 1].end;
}

function storeWith(checks: Record<string, boolean> = {}): SessionStore {
    return new SessionStore(
        SESSION_ID,
        sessionRecordFixture({ checks }),
        SERVER_UPDATED_AT,
    );
}

describe('roteiro', () => {
    it('lists_the_five_phases_with_number_name_and_minutes', () => {
        const rows = phaseRows(PHASES, {}, 0, DURATION_MINUTES);

        expect(rows).toHaveLength(5);
        expect(rows.map((row) => row.number)).toEqual([1, 2, 3, 4, 5]);
        expect(rows.map((row) => row.name)).toEqual(
            PHASES.map((phase) => phase.name),
        );
        expect(rows.map((row) => row.minutes)).toEqual([5, 5, 8, 12, 15]);
    });

    it('spreads_the_25_items_as_4_5_5_5_6', () => {
        const rows = phaseRows(PHASES, {}, 0, DURATION_MINUTES);

        expect(rows.map((row) => row.total)).toEqual([4, 5, 5, 5, 6]);
        expect(rows.reduce((sum, row) => sum + row.total, 0)).toBe(25);
    });

    it('counts_progress_per_phase_from_the_checks_map', () => {
        const [first] = PHASES[1].checklist_items;
        const rows = phaseRows(
            PHASES,
            { [String(first.id)]: true },
            0,
            DURATION_MINUTES,
        );

        expect(rows.map((row) => row.done)).toEqual([0, 1, 0, 0, 0]);
        expect(rows[1].items[0].checked).toBe(true);
        expect(rows[1].items[1].checked).toBe(false);
    });

    it('toggling_an_item_marks_session_dirty', () => {
        const store = storeWith();
        const item = PHASES[0].checklist_items[2];

        expect(store.isDirty).toBe(false);
        expect(isChecked(store.checks, item.id)).toBe(false);

        store.setCheck(item.id, true);

        expect(store.isDirty).toBe(true);
        expect(isChecked(store.checks, item.id)).toBe(true);
        expect(store.body().checks).toEqual({ [String(item.id)]: true });

        expect(
            phaseRows(PHASES, store.checks, 0, DURATION_MINUTES)[0].done,
        ).toBe(1);

        store.setCheck(item.id, false);

        expect(isChecked(store.checks, item.id)).toBe(false);
        expect(
            phaseRows(PHASES, store.checks, 0, DURATION_MINUTES)[0].done,
        ).toBe(0);
    });

    it('items_of_past_phases_stay_checkable', () => {
        const store = storeWith();
        const item = PHASES[0].checklist_items[0];
        const elapsed = startOf(4) + 30;

        store.setCheck(item.id, true);

        const rows = phaseRows(
            PHASES,
            store.checks,
            elapsed,
            DURATION_MINUTES,
            0,
        );

        expect(rows[0].state).toBe('done');
        expect(rows[0].open).toBe(true);
        expect(rows[0].items[0].checked).toBe(true);
    });

    it('current_phase_opens_by_itself_at_the_boundary', () => {
        const slices = bounds(DURATION_MINUTES, PHASES);

        for (let index = 0; index < PHASES.length; index++) {
            const elapsed = startOf(index) + 1;
            const rows = phaseRows(
                PHASES,
                {},
                elapsed,
                DURATION_MINUTES,
                FOLLOW_CURRENT,
            );

            expect(curPhase(elapsed, slices)).toBe(index);
            expect(
                rows.filter((row) => row.open).map((row) => row.index),
            ).toEqual([index]);
            expect(rows[index].state).toBe('current');
        }
    });

    it('manual_choice_wins_until_the_next_phase_turn', () => {
        const current = 2;

        expect(openIndexOf(FOLLOW_CURRENT, current)).toBe(current);

        const chosen = toggleChoice(FOLLOW_CURRENT, current, 0);

        expect(chosen).toBe(0);
        expect(openIndexOf(chosen, current)).toBe(0);

        const rows = phaseRows(
            PHASES,
            {},
            startOf(current) + 1,
            DURATION_MINUTES,
            chosen,
        );

        expect(rows.filter((row) => row.open).map((row) => row.index)).toEqual([
            0,
        ]);
        expect(rows[current].state).toBe('current');
        expect(rows[current].open).toBe(false);
    });

    it('clicking_the_open_phase_collapses_every_phase', () => {
        const collapsed = toggleChoice(FOLLOW_CURRENT, 1, 1);

        expect(collapsed).toBe(ALL_COLLAPSED);

        const rows = phaseRows(
            PHASES,
            {},
            startOf(1) + 1,
            DURATION_MINUTES,
            collapsed,
        );

        expect(rows.some((row) => row.open)).toBe(false);
    });

    it('changing_duration_rewrites_the_minutes_without_touching_the_checks', () => {
        const item = PHASES[4].checklist_items[0];
        const checks = { [String(item.id)]: true };

        expect(
            phaseRows(PHASES, checks, 0, 30).map((row) => row.minutes),
        ).toEqual([3, 3, 5, 8, 10]);
        expect(
            phaseRows(PHASES, checks, 0, 60).map((row) => row.minutes),
        ).toEqual([7, 7, 11, 16, 20]);
        expect(phaseRows(PHASES, checks, 0, 60)[4].done).toBe(1);
    });
});
