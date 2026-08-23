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
import type { Edge } from './types';

/** O mapa `id → order` do diagrama, que é o que a sequência promete. */
function orderMap(edges: readonly Edge[]): Record<string, number | null> {
    return Object.fromEntries(edges.map((edge) => [edge.id, edge.order]));
}

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

    it('new_edge_starts_outside_the_sequence', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400), nodeFixture('c', 800)],
            [edgeFixture('numerada', 'a', 'b', 1)],
        );

        const result = addEdge(state, 'b', 'c');

        expect(result.ok && result.value.order).toBeNull();
        expect(state.edges.map((edge) => edge.order)).toEqual([1, null]);
    });

    it('a ligação já existente volta sem perder o número que tinha', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400)],
            [edgeFixture('e1', 'a', 'b', 1)],
        );

        const again = addEdge(state, 'a', 'b');

        expect(again.ok && again.value.order).toBe(1);
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

    it('removeEdge_densifies_the_remaining_orders', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400), nodeFixture('c', 800)],
            [
                edgeFixture('a', 'a', 'b', 1),
                edgeFixture('b', 'b', 'c', 2),
                edgeFixture('c', 'c', 'a', 3),
            ],
        );

        removeEdge(state, 'b');

        expect(orderMap(state.edges)).toEqual({ a: 1, c: 2 });
    });

    it('removeNode_densifies_once_after_removing_its_edges', () => {
        const state = stateFixture(
            [
                nodeFixture('hub'),
                nodeFixture('a', 400),
                nodeFixture('b', 800),
                nodeFixture('c', 1200),
            ],
            [
                edgeFixture('h1', 'hub', 'a', 1),
                edgeFixture('sobra1', 'a', 'b', 2),
                edgeFixture('h2', 'hub', 'b', 3),
                edgeFixture('sobra2', 'b', 'c', 4),
                edgeFixture('h3', 'c', 'hub', 5),
                edgeFixture('sobra3', 'c', 'a', 6),
            ],
        );

        removeNode(state, 'hub');

        expect(orderMap(state.edges)).toEqual({
            sobra1: 1,
            sobra2: 2,
            sobra3: 3,
        });
    });
});

describe('snapshot', () => {
    it('restore_keeps_sparse_orders_without_densifying', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400)],
            [edgeFixture('e1', 'a', 'b', 1), edgeFixture('e2', 'b', 'a', 3)],
        );

        const taken = snapshot(state);

        expect(orderMap(taken.edges)).toEqual({ e1: 1, e2: 3 });

        state.edges[1].order = 2;
        restore(state, taken);

        expect(orderMap(state.edges)).toEqual({ e1: 1, e2: 3 });
    });

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
