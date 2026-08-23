import { describe, expect, it } from 'vitest';
import { indexComponents } from './catalog';
import { catalogFixture, edgeFixture, nodeFixture } from './fixtures';
import { MAX_EDGES, MAX_NODES } from './limits';
import {
    autoNumber,
    clampOrderInput,
    clearOrder,
    densify,
    numberedCount,
    setOrder,
} from './order';
import type { Edge, Node } from './types';

/** Três arestas numeradas na sequência, na ordem `A=1, B=2, C=3`. */
function sequence(): Edge[] {
    return [
        edgeFixture('a', 'n1', 'n2', 1),
        edgeFixture('b', 'n2', 'n3', 2),
        edgeFixture('c', 'n3', 'n4', 3),
    ];
}

/** O mapa `id → order`, que é o que toda asserção desta suíte compara. */
function orders(edges: readonly Edge[]): Record<string, number | null> {
    return Object.fromEntries(edges.map((edge) => [edge.id, edge.order]));
}

function numbers(edges: readonly Edge[]): number[] {
    return edges.flatMap((edge) => (edge.order === null ? [] : [edge.order]));
}

/** A invariante: os números são exatamente `1..N`, sem buraco e sem repetido. */
function isDense(edges: readonly Edge[]): boolean {
    const sorted = [...numbers(edges)].sort((a, b) => a - b);

    return sorted.every((value, index) => value === index + 1);
}

describe('densify', () => {
    it('densify_rewrites_sparse_orders_to_1_to_N', () => {
        const edges = [
            edgeFixture('a', 'n1', 'n2', 1),
            edgeFixture('b', 'n2', 'n3', 3),
            edgeFixture('c', 'n3', 'n4', 7),
        ];

        expect(densify(edges)).toBe(true);
        expect(orders(edges)).toEqual({ a: 1, b: 2, c: 3 });
        expect(densify(edges)).toBe(false);
    });

    it('densify_breaks_ties_by_array_position', () => {
        const edges = [
            edgeFixture('a', 'n1', 'n2', 2),
            edgeFixture('b', 'n2', 'n3', 2),
            edgeFixture('c', 'n3', 'n4', 1),
        ];

        densify(edges);

        expect(orders(edges)).toEqual({ a: 2, b: 3, c: 1 });
    });

    it('densify_includes_orphan_edges', () => {
        const edges = [
            edgeFixture('viva', 'n1', 'n2', 4),
            edgeFixture('orfa', 'sumiu', 'n2', 2),
        ];

        densify(edges);

        expect(orders(edges)).toEqual({ orfa: 1, viva: 2 });
        expect(numberedCount(edges)).toBe(2);
    });

    it('null_orders_do_not_count_toward_N', () => {
        const edges = [
            edgeFixture('a', 'n1', 'n2', 1),
            ...Array.from({ length: 5 }, (_, index) =>
                edgeFixture(`livre${index}`, 'n2', `n${index + 3}`),
            ),
            edgeFixture('b', 'n2', 'n3', 2),
        ];

        expect(numberedCount(edges)).toBe(2);
        expect(densify(edges)).toBe(false);

        const rest = edges.filter((edge) => edge.id !== 'livre2');

        densify(rest);

        expect(numbers(rest)).toEqual([1, 2]);
        expect(numberedCount(rest)).toBe(2);
    });
});

