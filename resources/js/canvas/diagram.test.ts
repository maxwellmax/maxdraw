import { describe, expect, it } from 'vitest';
import type { CatalogComponent } from './catalog';
import {
    addEdge,
    addNode,
    diagramBounds,
    moveNode,
    removeEdge,
    removeNode,
    renameNode,
    restore,
    snapshot,
} from './diagram';
import {
    catalogFixture,
    edgeFixture,
    nodeFixture,
    stateFixture,
} from './fixtures';
import { normalizeLabel } from './labels';
import { MAX_EDGES, MAX_LABEL_LENGTH, MAX_NODES } from './limits';

function componentBySlug(slug: string): CatalogComponent {
    return catalogFixture()
        .flatMap((category) => category.components)
        .find((component) => component.slug === slug)!;
}

describe('addNode', () => {
    it('new_node_label_defaults_to_component_short_name', () => {
        const state = stateFixture();
        const result = addNode(state, componentBySlug('dlq'), [400, 300]);

        expect(result.ok).toBe(true);
        expect(state.nodes[0].label).toBe('DLQ');
        expect(state.nodes[0].label).not.toBe('DLQ — fila de falhas');
        expect(state.nodes[0].type).toBe('dlq');
    });

    it('addNode_refuses_beyond_200_nodes', () => {
        const state = stateFixture(
            Array.from({ length: MAX_NODES }, (_, index) =>
                nodeFixture(`n${index}`, index * 200, 0),
            ),
        );

        const result = addNode(state, componentBySlug('api'), [0, 0]);

        expect(result).toEqual({ ok: false, reason: 'nodeLimitReached' });
        expect(state.nodes).toHaveLength(MAX_NODES);
    });

    it('aceita o bloco de número 200', () => {
        const state = stateFixture(
            Array.from({ length: MAX_NODES - 1 }, (_, index) =>
                nodeFixture(`n${index}`, index * 200, 0),
            ),
        );

        expect(addNode(state, componentBySlug('api'), [0, 0]).ok).toBe(true);
        expect(state.nodes).toHaveLength(MAX_NODES);
    });

    it('cria o bloco na grade de 4 px e sem sobrepor os existentes', () => {
        const state = stateFixture([nodeFixture('a', 400, 300)]);

        addNode(state, componentBySlug('api'), [401, 302]);

        const created = state.nodes[1];

        expect(created.x % 4).toBe(0);
        expect(created.y % 4).toBe(0);
        expect(created.y).toBeGreaterThan(state.nodes[0].y);
    });
});

describe('addEdge', () => {
    it('addEdge_refuses_beyond_400_edges', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400)],
            Array.from({ length: MAX_EDGES }, (_, index) =>
                edgeFixture(`e${index}`, 'a', 'b'),
            ),
        );

        const result = addEdge(state, 'a', 'b');

        expect(result).toEqual({ ok: false, reason: 'edgeLimitReached' });
        expect(state.edges).toHaveLength(MAX_EDGES);
    });

    it('recusa laço e bloco inexistente sem criar aresta', () => {
        const state = stateFixture([nodeFixture('a')]);

        expect(addEdge(state, 'a', 'a')).toEqual({
            ok: false,
            reason: 'invalidLink',
        });
        expect(addEdge(state, 'a', 'sumiu')).toEqual({
            ok: false,
            reason: 'invalidLink',
        });
        expect(state.edges).toHaveLength(0);
    });

    it('não duplica uma ligação que já existe', () => {
        const state = stateFixture([nodeFixture('a'), nodeFixture('b', 400)]);

        const first = addEdge(state, 'a', 'b');
        const again = addEdge(state, 'a', 'b');

        expect(first.ok && again.ok).toBe(true);
        expect(state.edges).toHaveLength(1);
    });
});

describe('moveNode', () => {
    it('move_snaps_to_4px_grid', () => {
        const state = stateFixture([nodeFixture('a', 0, 0)]);

        moveNode(state, 'a', 101.7, -33.2);

        expect(state.nodes[0].x).toBe(100);
        expect(state.nodes[0].y).toBe(-32);
    });

    it('não relata movimento quando o destino é a posição atual', () => {
        const state = stateFixture([nodeFixture('a', 100, 100)]);

        expect(moveNode(state, 'a', 101, 99)).toBe(false);
        expect(moveNode(state, 'a', 108, 100)).toBe(true);
    });
});

describe('renameNode', () => {
    it('label_is_capped_at_60_characters', () => {
        const state = stateFixture([nodeFixture('a')]);
        const longLabel = 'x'.repeat(90);

        renameNode(state, 'a', longLabel, 'API REST');

        expect(state.nodes[0].label).toHaveLength(MAX_LABEL_LENGTH);
        expect(normalizeLabel(longLabel, 'API REST')).toHaveLength(
            MAX_LABEL_LENGTH,
        );
    });

    it('empty_label_falls_back_to_short_name', () => {
        const state = stateFixture([nodeFixture('a')]);

        renameNode(state, 'a', '   ', 'DLQ');

        expect(state.nodes[0].label).toBe('DLQ');
        expect(normalizeLabel('', 'DLQ')).toBe('DLQ');
    });

    it('apara o rótulo antes de gravar', () => {
        const state = stateFixture([nodeFixture('a')]);

        renameNode(state, 'a', '  Fila de e-mails  ', 'Fila');

        expect(state.nodes[0].label).toBe('Fila de e-mails');
    });
});

describe('removeNode', () => {
    it('deleting_a_node_removes_its_incident_edges', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400), nodeFixture('c', 800)],
            [
                edgeFixture('entra', 'b', 'a'),
                edgeFixture('sai', 'a', 'c'),
                edgeFixture('longe', 'b', 'c'),
            ],
        );

        removeNode(state, 'a');

        expect(state.nodes.map((node) => node.id)).toEqual(['b', 'c']);
        expect(state.edges.map((edge) => edge.id)).toEqual(['longe']);
    });

    it('apaga só a aresta pedida', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400)],
            [edgeFixture('e1', 'a', 'b'), edgeFixture('e2', 'b', 'a')],
        );

        removeEdge(state, 'e1');

        expect(state.nodes).toHaveLength(2);
        expect(state.edges.map((edge) => edge.id)).toEqual(['e2']);
    });
});

describe('snapshot', () => {
    it('não compartilha referência com o estado vivo', () => {
        const state = stateFixture([nodeFixture('a')], []);
        const taken = snapshot(state);

        state.nodes[0].x = 500;

        expect(taken.nodes[0].x).toBe(0);

        restore(state, taken);

        expect(state.nodes[0].x).toBe(0);
    });
});

describe('diagramBounds', () => {
    it('envolve todos os blocos, com a altura medida de cada um', () => {
        const nodes = [nodeFixture('a', 0, 0), nodeFixture('b', 400, 200)];

        expect(diagramBounds(nodes, { b: 130 })).toEqual({
            x0: 0,
            y0: 0,
            x1: 532,
            y1: 330,
        });
        expect(diagramBounds([])).toBeNull();
    });
});
