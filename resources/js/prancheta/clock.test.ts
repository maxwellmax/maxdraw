import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { SessionTransport } from './autosave';
import { Autosave } from './autosave';
import type { PhaseOption } from './clock';
import {
    bounds,
    clockTone,
    CLOCK_PERSIST_MS,
    CLOCK_TICK_MS,
    curPhase,
    durationsFrom,
    formatClock,
    phaseSegments,
    SessionClock,
    totalSeconds,
} from './clock';
import type { MemoryStorage } from './fixtures';
import {
    memoryStorage,
    phaseOptionsFixture,
    sessionDurationOptionsFixture,
    sessionRecordFixture,
} from './fixtures';
import {
    readCache,
    resolveBoot,
    SESSION_CACHE_VERSION,
    writeCache,
} from './resume';
import type { SessionBody } from './session';
import { bodyFrom, recordFrom, SessionStore } from './session';

const SESSION_ID = 7;

const SERVER_UPDATED_AT = '2026-08-23T12:00:00.000000Z';

const PHASES = phaseOptionsFixture();

/** A duração de cada fase em minutos, arredondada como o roteiro a mostra. */
function minutesOf(durationMinutes: number, phases: PhaseOption[] = PHASES) {
    return bounds(durationMinutes, phases).map((slice) =>
        Math.round(slice.duration / 60),
    );
}

type Harness = {
    store: SessionStore;
    clock: SessionClock;
    persisted: () => number;
};

function harness(record = sessionRecordFixture()): Harness {
    const store = new SessionStore(SESSION_ID, record, SERVER_UPDATED_AT);
    const persist = vi.fn();

    return {
        store,
        clock: new SessionClock({ store, persist }),
        persisted: () => persist.mock.calls.length,
    };
}

/** O store como a prancheta o monta ao abrir: rascunho do navegador primeiro. */
function bootedStore(storage: MemoryStorage): SessionStore {
    const server = {
        id: SESSION_ID,
        body: bodyFrom(sessionRecordFixture()),
        updatedAt: SERVER_UPDATED_AT,
    };

    const boot = resolveBoot(server, readCache(storage, SESSION_ID));

    return new SessionStore(
        SESSION_ID,
        recordFrom(boot.body),
        server.updatedAt,
        server.body,
    );
}

describe('fronteiras das fases', () => {
    it('bounds_for_45_minutes_matches_5_5_8_12_15', () => {
        expect(minutesOf(45)).toEqual([5, 5, 8, 12, 15]);

        expect(bounds(45, PHASES).map((slice) => slice.end)).toEqual([
            297, 594, 1080, 1809, 2700,
        ]);
    });

    it('bounds_sum_equals_total_duration', () => {
        for (const durationMinutes of durationsFrom(
            sessionDurationOptionsFixture(),
        )) {
            const slices = bounds(durationMinutes, PHASES);
            const sum = slices.reduce(
                (accumulated, slice) => accumulated + slice.duration,
                0,
            );

            expect(sum).toBe(totalSeconds(durationMinutes));
            expect(slices[slices.length - 1].end).toBe(
                totalSeconds(durationMinutes),
            );
        }
    });

    it('curPhase_advances_at_each_boundary', () => {
        const slices = bounds(45, PHASES);

        expect(curPhase(0, slices)).toBe(0);

        slices.forEach((slice, index) => {
            expect(curPhase(slice.end - 1, slices)).toBe(index);

            if (index < slices.length - 1) {
                expect(curPhase(slice.end, slices)).toBe(index + 1);
            }
        });
    });

    it('curPhase_clamps_to_last_phase_after_total', () => {
        const slices = bounds(45, PHASES);

        expect(curPhase(2700, slices)).toBe(4);
        expect(curPhase(9999, slices)).toBe(4);
        expect(curPhase(120, [])).toBe(-1);
    });

    it('changing_duration_preserves_elapsed', () => {
        const { store, clock } = harness();

        store.setElapsedSeconds(600);
        clock.start();

        clock.setDurationMinutes(60);

        expect(clock.running).toBe(true);
        expect(store.elapsedSeconds).toBe(600);
        expect(store.durationMinutes).toBe(60);
        expect(minutesOf(60)).toEqual([7, 7, 11, 16, 20]);
        expect(curPhase(600, bounds(60, PHASES))).toBe(1);

        clock.setDurationMinutes(30);

        expect(store.elapsedSeconds).toBe(600);
        expect(minutesOf(30)).toEqual([3, 3, 5, 8, 10]);
        expect(curPhase(600, bounds(30, PHASES))).toBe(2);

        clock.stop();
    });
});

