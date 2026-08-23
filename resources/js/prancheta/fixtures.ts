import type { SessionDurationOption } from './clock';
import type { SessionStorage } from './resume';
import type { RoteiroPhase } from './roteiro';
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
 * As cinco fases do roteiro como o catálogo as entrega, com os 25 itens do
 * checklist. Pesos, textos e ids são os do `PhaseSeeder` e do
 * `ChecklistItemSeeder`, e `tests/Feature/Frontend/DrillClockTest.php` e
 * `DrillRoteiroTest.php` conferem fixture contra seeder — o roteiro é testado
 * contra o catálogo de verdade.
 */
export function phaseOptionsFixture(): RoteiroPhase[] {
    return [
        {
            id: 1,
            slug: 'requisitos-escopo',
            name: 'Requisitos & escopo',
            weight: 0.11,
            position: 1,
            checklist_items: [
                {
                    id: 1,
                    content:
                        'Requisitos funcionais listados — o que o sistema FAZ',
                },
                { id: 2, content: 'Fora de escopo declarado em voz alta' },
                {
                    id: 3,
                    content:
                        'Não-funcionais: latência, disponibilidade, consistência',
                },
                {
                    id: 4,
                    content: 'Perfil de uso: leitura pesada ou escrita pesada?',
                },
            ],
        },
        {
            id: 2,
            slug: 'estimativas-de-capacidade',
            name: 'Estimativas de capacidade',
            weight: 0.11,
            position: 2,
            checklist_items: [
                {
                    id: 5,
                    content: 'Usuários ativos por dia e ações por usuário',
                },
                { id: 6, content: 'QPS médio e QPS de pico' },
                {
                    id: 7,
                    content: 'Armazenamento por dia, por ano e na retenção',
                },
                { id: 8, content: 'Banda de entrada e de saída' },
                {
                    id: 9,
                    content:
                        'Conclusão: o que essa escala obriga (cache? shard? CDN?)',
                },
            ],
        },
        {
            id: 3,
            slug: 'api-modelo-de-dados',
            name: 'API & modelo de dados',
            weight: 0.18,
            position: 3,
            checklist_items: [
                { id: 10, content: 'Endpoints principais com entrada e saída' },
                { id: 11, content: 'Entidades e relacionamentos' },
                {
                    id: 12,
                    content: 'Escolha do banco justificada, não presumida',
                },
                { id: 13, content: 'Chave de particionamento definida' },
                {
                    id: 14,
                    content: 'Índices necessários para as consultas do produto',
                },
            ],
        },
        {
            id: 4,
            slug: 'desenho-de-alto-nivel',
            name: 'Desenho de alto nível',
            weight: 0.27,
            position: 4,
            checklist_items: [
                {
                    id: 15,
                    content: 'Caminho de ESCRITA desenhado ponta a ponta',
                },
                {
                    id: 16,
                    content: 'Caminho de LEITURA desenhado ponta a ponta',
                },
                {
                    id: 17,
                    content: 'Síncrono e assíncrono separados no desenho',
                },
                {
                    id: 18,
                    content: 'Onde entra cache e o que invalida cada entrada',
                },
                { id: 19, content: 'Fluxo narrado do clique até a resposta' },
            ],
        },
        {
            id: 5,
            slug: 'escala-trade-offs',
            name: 'Escala & trade-offs',
            weight: 0.33,
            position: 5,
            checklist_items: [
                { id: 20, content: 'Gargalo principal nomeado' },
                { id: 21, content: 'Estratégia de sharding e de replicação' },
                { id: 22, content: 'Nenhum ponto único de falha sobrou' },
                {
                    id: 23,
                    content: 'Consistência forte ou eventual — e o porquê',
                },
                { id: 24, content: 'Retry, idempotência e backpressure' },
                { id: 25, content: 'O que você mediria em produção' },
            ],
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
