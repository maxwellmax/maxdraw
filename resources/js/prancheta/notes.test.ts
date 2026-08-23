import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { SessionTransport } from './autosave';
import {
    Autosave,
    LOCAL_SAVE_DELAY_MS,
    SERVER_SAVE_DELAY_MS,
} from './autosave';
import type { MemoryStorage } from './fixtures';
import { memoryStorage, sessionRecordFixture } from './fixtures';
import { acceptNotes, NOTES_MAX_LENGTH } from './notes';
import { readCache } from './resume';
import type { SessionBody } from './session';
import { SessionStore } from './session';

const SESSION_ID = 34;

const SERVER_UPDATED_AT = '2026-08-23T12:00:00.000000Z';

type Harness = {
    store: SessionStore;
    autosave: Autosave;
    storage: MemoryStorage;
    sent: SessionBody[];
};

function harness(): Harness {
    const store = new SessionStore(
        SESSION_ID,
        sessionRecordFixture(),
        SERVER_UPDATED_AT,
    );
    const storage = memoryStorage();
    const sent: SessionBody[] = [];
    const send: SessionTransport = async (_id: number, body: SessionBody) => {
        sent.push(body);

        return { updated_at: SERVER_UPDATED_AT };
    };

    return {
        store,
        autosave: new Autosave({ store, storage, send }),
        storage,
        sent,
    };
}

/** Digitar é uma alteração por tecla, e o watcher da tela chama `touch()`. */
function type(harness: Harness, text: string): void {
    for (let length = 1; length <= text.length; length++) {
        harness.store.setNotes(text.slice(0, length));
        harness.autosave.touch();
        vi.advanceTimersByTime(60);
    }
}

describe('notas da sessão', () => {
    beforeEach(() => vi.useFakeTimers());

    afterEach(() => vi.useRealTimers());

    it('typing_notes_marks_dirty_and_debounces', () => {
        const context = harness();

        type(context, 'particionar por user_id');

        expect(context.store.notes).toBe('particionar por user_id');
        expect(context.store.isDirty).toBe(true);
        expect(context.storage.items.size).toBe(0);
        expect(context.sent).toHaveLength(0);

        vi.advanceTimersByTime(LOCAL_SAVE_DELAY_MS);

        expect(readCache(context.storage, SESSION_ID)?.body.notes).toBe(
            'particionar por user_id',
        );
        expect(context.sent).toHaveLength(0);

        vi.advanceTimersByTime(SERVER_SAVE_DELAY_MS - LOCAL_SAVE_DELAY_MS);

        expect(context.sent).toHaveLength(1);
        expect(context.sent[0].notes).toBe('particionar por user_id');

        context.autosave.stop();
    });

    it('notes_survive_a_round_trip_through_the_payload', () => {
        const context = harness();

        context.store.setNotes('cache de leitura na borda');

        expect(context.store.body().notes).toBe('cache de leitura na borda');

        context.store.restore({
            ...context.store.body(),
            notes: 'o que o servidor devolveu',
        });

        expect(context.store.notes).toBe('o que o servidor devolveu');
    });

    it('notes_at_the_limit_are_accepted_untouched', () => {
        const accepted = acceptNotes('n'.repeat(NOTES_MAX_LENGTH));

        expect(accepted.notes).toHaveLength(NOTES_MAX_LENGTH);
        expect(accepted.blocked).toBe(false);
    });

    it('notes_past_the_limit_are_blocked_instead_of_silently_truncated', () => {
        const accepted = acceptNotes('n'.repeat(NOTES_MAX_LENGTH + 1));

        expect(accepted.notes).toHaveLength(NOTES_MAX_LENGTH);
        expect(accepted.blocked).toBe(true);
    });
});