describe('setOrder', () => {
    it('setOrder_pushes_later_edges_forward', () => {
        const edges = sequence();

        expect(setOrder(edges, 'c', 1)).toBe(true);
        expect(orders(edges)).toEqual({ c: 1, a: 2, b: 3 });
        expect(isDense(edges)).toBe(true);
    });

    it('setOrder_moving_forward_lands_at_the_requested_slot', () => {
        const edges = sequence();

        expect(setOrder(edges, 'a', 3)).toBe(true);
        expect(orders(edges)).toEqual({ b: 1, c: 2, a: 3 });
        expect(isDense(edges)).toBe(true);
    });

    it('setOrder_numbers_an_edge_that_was_outside_the_sequence', () => {
        const edges = [...sequence(), edgeFixture('d', 'n4', 'n5')];

        expect(setOrder(edges, 'd', 2)).toBe(true);
        expect(orders(edges)).toEqual({ a: 1, d: 2, b: 3, c: 4 });
        expect(numberedCount(edges)).toBe(4);
    });

    it('setOrder_clamps_out_of_range_values_to_the_nearest_end', () => {
        const edges = sequence();

        setOrder(edges, 'a', 999);

        expect(orders(edges)).toEqual({ b: 1, c: 2, a: 3 });

        setOrder(edges, 'a', -5);

        expect(orders(edges)).toEqual({ a: 1, b: 2, c: 3 });
    });

    it('setOrder_refuses_an_unknown_edge_or_a_value_that_is_not_a_number', () => {
        const edges = sequence();

        expect(setOrder(edges, 'sumiu', 1)).toBe(false);
        expect(setOrder(edges, 'a', Number.NaN)).toBe(false);
        expect(orders(edges)).toEqual({ a: 1, b: 2, c: 3 });
    });
});

describe('clearOrder', () => {
    it('clearOrder_removes_from_the_sequence_and_densifies', () => {
        const edges = sequence();

        expect(clearOrder(edges, 'b')).toBe(true);
        expect(orders(edges)).toEqual({ a: 1, b: null, c: 2 });
        expect(numberedCount(edges)).toBe(2);
    });

    it('clearOrder_is_a_no_op_on_an_edge_already_outside_the_sequence', () => {
        const edges = [...sequence(), edgeFixture('d', 'n4', 'n5')];

        expect(clearOrder(edges, 'd')).toBe(false);
        expect(orders(edges)).toEqual({ a: 1, b: 2, c: 3, d: null });
    });
});

describe('invariante', () => {
    it('no_operation_sequence_produces_a_duplicate_or_a_hole', () => {
        const edges = Array.from({ length: 8 }, (_, index) =>
            edgeFixture(`e${index}`, `n${index}`, `n${index + 1}`),
        );

        const ids = edges.map((edge) => edge.id);

        for (const [step, id] of ids.entries()) {
            setOrder(edges, id, (step % 4) + 1);

            expect(isDense(edges)).toBe(true);
        }

        for (const [step, id] of ids.entries()) {
            if (step % 3 === 0) {
                clearOrder(edges, id);
            } else {
                setOrder(edges, id, ids.length - step);
            }

            expect(isDense(edges)).toBe(true);
        }

        expect(new Set(numbers(edges)).size).toBe(numberedCount(edges));
    });
});

