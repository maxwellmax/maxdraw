import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type {
    AutosaveOptions,
    SessionTransport,
    UnloadTarget,
} from './autosave';
import {
    Autosave,
    backoffDelay,
    LOCAL_SAVE_DELAY_MS,
    RATE_LIMIT_DELAY_MS,
    RATE_LIMITED_LABEL,
    RETRY_BASE_MS,
    RETRY_MAX_MS,
    SaveRejected,
    SERVER_SAVE_DELAY_MS,
    SERVER_SAVE_MAX_WAIT_MS,
} from './autosave';
import { memoryStorage, sessionRecordFixture } from './fixtures';
import type { MemoryStorage } from './fixtures';
import type { SessionCacheEntry } from './resume';
import {
    readCache,
    SESSION_CACHE_VERSION,
    sessionCacheKey,
    writeCache,
} from './resume';
import type { SessionBody } from './session';
import { bodyFrom, SessionStore } from './session';

const SESSION_ID = 12;

const SERVER_UPDATED_AT = '2026-08-22T12:00:00.000000Z';

const ACK_UPDATED_AT = '2026-08-22T12:05:00.000000Z';

type Harness = {
    store: SessionStore;
    storage: MemoryStorage;
    autosave: Autosave;
    sent: SessionBody[];
    send: SessionTransport;
    cached: () => SessionCacheEntry | null;
};

function harness(options: Partial<AutosaveOptions> = {}): Harness {
    const store = new SessionStore(
        SESSION_ID,
        sessionRecordFixture(),
        SERVER_UPDATED_AT,
    );
    const storage = memoryStorage();
    const sent: SessionBody[] = [];
    const send =
        options.send ??
        (async (_id: number, body: SessionBody) => {
            sent.push(body);

            return { updated_at: ACK_UPDATED_AT };
        });

    return {
        store,
        storage,
        sent,
        send,
        autosave: new Autosave({ store, storage, send, ...options }),
        cached: () => readCache(storage, SESSION_ID),
    };
}

/** Um envio que só termina quando o teste mandar. */
function deferred(): { promise: Promise<void>; resolve: () => void } {
    let resolve: () => void = () => {};
    const promise = new Promise<void>((done) => {
        resolve = done;
    });

    return { promise, resolve };
}

