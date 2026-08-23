import { describe, expect, it } from 'vitest';
import { indexComponents } from './catalog';
import { CanvasEngine } from './engine';
import {
    catalogFixture,
    edgeFixture,
    linkTypesFixture,
    nodeFixture,
    stateFixture,
} from './fixtures';
import type { SequenceMap } from './sequence';
import { flowSeq, outSeq, seqMap, sequenceModeOf } from './sequence';
import type { Edge, Node, SessionState } from './types';

const COMPONENTS = indexComponents(catalogFixture());

function engineWith(state: SessionState): CanvasEngine {
    return new CanvasEngine(state, catalogFixture(), linkTypesFixture());
}

/** Os ids na ordem em que a numeração os visita. */
function order(numbers: SequenceMap): string[] {
    return Object.entries(numbers)
        .sort(([, a], [, b]) => a.index - b.index)
        .map(([id]) => id);
}

/** Um cliente e três serviços, para o fluxo ter por onde começar. */
function flowNodes(): Node[] {
    return [
        nodeFixture('api', 200, 0, 'api'),
        nodeFixture('sql', 400, 0, 'sql'),
        nodeFixture('browser', 0, 0, 'browser'),
        nodeFixture('cdn', 200, 200, 'cdn'),
    ];
}

describe('outSeq', () => {
    it('outSeq_skips_blocks_with_a_single_output', () => {
        const nodes = [
            nodeFixture('a'),
            nodeFixture('b', 400),
            nodeFixture('c', 800),
        ];
        const edges = [
            edgeFixture('só', 'a', 'b'),
            edgeFixture('e1', 'b', 'a'),
            edgeFixture('e2', 'b', 'c'),
        ];

        expect(Object.keys(outSeq(edges, nodes))).toEqual(['e1', 'e2']);
    });

    it('outSeq_numbers_by_array_order', () => {
        const nodes = [
            nodeFixture('a'),
            nodeFixture('b', 400),
            nodeFixture('c', 800),
        ];
        const edges = [
            edgeFixture('e2', 'a', 'c'),
            edgeFixture('e1', 'a', 'b'),
        ];

        expect(outSeq(edges, nodes)).toEqual({
            e2: { index: 1, total: 2 },
            e1: { index: 2, total: 2 },
        });

        edges.reverse();

        expect(outSeq(edges, nodes)).toEqual({
            e1: { index: 1, total: 2 },
            e2: { index: 2, total: 2 },
        });
    });

    it('outSeq_ignores_dangling_edges', () => {
        const nodes = [
            nodeFixture('a'),
            nodeFixture('b', 400),
            nodeFixture('c', 800),
        ];
        const edges = [
            edgeFixture('laço', 'a', 'a'),
            edgeFixture('e1', 'a', 'b'),
            edgeFixture('órfã', 'a', 'apagado'),
            edgeFixture('e2', 'a', 'c'),
        ];

        expect(outSeq(edges, nodes)).toEqual({
            e1: { index: 1, total: 2 },
            e2: { index: 2, total: 2 },
        });
    });

    it('outSeq_reports_correct_total_per_block', () => {
        const nodes = [
            nodeFixture('a'),
            nodeFixture('b', 400),
            nodeFixture('c', 800),
            nodeFixture('d', 1200),
        ];
        const edges = [
            edgeFixture('a1', 'a', 'b'),
            edgeFixture('b1', 'b', 'c'),
            edgeFixture('a2', 'a', 'c'),
            edgeFixture('b2', 'b', 'd'),
            edgeFixture('a3', 'a', 'd'),
            edgeFixture('c1', 'c', 'd'),
        ];

        expect(outSeq(edges, nodes)).toEqual({
            a1: { index: 1, total: 3 },
            b1: { index: 1, total: 2 },
            a2: { index: 2, total: 3 },
            b2: { index: 2, total: 2 },
            a3: { index: 3, total: 3 },
        });
    });
});

