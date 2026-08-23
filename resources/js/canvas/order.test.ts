import { describe, expect, it } from 'vitest';
import { edgeFixture } from './fixtures';
import { clearOrder, densify, numberedCount, setOrder } from './order';
import type { Edge } from './types';

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