/** Uma alteração de verdade, das que sujam a sessão. */
function change(store: SessionStore, autosave: Autosave, notes: string): void {
    store.setNotes(notes);
    autosave.touch();
}

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('duplo debounce', () => {
    it('local_save_fires_after_800ms_of_quiet', async () => {
        const { store, autosave, cached, sent } = harness();

        change(store, autosave, 'particionar por user_id');

        await vi.advanceTimersByTimeAsync(LOCAL_SAVE_DELAY_MS - 1);

        expect(cached()).toBeNull();

        await vi.advanceTimersByTimeAsync(1);

        expect(cached()?.body.notes).toBe('particionar por user_id');
        expect(sent).toHaveLength(0);
        expect(autosave.chip).toBe('dirty');
    });

    it('server_save_fires_after_3s_of_quiet', async () => {
        const { store, autosave, sent } = harness();

        change(store, autosave, 'cache aside no feed');

        await vi.advanceTimersByTimeAsync(SERVER_SAVE_DELAY_MS - 1);

        expect(sent).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(1);

        expect(sent).toHaveLength(1);
        expect(sent[0].notes).toBe('cache aside no feed');
        expect(autosave.chip).toBe('saved');
        expect(store.serverUpdatedAt).toBe(ACK_UPDATED_AT);
    });

    it('burst_of_changes_resets_both_timers', async () => {
        const { store, autosave, sent, cached } = harness();

        for (let keystroke = 1; keystroke <= 6; keystroke++) {
            change(store, autosave, 'tecla '.repeat(keystroke));

            await vi.advanceTimersByTimeAsync(700);
        }

        expect(cached()).toBeNull();
        expect(sent).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(LOCAL_SAVE_DELAY_MS);

        expect(cached()?.body.notes).toBe('tecla '.repeat(6));
        expect(sent).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(
            SERVER_SAVE_DELAY_MS - LOCAL_SAVE_DELAY_MS,
        );

        expect(sent).toHaveLength(1);
        expect(sent[0].notes).toBe('tecla '.repeat(6));
    });

    it('envia mesmo com o cronômetro sujando a sessão a cada segundo', async () => {
        const { store, autosave, sent } = harness();

        for (
            let second = 1;
            second <= SERVER_SAVE_MAX_WAIT_MS / 1000;
            second++
        ) {
            store.setElapsedSeconds(second);
            autosave.touch();

            await vi.advanceTimersByTimeAsync(1000);
        }

        expect(sent).toHaveLength(1);
        expect(sent[0].elapsed_seconds).toBe(SERVER_SAVE_MAX_WAIT_MS / 1000);
    });

    it('unload_triggers_a_final_local_save', async () => {
        const { store, autosave, cached } = harness();
        const listeners = new Map<string, () => void>();
        const target: UnloadTarget = {
            addEventListener: (type, listener) =>
                void listeners.set(type, listener),
            removeEventListener: (type) => void listeners.delete(type),
        };

        const unbind = autosave.bindUnload(target);

        change(store, autosave, 'fechou a aba antes da inércia');

        expect(cached()).toBeNull();

        listeners.get('beforeunload')?.();

        expect(cached()?.body.notes).toBe('fechou a aba antes da inércia');

        unbind();

        expect(listeners.size).toBe(0);
    });

    it('o botão Salvar envia sem esperar a inércia', async () => {
        const { store, autosave, sent } = harness();

        change(store, autosave, 'salvar agora');

        await autosave.save();

        expect(sent).toHaveLength(1);
        expect(autosave.chip).toBe('saved');
    });

    it('não envia nada quando não há alteração', async () => {
        const { autosave, sent } = harness();

        await autosave.save();

        expect(sent).toHaveLength(0);
        expect(autosave.chip).toBe('saved');
    });
});

describe('indicador', () => {
    it('vai para "não salvo" no instante da alteração, antes de qualquer temporizador', () => {
        const { store, autosave } = harness();

        expect(autosave.chip).toBe('saved');

        store.setNotes('primeira letra');

        expect(autosave.chip).toBe('dirty');
    });

    it('mostra "salvando…" enquanto o envio está no ar e "salvo" depois', async () => {
        const inFlight = deferred();
        const sent: SessionBody[] = [];
        const { store, autosave } = harness({
            send: async (_id, body) => {
                await inFlight.promise;

                sent.push(body);

                return { updated_at: ACK_UPDATED_AT };
            },
        });

        change(store, autosave, 'em voo');

        const saving = autosave.save();

        expect(autosave.chip).toBe('saving');

        inFlight.resolve();
        await saving;

        expect(autosave.chip).toBe('saved');
        expect(sent).toHaveLength(1);
    });

    it('volta a "não salvo" quando o payload muda durante o envio', async () => {
        const inFlight = deferred();
        const { store, autosave } = harness({
            send: async () => {
                await inFlight.promise;

                return { updated_at: ACK_UPDATED_AT };
            },
        });

        change(store, autosave, 'primeira frase');

        const saving = autosave.save();

        store.setNotes('primeira frase e a segunda');
        inFlight.resolve();
        await saving;

        expect(autosave.chip).toBe('dirty');
    });
});

