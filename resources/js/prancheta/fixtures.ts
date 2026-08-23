import type { PhaseOption, SessionDurationOption } from './clock';
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

/**
 * As cinco fases do roteiro como o catálogo as entrega. Os pesos são os do
 * `PhaseSeeder`, e `tests/Feature/Frontend/DrillClockTest.php` confere fixture
 * contra seeder — o cronômetro é testado contra o catálogo de verdade.
 */
export function phaseOptionsFixture(): PhaseOption[] {
    return [
        {
            id: 1,
            slug: 'requisitos-escopo',
            name: 'Requisitos & escopo',
            weight: 0.11,
            position: 1,
        },
        {
            id: 2,
            slug: 'estimativas-de-capacidade',
            name: 'Estimativas de capacidade',
            weight: 0.11,
            position: 2,
        },
        {
            id: 3,
            slug: 'api-modelo-de-dados',
            name: 'API & modelo de dados',
            weight: 0.18,
            position: 3,
        },
        {
            id: 4,
            slug: 'desenho-de-alto-nivel',
            name: 'Desenho de alto nível',
            weight: 0.27,
            position: 4,
        },
        {
            id: 5,
            slug: 'escala-trade-offs',
            name: 'Escala & trade-offs',
            weight: 0.33,
            position: 5,
        },
    ];
}

/** As três durações do catálogo, com a padrão marcada. */
export function sessionDurationOptionsFixture(): SessionDurationOption[] {
    return [
        { id: 1, minutes: 30, is_default: false },
        { id: 2, minutes: 45, is_default: true },
        { id: 3, minutes: 60, is_default: false },
    ];
}
