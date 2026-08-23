import type { SessionStorage } from './resume';
import type { SessionRecord } from './session';

/**
 * Uma sessão como o servidor a entrega logo depois de criada, no formato do
 * store. Vive no pacote porque é ele que define o formato; quem consome são os
 * testes.
 */
export function sessionRecordFixture(
    overrides: Partial<SessionRecord> = {},
): SessionRecord {
    return {
        nodes: [],
        edges: [],
        seqMode: 'out',
        checks: {},
        notes: '',
        estimate: {
            mode: 'user',
            dau: 1000000,
            act: 10,
            per_month: 10000000,
            ratio: 100,
            size: 1,
            peak: 3,
            ret: 3,
        },
        elapsedSeconds: 0,
        durationMinutes: 45,
        ...overrides,
    };
}

export type MemoryStorage = SessionStorage & {
    items: Map<string, string>;
};

/** O `localStorage` do navegador, do tamanho do que o autosave usa. */
export function memoryStorage(): MemoryStorage {
    const items = new Map<string, string>();

    return {
        items,
        getItem: (key: string): string | null => items.get(key) ?? null,
        setItem: (key: string, value: string): void =>
            void items.set(key, value),
        removeItem: (key: string): void => void items.delete(key),
    };
}