describe('flowSeq', () => {
    it('flowSeq_starts_from_client_nodes_without_inputs', () => {
        const edges = [
            edgeFixture('api-sql', 'api', 'sql'),
            edgeFixture('cdn-api', 'cdn', 'api'),
            edgeFixture('browser-cdn', 'browser', 'cdn'),
        ];

        expect(order(flowSeq(edges, flowNodes(), COMPONENTS))).toEqual([
            'browser-cdn',
            'cdn-api',
            'api-sql',
        ]);
    });

    it('flowSeq_follows_output_order_depth_first', () => {
        const nodes = [
            nodeFixture('browser', 0, 0, 'browser'),
            nodeFixture('api', 200, 0, 'api'),
            nodeFixture('sql', 400, 0, 'sql'),
            nodeFixture('cdn', 200, 200, 'cdn'),
            nodeFixture('dlq', 400, 200, 'dlq'),
        ];
        const edges = [
            edgeFixture('browser-api', 'browser', 'api'),
            edgeFixture('browser-cdn', 'browser', 'cdn'),
            edgeFixture('api-sql', 'api', 'sql'),
            edgeFixture('cdn-dlq', 'cdn', 'dlq'),
        ];

        expect(order(flowSeq(edges, nodes, COMPONENTS))).toEqual([
            'browser-api',
            'api-sql',
            'browser-cdn',
            'cdn-dlq',
        ]);
    });

    it('flowSeq_terminates_on_cyclic_graphs', () => {
        const nodes = [
            nodeFixture('browser', 0, 0, 'browser'),
            nodeFixture('api', 200, 0, 'api'),
            nodeFixture('sql', 400, 0, 'sql'),
        ];
        const edges = [
            edgeFixture('browser-api', 'browser', 'api'),
            edgeFixture('api-sql', 'api', 'sql'),
            edgeFixture('sql-api', 'sql', 'api'),
        ];

        const numbers = flowSeq(edges, nodes, COMPONENTS);

        expect(order(numbers)).toEqual(['browser-api', 'api-sql', 'sql-api']);
        expect(numbers['sql-api'].total).toBe(3);
    });

    it('flowSeq_covers_orphan_components_at_the_end', () => {
        const nodes = [
            ...flowNodes(),
            nodeFixture('worker', 0, 400, 'worker'),
            nodeFixture('monitor', 200, 400, 'monitor'),
        ];
        const edges = [
            edgeFixture('worker-monitor', 'worker', 'monitor'),
            edgeFixture('browser-api', 'browser', 'api'),
            edgeFixture('api-sql', 'api', 'sql'),
        ];

        expect(order(flowSeq(edges, nodes, COMPONENTS))).toEqual([
            'browser-api',
            'api-sql',
            'worker-monitor',
        ]);
    });

    it('flowSeq_numbers_every_live_edge_exactly_once', () => {
        const nodes = [
            ...flowNodes(),
            nodeFixture('dlq', 600, 200, 'dlq'),
            nodeFixture('worker', 600, 400, 'worker'),
        ];
        const edges = [
            edgeFixture('e1', 'browser', 'api'),
            edgeFixture('e2', 'api', 'sql'),
            edgeFixture('e3', 'sql', 'api'),
            edgeFixture('e4', 'api', 'dlq'),
            edgeFixture('e5', 'worker', 'dlq'),
            edgeFixture('e6', 'dlq', 'worker'),
            edgeFixture('laço', 'cdn', 'cdn'),
            edgeFixture('órfã', 'api', 'apagado'),
        ];

        const numbers = flowSeq(edges, nodes, COMPONENTS);

        expect(Object.keys(numbers).sort()).toEqual([
            'e1',
            'e2',
            'e3',
            'e4',
            'e5',
            'e6',
        ]);
        expect(order(numbers).map((_, position) => position + 1)).toEqual([
            1, 2, 3, 4, 5, 6,
        ]);
        expect(Object.values(numbers).map((number) => number.total)).toEqual([
            6, 6, 6, 6, 6, 6,
        ]);
    });
});