describe('resiliência', () => {
    it('failed_save_keeps_dirty_and_schedules_retry', async () => {
        const sent: SessionBody[] = [];
        let attempts = 0;
        const { store, autosave, cached } = harness({
            send: async (_id, body) => {
                attempts++;

                if (attempts === 1) {
                    throw new Error('Network error');
                }

                sent.push(body);

                return { updated_at: ACK_UPDATED_AT };
            },
        });

        change(store, autosave, 'a rede caiu no meio do treino');

        await vi.advanceTimersByTimeAsync(SERVER_SAVE_DELAY_MS);

        expect(attempts).toBe(1);
        expect(autosave.chip).toBe('dirty');
        expect(store.isDirty).toBe(true);
        expect(store.notes).toBe('a rede caiu no meio do treino');
        expect(cached()?.body.notes).toBe('a rede caiu no meio do treino');

        await vi.advanceTimersByTimeAsync(RETRY_BASE_MS);

        expect(attempts).toBe(2);
        expect(sent).toHaveLength(1);
        expect(autosave.chip).toBe('saved');
    });

    it('dobra a espera a cada tentativa, até o teto', () => {
        expect(backoffDelay(1)).toBe(RETRY_BASE_MS);
        expect(backoffDelay(2)).toBe(RETRY_BASE_MS * 2);
        expect(backoffDelay(3)).toBe(RETRY_BASE_MS * 4);
        expect(backoffDelay(99)).toBe(RETRY_MAX_MS);
    });

    it('rate_limited_save_backs_off_and_retries', async () => {
        const sent: SessionBody[] = [];
        let attempts = 0;
        const { store, autosave } = harness({
            send: async (_id, body) => {
                attempts++;

                if (attempts === 1) {
                    throw new SaveRejected(429);
                }

                sent.push(body);

                return { updated_at: ACK_UPDATED_AT };
            },
        });

        change(store, autosave, 'muitas gravações seguidas');

        await vi.advanceTimersByTimeAsync(SERVER_SAVE_DELAY_MS);

        expect(attempts).toBe(1);
        expect(autosave.chip).toBe('dirty');
        expect(autosave.label).toBe(RATE_LIMITED_LABEL);

        await vi.advanceTimersByTimeAsync(RATE_LIMIT_DELAY_MS - 1);

        expect(attempts).toBe(1);

        await vi.advanceTimersByTimeAsync(1);

        expect(attempts).toBe(2);
        expect(sent[0].notes).toBe('muitas gravações seguidas');
        expect(autosave.chip).toBe('saved');
    });

    it('espera o que a resposta do rate limit pedir', async () => {
        let attempts = 0;
        const { store, autosave } = harness({
            send: async () => {
                attempts++;

                if (attempts === 1) {
                    throw new SaveRejected(429, 5000);
                }

                return { updated_at: ACK_UPDATED_AT };
            },
        });

        change(store, autosave, 'retry-after de cinco segundos');

        await vi.advanceTimersByTimeAsync(SERVER_SAVE_DELAY_MS);
        await vi.advanceTimersByTimeAsync(5000);

        expect(attempts).toBe(2);
        expect(autosave.chip).toBe('saved');
    });

    it('newer_server_version_warns_instead_of_overwriting', async () => {
        const onServerIsNewer = vi.fn();
        const { store, storage, autosave, sent } = harness({ onServerIsNewer });

        writeCache(storage, {
            version: SESSION_CACHE_VERSION,
            id: SESSION_ID,
            savedAt: Date.parse('2026-08-22T12:30:00.000000Z'),
            serverUpdatedAt: '2026-08-22T12:30:00.000000Z',
            body: bodyFrom(sessionRecordFixture({ notes: 'da outra aba' })),
        });

        change(store, autosave, 'desta aba');

        await vi.advanceTimersByTimeAsync(SERVER_SAVE_DELAY_MS);

        expect(sent).toHaveLength(0);
        expect(onServerIsNewer).toHaveBeenCalledTimes(1);
        expect(autosave.chip).toBe('dirty');
        expect(store.notes).toBe('desta aba');

        change(store, autosave, 'e continua desenhando');

        await vi.advanceTimersByTimeAsync(SERVER_SAVE_DELAY_MS);

        expect(sent).toHaveLength(0);
        expect(onServerIsNewer).toHaveBeenCalledTimes(1);
    });

    it('guarda no navegador o que o servidor recusou', async () => {
        const { store, storage, autosave } = harness({
            send: async () => {
                throw new SaveRejected(500);
            },
        });

        change(store, autosave, 'quinhentos do servidor');

        await vi.advanceTimersByTimeAsync(SERVER_SAVE_DELAY_MS);

        expect(storage.items.get(sessionCacheKey(SESSION_ID))).toContain(
            'quinhentos do servidor',
        );
    });
});
