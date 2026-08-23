import { describe, expect, it } from 'vitest';
import { CanvasEngine } from './engine';
import {
    catalogFixture,
    edgeFixture,
    linkTypesFixture,
    nodeFixture,
    stateFixture,
} from './fixtures';
import { ORDER_GLOSS, ORDER_NAME } from './legend';
import type { Edge, Node, SessionState } from './types';

function engineWith(state: SessionState): CanvasEngine {
    return new CanvasEngine(state, catalogFixture(), linkTypesFixture());
}

function legendOf(
    nodes: Node[] = [],
    edges: Edge[] = [],
    showConnectionOrder = true,
) {
    return engineWith(
        stateFixture(nodes, edges, showConnectionOrder),
    ).legendData();
}

function typed(id: string, from: string, to: string, kind: string): Edge {
    return { ...edgeFixture(id, from, to), kind };
}

describe('legendData', () => {
    it('legendData_lists_only_present_categories_with_counts', () => {
        const legend = legendOf([
            nodeFixture('n1', 0, 0, 'sql'),
            nodeFixture('n2', 200, 0, 'api'),
            nodeFixture('n3', 400, 0, 'worker'),
            nodeFixture('n4', 600, 0, 'browser'),
        ]);

        expect(legend.categories).toEqual([
            {
                slug: 'compute',
                name: 'Computação',
                color: 'var(--c-compute)',
                count: 2,
            },
            {
                slug: 'client',
                name: 'Cliente',
                color: 'var(--c-client)',
                count: 1,
            },
            { slug: 'data', name: 'Dados', color: 'var(--c-data)', count: 1 },
        ]);
    });

    it('legendData_lists_only_used_link_types_in_catalog_order', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 200, 0, 'api'),
            nodeFixture('c', 400, 0, 'sql'),
        ];
        const legend = legendOf(nodes, [
            typed('e1', 'b', 'c', 'query'),
            typed('e2', 'a', 'b', 'http'),
            typed('e3', 'b', 'a', 'ws'),
            typed('e4', 'c', 'b', 'query'),
        ]);

        expect(legend.links.map((link) => link.slug)).toEqual([
            'http',
            'ws',
            'query',
        ]);

        expect(legend.links[0]).toEqual({
            slug: 'http',
            name: 'HTTP / REST',
            badge: 'HTTP',
            dash: null,
            bidir: false,
            gloss: 'requisição e resposta; o chamador fica esperando',
        });

        expect(legend.links[1]).toMatchObject({ badge: 'WS', bidir: true });
        expect(legend.untyped).toBe(false);
    });

    it('legendData_flags_untyped_edges', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 200, 0, 'api'),
        ];

        expect(legendOf(nodes, [typed('e1', 'a', 'b', 'http')]).untyped).toBe(
            false,
        );

        expect(legendOf(nodes, [edgeFixture('e1', 'a', 'b')]).untyped).toBe(
            true,
        );

        const both = legendOf(nodes, [
            typed('e1', 'a', 'b', 'http'),
            edgeFixture('e2', 'b', 'a'),
        ]);

        expect(both.untyped).toBe(true);
        expect(both.links.map((link) => link.slug)).toEqual(['http']);
    });

    it('legendData_flags_sequence_only_when_numbering_is_on', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 200, 0, 'api'),
            nodeFixture('c', 400, 0, 'sql'),
        ];
        const plain = [
            typed('e1', 'a', 'b', 'http'),
            typed('e2', 'b', 'c', 'query'),
        ];
        const numbered = [
            { ...plain[0], order: 1 },
            { ...plain[1], order: 2 },
        ];

        // Nenhuma aresta na sequência: não há número a explicar.
        expect(legendOf(nodes, plain).sequence).toBeNull();

        expect(legendOf(nodes, numbered).sequence).toEqual({
            name: ORDER_NAME,
            text: ORDER_GLOSS,
        });

        // Uma só basta para a seção existir.
        expect(legendOf(nodes, [plain[0], numbered[1]]).sequence).toEqual({
            name: ORDER_NAME,
            text: ORDER_GLOSS,
        });

        // Bandeira apagada: a seção some, e os números seguem no estado.
        const hidden = stateFixture(nodes, numbered, false);

        expect(engineWith(hidden).legendData().sequence).toBeNull();
        expect(hidden.edges.map((edge) => edge.order)).toEqual([1, 2]);
    });

    it('legend_sequence_has_no_mode_and_no_catalog_text', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 200, 0, 'api'),
        ];
        const sequence = legendOf(nodes, [
            { ...typed('e1', 'a', 'b', 'http'), order: 1 },
        ]).sequence;

        expect(Object.keys(sequence!)).toEqual(['name', 'text']);
        expect(sequence).not.toHaveProperty('mode');
        expect(sequence?.text).toBe(ORDER_GLOSS);
    });

    it('sequence_section_ignores_orders_of_edges_that_are_not_drawn', () => {
        const nodes = [nodeFixture('a', 0, 0, 'browser')];

        // A órfã guarda o número dela, mas não é desenhada: nada a explicar.
        expect(
            legendOf(nodes, [
                { ...edgeFixture('órfã', 'a', 'sumiu'), order: 1 },
            ]).sequence,
        ).toBeNull();
    });

    it('legendData_is_empty_for_empty_diagram', () => {
        expect(legendOf()).toEqual({
            categories: [],
            links: [],
            untyped: false,
            sequence: null,
            empty: true,
        });
    });

    it('dangling_edges_do_not_appear_in_legend', () => {
        const nodes = [
            nodeFixture('a', 0, 0, 'browser'),
            nodeFixture('b', 200, 0, 'api'),
        ];
        const legend = legendOf(nodes, [
            typed('e1', 'a', 'b', 'http'),
            typed('órfã', 'a', 'sumiu', 'retry'),
            typed('laço', 'b', 'b', 'batch'),
            edgeFixture('semTipoÓrfã', 'foi', 'b'),
        ]);

        expect(legend.links.map((link) => link.slug)).toEqual(['http']);
        expect(legend.untyped).toBe(false);
        expect(legend.empty).toBe(false);
    });

    it('esconde a legenda inteira quando não há nada desenhado', () => {
        expect(legendOf().empty).toBe(true);
        expect(legendOf([nodeFixture('a', 0, 0, 'browser')]).empty).toBe(false);
    });
});