describe('seqMap', () => {
    const nodes = [
        nodeFixture('browser', 0, 0, 'browser'),
        nodeFixture('api', 200, 0, 'api'),
        nodeFixture('sql', 400, 0, 'sql'),
    ];
    const edges = [
        edgeFixture('browser-api', 'browser', 'api'),
        edgeFixture('api-sql', 'api', 'sql'),
    ];

    it('seqMap_returns_empty_when_off', () => {
        expect(seqMap('off', edges, nodes, COMPONENTS)).toEqual({});
        expect(seqMap('flow', edges, nodes, COMPONENTS)).toEqual(
            flowSeq(edges, nodes, COMPONENTS),
        );
        expect(seqMap('out', edges, nodes, COMPONENTS)).toEqual(
            outSeq(edges, nodes),
        );
    });

    it('invalid_mode_normalizes_to_out', () => {
        const expected = outSeq(edges, nodes);

        for (const mode of ['', 'seq', 'OUT', null, undefined]) {
            expect(sequenceModeOf(mode)).toBe('out');
            expect(seqMap(mode, edges, nodes, COMPONENTS)).toEqual(expected);
        }

        expect(sequenceModeOf('off')).toBe('off');
        expect(sequenceModeOf('flow')).toBe('flow');

        const engine = engineWith({
            ...stateFixture(nodes, edges),
            seqMode: 'inventado' as never,
        });

        expect(engine.seqMode).toBe('out');
        expect(engine.seqMap()).toEqual(expected);
    });
});

describe('moveSeq', () => {
    /** Duas saídas de `a` com a saída de outro bloco no meio do array. */
    function branched(): { engine: CanvasEngine; ids: () => string[] } {
        const engine = engineWith(
            stateFixture(
                [
                    nodeFixture('a'),
                    nodeFixture('b', 400),
                    nodeFixture('c', 800),
                    nodeFixture('d', 1200),
                ],
                [
                    edgeFixture('a-b', 'a', 'b'),
                    edgeFixture('d-c', 'd', 'c'),
                    edgeFixture('a-c', 'a', 'c'),
                ],
            ),
        );

        return { engine, ids: () => engine.edges.map((edge: Edge) => edge.id) };
    }

    it('moveSeq_swaps_with_sibling_output_only', () => {
        const { engine, ids } = branched();
        const depth = engine.undoDepth;

        expect(engine.moveSeq('a-c', -1)).toBe(true);
        expect(ids()).toEqual(['a-c', 'd-c', 'a-b']);
        expect(engine.undoDepth).toBe(depth + 1);
        expect(engine.outSeq()).toEqual({
            'a-c': { index: 1, total: 2 },
            'a-b': { index: 2, total: 2 },
        });

        engine.undo();

        expect(ids()).toEqual(['a-b', 'd-c', 'a-c']);
    });

    it('moveSeq_refuses_at_both_ends', () => {
        const { engine, ids } = branched();
        const depth = engine.undoDepth;

        expect(engine.moveSeq('a-b', -1)).toBe(false);
        expect(engine.moveSeq('a-c', 1)).toBe(false);
        expect(engine.moveSeq('d-c', -1)).toBe(false);
        expect(engine.moveSeq('d-c', 1)).toBe(false);
        expect(engine.moveSeq('sumiu', -1)).toBe(false);
        expect(ids()).toEqual(['a-b', 'd-c', 'a-c']);
        expect(engine.undoDepth).toBe(depth);
    });

    it('reordering_changes_flow_traversal_order', () => {
        const engine = engineWith(
            stateFixture(
                [
                    nodeFixture('browser', 0, 0, 'browser'),
                    nodeFixture('api', 200, 0, 'api'),
                    nodeFixture('sql', 400, 0, 'sql'),
                    nodeFixture('cdn', 200, 200, 'cdn'),
                    nodeFixture('dlq', 400, 200, 'dlq'),
                ],
                [
                    edgeFixture('browser-api', 'browser', 'api'),
                    edgeFixture('browser-cdn', 'browser', 'cdn'),
                    edgeFixture('api-sql', 'api', 'sql'),
                    edgeFixture('cdn-dlq', 'cdn', 'dlq'),
                ],
            ),
        );

        engine.setSequenceMode('flow');

        expect(order(engine.seqMap())).toEqual([
            'browser-api',
            'api-sql',
            'browser-cdn',
            'cdn-dlq',
        ]);

        expect(engine.moveSeq('browser-cdn', -1)).toBe(true);

        expect(order(engine.seqMap())).toEqual([
            'browser-cdn',
            'cdn-dlq',
            'browser-api',
            'api-sql',
        ]);

        engine.setSequenceMode('out');

        expect(engine.seqMap()).toEqual({
            'browser-cdn': { index: 1, total: 2 },
            'browser-api': { index: 2, total: 2 },
        });
    });
});
