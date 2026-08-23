import type { CatalogCategory } from './catalog';
import type { LinkType } from './links';
import type { Edge, Node, SessionState } from './types';

/**
 * Um recorte do catálogo real, no formato em que o servidor o entrega. Vive no
 * pacote porque é o motor que define esse formato; quem consome são os testes.
 */
export function catalogFixture(): CatalogCategory[] {
    return [
        {
            slug: 'compute',
            name: 'Computação',
            color_token: '--c-compute',
            components: [
                {
                    slug: 'api',
                    name: 'API REST',
                    short_name: 'API REST',
                    icon_key: 'api',
                },
                {
                    slug: 'worker',
                    name: 'Worker',
                    short_name: 'Worker',
                    icon_key: 'worker',
                },
            ],
        },
        {
            slug: 'client',
            name: 'Cliente',
            color_token: '--c-client',
            components: [
                {
                    slug: 'browser',
                    name: 'Navegador',
                    short_name: 'Navegador',
                    icon_key: 'browser',
                },
            ],
        },
        {
            slug: 'edge',
            name: 'Rede & Borda',
            color_token: '--c-edge',
            components: [
                {
                    slug: 'cdn',
                    name: 'CDN',
                    short_name: 'CDN',
                    icon_key: 'cdn',
                },
            ],
        },
        {
            slug: 'data',
            name: 'Dados',
            color_token: '--c-data',
            components: [
                {
                    slug: 'sql',
                    name: 'Banco Relacional',
                    short_name: 'Banco Relacional',
                    icon_key: 'sql',
                },
            ],
        },
        {
            slug: 'async',
            name: 'Assíncrono',
            color_token: '--c-async',
            components: [
                {
                    slug: 'dlq',
                    name: 'DLQ — fila de falhas',
                    short_name: 'DLQ',
                    icon_key: 'dlq',
                },
            ],
        },
        {
            slug: 'ops',
            name: 'Operação',
            color_token: '--c-ops',
            components: [
                {
                    slug: 'monitor',
                    name: 'Monitoramento',
                    short_name: 'Monitoramento',
                    icon_key: 'monitor',
                },
            ],
        },
    ];
}

/**
 * Os nove tipos de ligação como o `LinkTypeSeeder` os grava. O selo e o
 * `dash_array` são os do catálogo real — mudá-los aqui é mudar o contrato.
 */
export function linkTypesFixture(): LinkType[] {
    return [
        ['http', 'HTTP / REST', 'HTTP', null, false],
        ['grpc', 'gRPC', 'gRPC', null, false],
        ['ws', 'WebSocket', 'WS', null, true],
        ['event', 'Evento — assíncrono', 'async', '5 4.5', false],
        ['query', 'Consulta ao banco', 'query', null, false],
        ['cache', 'Cache lookup', 'cache', null, false],
        ['repl', 'Replicação', 'replica', '5 4.5', false],
        ['batch', 'Lote / ETL', 'batch', '5 4.5', false],
        ['retry', 'Falha / Retry — DLQ', 'retry', '2 4.5', false],
    ].map(([slug, name, badge, dash, bidir], position) => ({
        id: position + 1,
        slug: slug as string,
        name: name as string,
        badge_label: badge as string,
        dash_array: dash as string | null,
        is_bidirectional_default: bidir as boolean,
        gloss: '',
    }));
}

export function nodeFixture(id: string, x = 0, y = 0, type = 'api'): Node {
    return { id, type, label: id, x, y };
}

export function edgeFixture(id: string, from: string, to: string): Edge {
    return { id, from, to, kind: null, label: '', dashed: false, bidir: false };
}

export function stateFixture(
    nodes: Node[] = [],
    edges: Edge[] = [],
): SessionState {
    return { nodes, edges, seqMode: 'out' };
}