describe('barra e aparência do relógio', () => {
    it('mostra as cinco fatias com a largura do peso e o progresso da corrente', () => {
        const segments = phaseSegments(297 + 297 / 2, 45, PHASES);

        expect(segments.map((segment) => segment.weight)).toEqual([
            0.11, 0.11, 0.18, 0.27, 0.33,
        ]);

        expect(segments.map((segment) => segment.state)).toEqual([
            'done',
            'current',
            'todo',
            'todo',
            'todo',
        ]);

        expect(segments[1].progress).toBeCloseTo(0.5, 5);
        expect(segments[0].progress).toBe(0);
    });

    it('avisa aos 85% e de novo ao estourar o total', () => {
        expect(clockTone(0, 45)).toBe('normal');
        expect(clockTone(2700 * 0.85, 45)).toBe('normal');
        expect(clockTone(2700 * 0.85 + 1, 45)).toBe('warn');
        expect(clockTone(2700, 45)).toBe('over');
        expect(formatClock(2700)).toBe('45:00');
        expect(formatClock(-5)).toBe('00:00');
    });
});

describe('cronômetro da sessão', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('play conta, pausa interrompe e o decorrido fica no estado', () => {
        const { store, clock } = harness();

        clock.toggle();
        vi.advanceTimersByTime(5 * CLOCK_TICK_MS);

        expect(clock.running).toBe(true);
        expect(store.elapsedSeconds).toBe(5);

        clock.toggle();
        vi.advanceTimersByTime(30 * CLOCK_TICK_MS);

        expect(clock.running).toBe(false);
        expect(store.elapsedSeconds).toBe(5);
    });

    it('zerar para o relógio e reabre a primeira fase', () => {
        const { store, clock } = harness();

        clock.start();
        vi.advanceTimersByTime(400 * CLOCK_TICK_MS);
        clock.reset();

        expect(clock.running).toBe(false);
        expect(store.elapsedSeconds).toBe(0);
        expect(curPhase(store.elapsedSeconds, bounds(45, PHASES))).toBe(0);
        expect(store.notes).toBe(sessionRecordFixture().notes);
    });

    it('para e sinaliza o fim quando o tempo restante zera, sem apagar nada', () => {
        const { store, clock } = harness(
            sessionRecordFixture({
                elapsedSeconds: 2697,
                notes: 'o desenho continua aqui',
            }),
        );

        clock.start();
        vi.advanceTimersByTime(10 * CLOCK_TICK_MS);

        expect(clock.running).toBe(false);
        expect(clock.finished).toBe(true);
        expect(clock.tone).toBe('over');
        expect(store.elapsedSeconds).toBe(2700);
        expect(store.notes).toBe('o desenho continua aqui');

        clock.start();

        expect(clock.running).toBe(false);
    });

    it('running_clock_persists_elapsed_periodically', () => {
        const { store, clock, persisted } = harness();

        clock.start();

        vi.advanceTimersByTime(CLOCK_PERSIST_MS - CLOCK_TICK_MS);

        expect(persisted()).toBe(0);

        vi.advanceTimersByTime(CLOCK_TICK_MS);

        expect(store.elapsedSeconds).toBe(CLOCK_PERSIST_MS / 1000);
        expect(persisted()).toBe(1);

        vi.advanceTimersByTime(2 * CLOCK_PERSIST_MS);

        expect(persisted()).toBe(3);

        clock.stop();
    });

    it('grava periodicamente sem enviar ao servidor a cada tique', () => {
        const store = new SessionStore(
            SESSION_ID,
            sessionRecordFixture(),
            SERVER_UPDATED_AT,
        );
        const storage = memoryStorage();
        const sent: SessionBody[] = [];
        const send: SessionTransport = async (
            _id: number,
            body: SessionBody,
        ) => {
            sent.push(body);

            return { updated_at: SERVER_UPDATED_AT };
        };

        const autosave = new Autosave({ store, storage, send });
        const clock = new SessionClock({
            store,
            persist: () => autosave.saveLocal(),
        });

        clock.start();

        // O watcher do payload é o que chama `touch()` na tela; aqui ele é explícito.
        for (let second = 0; second < 20; second++) {
            vi.advanceTimersByTime(CLOCK_TICK_MS);
            autosave.touch();
        }

        expect(store.elapsedSeconds).toBe(20);
        expect(sent.length).toBeLessThanOrEqual(2);
        expect(sent.length).toBeGreaterThan(0);

        clock.stop();
        autosave.stop();
    });

    it('clock_resumes_paused_at_persisted_elapsed', () => {
        const storage = memoryStorage();
        const body: SessionBody = bodyFrom(
            sessionRecordFixture({ elapsedSeconds: 742, durationMinutes: 60 }),
        );

        writeCache(storage, {
            version: SESSION_CACHE_VERSION,
            id: SESSION_ID,
            savedAt: 1,
            serverUpdatedAt: SERVER_UPDATED_AT,
            body,
        });

        const store = bootedStore(storage);
        const clock = new SessionClock({ store });

        expect(clock.running).toBe(false);
        expect(clock.elapsedSeconds).toBe(742);

        vi.advanceTimersByTime(30 * CLOCK_TICK_MS);

        expect(clock.running).toBe(false);
        expect(store.elapsedSeconds).toBe(742);
        expect(clock.tone).toBe('normal');
    });
});