describe('autoNumber', () => {
    const index = indexComponents(catalogFixture());

    /** Um diagrama com cliente, leque, ciclo e uma órfã — o caso de sempre. */
    function tangled(): { nodes: Node[]; edges: Edge[] } {
        return {
            nodes: [
                nodeFixture('api', 400, 0, 'api'),
                nodeFixture('cdn', 200, 0, 'cdn'),
                nodeFixture('cli', 0, 0, 'browser'),
                nodeFixture('sql', 800, 0, 'sql'),
                nodeFixture('mq', 800, 200, 'dlq'),
                nodeFixture('wrk', 1200, 200, 'worker'),
            ],
            edges: [
                edgeFixture('a', 'api', 'sql', 4),
                edgeFixture('b', 'cli', 'cdn'),
                edgeFixture('c', 'cdn', 'api', 1),
                edgeFixture('d', 'api', 'mq'),
                edgeFixture('e', 'mq', 'wrk', 9),
                edgeFixture('f', 'wrk', 'api'),
                edgeFixture('g', 'sumiu', 'api', 2),
            ],
        };
    }

    /** O diagrama no limite de payload: 200 blocos e 400 ligações, sem laço. */
    function payloadLimit(): { nodes: Node[]; edges: Edge[] } {
        const nodes = Array.from({ length: MAX_NODES }, (_, position) =>
            nodeFixture(
                `n${position}`,
                (position % 20) * 200,
                Math.floor(position / 20) * 140,
                position === 0 ? 'browser' : 'api',
            ),
        );

        const edges = Array.from({ length: MAX_EDGES }, (_, position) =>
            edgeFixture(
                `e${position}`,
                `n${position % MAX_NODES}`,
                `n${(position * 7 + 13) % MAX_NODES}`,
                position % 3 === 0 ? null : MAX_EDGES - position,
            ),
        );

        return { nodes, edges };
    }

    it('autoNumber_numbers_every_live_edge_exactly_once', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 400, 0, 'api'),
            nodeFixture('c', 800, 0, 'sql'),
        ];
        const edges = [
            edgeFixture('e1', 'a', 'b', 9),
            edgeFixture('e2', 'b', 'c'),
            edgeFixture('e3', 'a', 'c', 3),
        ];

        expect(autoNumber(edges, nodes, index)).toBe(true);
        expect(orders(edges)).toEqual({ e1: 1, e3: 2, e2: 3 });
        expect(numberedCount(edges)).toBe(3);
        expect(isDense(edges)).toBe(true);
        expect(autoNumber(edges, nodes, index)).toBe(false);
    });

    it('autoNumber_starts_from_client_nodes_without_inputs', () => {
        const nodes = [
            nodeFixture('d', 0, 200, 'cdn'),
            nodeFixture('a', 400, 0, 'api'),
            nodeFixture('cli', 0, 0, 'browser'),
            nodeFixture('b', 800, 0, 'api'),
            nodeFixture('c', 1200, 0, 'sql'),
        ];
        const edges = [
            edgeFixture('e0', 'd', 'c'),
            edgeFixture('e1', 'a', 'b'),
            edgeFixture('e2', 'cli', 'a'),
            edgeFixture('e3', 'b', 'c'),
        ];

        autoNumber(edges, nodes, index);

        expect(orders(edges)).toEqual({ e2: 1, e1: 2, e3: 3, e0: 4 });
    });

    it('autoNumber_walks_breadth_first_from_each_root', () => {
        const nodes = [
            nodeFixture('r', 0, 0, 'browser'),
            nodeFixture('x', 400, 0, 'api'),
            nodeFixture('y', 400, 200, 'api'),
            nodeFixture('x2', 800, 0, 'sql'),
            nodeFixture('y2', 800, 200, 'sql'),
        ];
        const edges = [
            edgeFixture('rx', 'r', 'x'),
            edgeFixture('ry', 'r', 'y'),
            edgeFixture('xx', 'x', 'x2'),
            edgeFixture('yy', 'y', 'y2'),
        ];

        autoNumber(edges, nodes, index);

        expect(orders(edges)).toEqual({ rx: 1, ry: 2, xx: 3, yy: 4 });
    });

    it('autoNumber_terminates_on_cyclic_graphs', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'api'),
            nodeFixture('b', 400, 0, 'api'),
            nodeFixture('c', 800, 0, 'api'),
        ];
        const edges = [
            edgeFixture('e1', 'a', 'b'),
            edgeFixture('e2', 'b', 'c'),
            edgeFixture('e3', 'c', 'a'),
        ];

        autoNumber(edges, nodes, index);

        expect(orders(edges)).toEqual({ e1: 1, e2: 2, e3: 3 });
        expect(isDense(edges)).toBe(true);
    });

    it('autoNumber_gives_a_bidir_edge_a_single_order_and_keeps_its_source_as_root', () => {
        const nodes = [
            nodeFixture('srv', 400, 0, 'api'),
            nodeFixture('db', 800, 0, 'sql'),
            nodeFixture('ws', 0, 0, 'browser'),
        ];
        const edges = [
            edgeFixture('e2', 'srv', 'db'),
            { ...edgeFixture('e1', 'ws', 'srv'), bidir: true },
        ];

        autoNumber(edges, nodes, index);

        expect(orders(edges)).toEqual({ e1: 1, e2: 2 });
        expect(numberedCount(edges)).toBe(2);
    });

    it('autoNumber_ranks_a_numbered_orphan_after_every_live_edge', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 400, 0, 'api'),
            nodeFixture('c', 800, 0, 'sql'),
            nodeFixture('d', 1200, 0, 'worker'),
        ];
        const edges = [
            edgeFixture('e1', 'a', 'b'),
            edgeFixture('e2', 'b', 'c'),
            edgeFixture('e3', 'c', 'd'),
            edgeFixture('orfa', 'sumiu', 'b', 2),
        ];

        autoNumber(edges, nodes, index);

        expect(orders(edges)).toEqual({ e1: 1, e2: 2, e3: 3, orfa: 4 });
        expect(numberedCount(edges)).toBe(4);
        expect(isDense(edges)).toBe(true);
    });

    it('autoNumber_keeps_the_relative_order_of_numbered_orphans', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 400, 0, 'api'),
            nodeFixture('c', 800, 0, 'sql'),
            nodeFixture('d', 1200, 0, 'worker'),
        ];
        const edges = [
            edgeFixture('e1', 'a', 'b'),
            edgeFixture('e2', 'b', 'c'),
            edgeFixture('e3', 'c', 'd'),
            edgeFixture('orfaA', 'sumiu', 'b', 5),
            edgeFixture('orfaB', 'sumiu', 'c', 2),
            edgeFixture('orfaC', 'sumiu', 'd'),
        ];

        autoNumber(edges, nodes, index);

        expect(orders(edges)).toEqual({
            e1: 1,
            e2: 2,
            e3: 3,
            orfaB: 4,
            orfaA: 5,
            orfaC: null,
        });
        expect(numberedCount(edges)).toBe(5);
        expect(isDense(edges)).toBe(true);
    });

    it('autoNumber_is_deterministic_over_50_consecutive_runs', () => {
        const { nodes, edges } = tangled();

        autoNumber(edges, nodes, index);

        const baseline = JSON.stringify(orders(edges));
        const divergences: number[] = [];

        for (let run = 0; run < 50; run++) {
            const fresh = tangled();

            autoNumber(fresh.edges, fresh.nodes, index);
            autoNumber(edges, nodes, index);

            if (
                JSON.stringify(orders(fresh.edges)) !== baseline ||
                JSON.stringify(orders(edges)) !== baseline
            ) {
                divergences.push(run);
            }
        }

        expect(divergences).toEqual([]);
        expect(orders(edges)).toEqual({
            b: 1,
            c: 2,
            a: 3,
            d: 4,
            e: 5,
            f: 6,
            g: 7,
        });
    });

    it('autoNumber_renumbers_the_payload_limit_under_16ms', () => {
        const { nodes, edges } = payloadLimit();

        expect(nodes).toHaveLength(MAX_NODES);
        expect(edges).toHaveLength(MAX_EDGES);

        const samples = Array.from({ length: 20 }, () => {
            const started = performance.now();

            densify(edges);
            autoNumber(edges, nodes, index);

            return performance.now() - started;
        });

        const sorted = [...samples].sort((a, b) => a - b);
        const median = (sorted[9] + sorted[10]) / 2;

        expect(numberedCount(edges)).toBe(MAX_EDGES);
        expect(isDense(edges)).toBe(true);
        expect(median).toBeLessThan(16);
    });
});

describe('clampOrderInput', () => {
    it('clampOrderInput_caps_a_numbered_edge_at_N', () => {
        expect(clampOrderInput('999', 3, true)).toBe(3);
        expect(clampOrderInput(2, 3, true)).toBe(2);
    });

    it('clampOrderInput_caps_an_unnumbered_edge_at_N_plus_1', () => {
        expect(clampOrderInput('999', 3, false)).toBe(4);
        expect(clampOrderInput('1', 0, false)).toBe(1);
        expect(clampOrderInput('9', 0, false)).toBe(1);
    });

    it('clampOrderInput_treats_an_empty_field_as_a_no_op', () => {
        const edges = sequence();

        for (const empty of ['', '   ', 'abc']) {
            expect(clampOrderInput(empty, 3, true)).toBeNull();
        }

        expect(orders(edges)).toEqual({ a: 1, b: 2, c: 3 });
    });

    it('clampOrderInput_pulls_zero_and_negatives_up_to_the_first_slot', () => {
        expect(clampOrderInput('0', 3, true)).toBe(1);
        expect(clampOrderInput('-7', 3, true)).toBe(1);
        expect(clampOrderInput('2.9', 3, true)).toBe(2);
    });
});
