import { describe, expect, it } from 'vitest';
import { CanvasEngine } from './engine';
import {
    catalogFixture,
    edgeFixture,
    linkTypesFixture,
    nodeFixture,
    sequenceModesFixture,
    stateFixture,
} from './fixtures';
import type { Edge, Node, SequenceMode, SessionState } from './types';

function engineWith(state: SessionState): CanvasEngine {
    return new CanvasEngine(
        state,
        catalogFixture(),
        linkTypesFixture(),
        sequenceModesFixture(),
    );
}

function legendOf(
    nodes: Node[] = [],
    edges: Edge[] = [],
    mode: SequenceMode = 'out',
) {
    const state = stateFixture(nodes, edges);

    state.seqMode = mode;

    return engineWith(state).legendData();
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
        const edges = [
            typed('e1', 'a', 'b', 'http'),
            typed('e2', 'b', 'c', 'query'),
        ];

        expect(legendOf(nodes, edges, 'off').sequence).toBeNull();

        expect(legendOf(nodes, edges, 'flow').sequence).toEqual({
            mode: 'flow',
            name: 'Sequência do fluxo inteiro',
            text: 'os passos na ordem em que acontecem, começando pelo cliente',
        });

        // Uma saída por bloco: o modo `out` não numera nada, e a seção some.
        expect(legendOf(nodes, edges, 'out').sequence).toBeNull();

        expect(
            legendOf(nodes, [...edges, typed('e3', 'b', 'a', 'http')], 'out')
                .sequence,
        ).toEqual({
            mode: 'out',
            name: 'Ordem de saída de cada bloco',
            text: 'quando um bloco dispara mais de uma coisa, o número diz o que vem antes',
        });
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
