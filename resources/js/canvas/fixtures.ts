import type { CatalogCategory } from './catalog';
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
    ];
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
