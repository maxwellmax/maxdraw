import { beforeEach, describe, expect, it } from 'vitest';
import { CanvasEngine } from './engine';
import {
    catalogFixture,
    edgeFixture,
    linkTypesFixture,
    nodeFixture,
    stateFixture,
} from './fixtures';
import { NODE_WIDTH } from './geometry';
import { MAX_EDGES } from './limits';
import type { SessionState } from './types';
import { MAX_SCALE, MIN_SCALE } from './view';

const STAGE = { width: 1200, height: 800 };

function engineWith(state: SessionState = stateFixture()): CanvasEngine {
    const engine = new CanvasEngine(
        state,
        catalogFixture(),
        linkTypesFixture(),
    );

    engine.setSize(STAGE);

    return engine;
}

describe('paleta', () => {
    let engine: CanvasEngine;

    beforeEach(() => {
        engine = engineWith();
    });

    it('coloca o bloco no meio da área visível e já o seleciona', () => {
        const result = engine.addNode('api');

        expect(result.ok).toBe(true);
        expect(engine.nodes).toHaveLength(1);
        expect(engine.nodes[0].x).toBe(
            Math.round((STAGE.width / 2 - NODE_WIDTH / 2) / 4) * 4,
        );
        expect(engine.isSelected('node', engine.nodes[0].id)).toBe(true);
    });

    it('usa o nome curto do componente como rótulo inicial', () => {
        engine.addNode('dlq');

        expect(engine.nodes[0].label).toBe('DLQ');
    });

    it('tira a cor do bloco da categoria do componente', () => {
        expect(engine.color('api')).toBe('var(--c-compute)');
        expect(engine.color('sql')).toBe('var(--c-data)');
        expect(engine.color('dlq')).toBe('var(--c-async)');
        expect(engine.color('aposentado')).toBe('var(--ink-3)');
    });

    it('recusa um tipo que não está no catálogo', () => {
        expect(engine.addNode('inexistente')).toEqual({
            ok: false,
            reason: 'unknownComponent',
        });
        expect(engine.nodes).toHaveLength(0);
    });
});

describe('arrastar', () => {
    it('move com snap de 4 px e empilha um único desfazer', () => {
        const engine = engineWith(stateFixture([nodeFixture('a', 100, 100)]));

        engine.beginDrag('a', 100, 100);
        engine.dragTo(203, 151);
        engine.dragTo(211, 159);
        engine.endDrag();

        expect(engine.nodes[0]).toMatchObject({ x: 212, y: 160 });
        expect(engine.undoDepth).toBe(1);
    });

    it('um arrasto que não moveu nada não empilha', () => {
        const engine = engineWith(stateFixture([nodeFixture('a', 100, 100)]));

        engine.beginDrag('a', 110, 110);
        engine.dragTo(111, 109);

        expect(engine.endDrag()).toBe(false);
        expect(engine.undoDepth).toBe(0);
    });
});

describe('renomear', () => {
    it('empilha desfazer e volta ao nome curto quando esvaziado', () => {
        const engine = engineWith(
            stateFixture([nodeFixture('a', 0, 0, 'dlq')]),
        );

        expect(engine.renameNode('a', 'fila de falhas do checkout')).toBe(true);
        expect(engine.undoDepth).toBe(1);

        engine.renameNode('a', '  ');

        expect(engine.nodes[0].label).toBe('DLQ');
        expect(engine.undoDepth).toBe(2);
    });

    it('não empilha quando o rótulo não mudou', () => {
        const engine = engineWith(stateFixture([nodeFixture('a')]));

        engine.renameNode('a', 'a');

        expect(engine.undoDepth).toBe(0);
    });
});

describe('apagar', () => {
    it('delete_clears_selection', () => {
        const engine = engineWith(
            stateFixture(
                [nodeFixture('a'), nodeFixture('b', 400)],
                [edgeFixture('e1', 'a', 'b')],
            ),
        );

        engine.selectNode('a');

        expect(engine.deleteSelection()).toBe(true);
        expect(engine.selection).toBeNull();
        expect(engine.nodes.map((node) => node.id)).toEqual(['b']);
        expect(engine.edges).toHaveLength(0);
    });

    it('não faz nada sem seleção', () => {
        const engine = engineWith(stateFixture([nodeFixture('a')]));

        expect(engine.deleteSelection()).toBe(false);
        expect(engine.undoDepth).toBe(0);
    });
});

describe('ligar', () => {
    function linkable(): CanvasEngine {
        return engineWith(
            stateFixture([nodeFixture('a'), nodeFixture('b', 400)]),
        );
    }

    it('desenha a curva fantasma da bolinha até o cursor', () => {
        const engine = linkable();

        expect(engine.ghost).toBeNull();

        engine.beginLink('a', 66, 43);
        engine.linkTo(300, 200);

        expect(engine.isLinking).toBe(true);
        expect(engine.ghost?.d).toMatch(/^M[\d.]+ [\d.]+C.* 300 200$/);
    });

    it('destaca o bloco sob o cursor, menos o próprio bloco de origem', () => {
        const engine = linkable();

        engine.beginLink('a', 66, 43);
        engine.linkTo(430, 43, 'b');

        expect(engine.linkTarget).toBe('b');

        engine.linkTo(66, 43, 'a');

        expect(engine.linkTarget).toBeNull();

        engine.linkTo(900, 900, 'apagado');

        expect(engine.linkTarget).toBeNull();
    });

    it('cria a aresta ao soltar sobre um bloco válido e já a seleciona', () => {
        const engine = linkable();

        engine.beginLink('a', 66, 43);
        engine.linkTo(430, 43, 'b');

        expect(engine.endLink()).toMatchObject({ ok: true });
        expect(engine.edges).toHaveLength(1);
        expect(engine.edges[0]).toMatchObject({ from: 'a', to: 'b' });
        expect(engine.isSelected('edge', engine.edges[0].id)).toBe(true);
        expect(engine.isLinking).toBe(false);
        expect(engine.ghost).toBeNull();
    });

    it('soltar fora de qualquer bloco não cria nada', () => {
        const engine = linkable();

        engine.beginLink('a', 66, 43);
        engine.linkTo(900, 700);

        expect(engine.endLink()).toBeNull();
        expect(engine.edges).toHaveLength(0);
        expect(engine.undoDepth).toBe(0);
    });

    it('soltar sobre o próprio bloco de origem não cria nada', () => {
        const engine = linkable();

        engine.beginLink('a', 66, 43);
        engine.linkTo(70, 50, 'a');

        expect(engine.endLink()).toBeNull();
        expect(engine.edges).toHaveLength(0);
    });

    it('recusa a ligação de número 401 com o motivo do aviso', () => {
        const state = stateFixture([nodeFixture('a'), nodeFixture('b', 400)]);

        for (let index = 0; index < MAX_EDGES; index++) {
            state.edges.push(edgeFixture('e' + index, 'a', 'b'));
        }

        const engine = engineWith(state);

        engine.beginLink('a', 66, 43);
        engine.linkTo(430, 43, 'b');

        expect(engine.endLink()).toEqual({
            ok: false,
            reason: 'edgeLimitReached',
        });
        expect(engine.edges).toHaveLength(MAX_EDGES);
    });

    it('cancelar o arrasto larga a ligação sem criar nada', () => {
        const engine = linkable();

        engine.beginLink('a', 66, 43);
        engine.linkTo(430, 43, 'b');
        engine.cancelLink();

        expect(engine.isLinking).toBe(false);
        expect(engine.edges).toHaveLength(0);
    });

    it('a seta acompanha o bloco arrastado', () => {
        const engine = engineWith(
            stateFixture(
                [nodeFixture('a'), nodeFixture('b', 400)],
                [edgeFixture('e1', 'a', 'b')],
            ),
        );
        const before = engine.geometry(engine.edge('e1')!)!.x1;

        engine.beginDrag('a', 0, 0);
        engine.dragTo(120, 60);

        expect(engine.geometry(engine.edge('e1')!)!.x1).not.toBe(before);
    });
});

describe('tipo e bandeiras da ligação', () => {
    function typed(): CanvasEngine {
        return engineWith(
            stateFixture(
                [
                    nodeFixture('a', 0, 0, 'api'),
                    nodeFixture('b', 400, 0, 'sql'),
                ],
                [edgeFixture('e1', 'a', 'b')],
            ),
        );
    }

    it('choosing_ws_sets_bidirectional', () => {
        const engine = typed();

        engine.setEdgeKind('e1', 'ws');

        expect(engine.edge('e1')).toMatchObject({
            kind: 'ws',
            bidir: true,
            dashed: false,
        });

        engine.toggleEdgeFlag('e1', 'bidir');

        expect(engine.edge('e1')?.bidir).toBe(false);
        expect(engine.edge('e1')?.kind).toBe('ws');
    });

    it('lista os nove tipos na ordem do catálogo', () => {
        expect(typed().linkTypes.map((type) => type.slug)).toEqual([
            'http',
            'grpc',
            'ws',
            'event',
            'query',
            'cache',
            'repl',
            'batch',
            'retry',
        ]);
    });

    it('edge_label_is_capped_at_60_characters', () => {
        const engine = typed();

        engine.setEdgeLabel('e1', 'x'.repeat(80));

        expect(engine.edge('e1')?.label).toHaveLength(60);

        engine.setEdgeLabel('e1', '  GET /feed  ');

        expect(engine.edge('e1')?.label).toBe('GET /feed');
    });

    it('reversing_edge_swaps_endpoints_and_recolors_badge', () => {
        const engine = typed();

        engine.setEdgeKind('e1', 'query');

        expect(engine.edgeColor(engine.edge('e1')!)).toBe('var(--c-compute)');

        engine.reverseEdge('e1');

        expect(engine.edge('e1')).toMatchObject({ from: 'b', to: 'a' });
        expect(engine.edgeColor(engine.edge('e1')!)).toBe('var(--c-data)');
        expect(engine.edgeChip(engine.edge('e1')!).badge).toBe('query');
    });

    it('type_and_flag_changes_push_undo', () => {
        const engine = typed();

        engine.setEdgeKind('e1', 'event');
        engine.setEdgeLabel('e1', 'GET /feed');
        engine.toggleEdgeFlag('e1', 'dashed');
        engine.toggleEdgeFlag('e1', 'bidir');
        engine.reverseEdge('e1');

        expect(engine.undoDepth).toBe(5);

        engine.undo();

        expect(engine.edge('e1')).toMatchObject({ from: 'a', to: 'b' });

        engine.undo();
        engine.undo();
        engine.undo();

        expect(engine.edge('e1')).toMatchObject({
            kind: 'event',
            label: '',
            dashed: true,
            bidir: false,
        });

        engine.undo();

        expect(engine.edge('e1')?.kind).toBeNull();
    });

    it('não empilha quando o pedido não muda nada', () => {
        const engine = typed();

        engine.setEdgeKind('e1', null);
        engine.setEdgeLabel('e1', '');
        engine.setEdgeKind('inexistente', 'http');
        engine.reverseEdge('inexistente');

        expect(engine.undoDepth).toBe(0);
    });
});

describe('ordem explícita', () => {
    function numbered(): CanvasEngine {
        return engineWith(
            stateFixture(
                [
                    nodeFixture('a', 0, 0, 'browser'),
                    nodeFixture('b', 400, 0, 'api'),
                    nodeFixture('c', 800, 0, 'sql'),
                    nodeFixture('d', 1200, 0, 'worker'),
                ],
                [
                    edgeFixture('e1', 'a', 'b', 1),
                    edgeFixture('e2', 'b', 'c', 2),
                    edgeFixture('e3', 'c', 'd', 3),
                ],
            ),
        );
    }

    function orderMap(engine: CanvasEngine): Record<string, number | null> {
        return Object.fromEntries(
            engine.edges.map((edge) => [edge.id, edge.order]),
        );
    }

    it('undo_restores_the_order_map_after_setEdgeOrder', () => {
        const engine = numbered();

        expect(engine.setEdgeOrder('e3', 1)).toBe(true);
        expect(engine.undoDepth).toBe(1);
        expect(orderMap(engine)).toEqual({ e3: 1, e1: 2, e2: 3 });

        engine.undo();

        expect(orderMap(engine)).toEqual({ e1: 1, e2: 2, e3: 3 });
        expect(engine.undoDepth).toBe(0);
    });

    it('undo_restores_the_order_map_after_clearEdgeOrder', () => {
        const engine = numbered();

        expect(engine.clearEdgeOrder('e2')).toBe(true);
        expect(engine.undoDepth).toBe(1);
        expect(orderMap(engine)).toEqual({ e1: 1, e2: null, e3: 2 });
        expect(engine.numberedCount).toBe(2);

        engine.undo();

        expect(orderMap(engine)).toEqual({ e1: 1, e2: 2, e3: 3 });
        expect(engine.numberedCount).toBe(3);
    });

    it('numberedCount_reports_the_current_N', () => {
        const engine = numbered();

        expect(engine.numberedCount).toBe(3);
        expect(engine.orderOf(engine.edge('e2')!)).toBe(2);

        engine.addEdge('a', 'c');

        expect(engine.numberedCount).toBe(3);
        expect(engine.orderOf(engine.edges[3])).toBeNull();
    });

    it('autoNumberOrder_stacks_a_single_undo_step', () => {
        const engine = engineWith(
            stateFixture(
                [
                    nodeFixture('a', 0, 0, 'browser'),
                    nodeFixture('b', 400, 0, 'api'),
                    nodeFixture('c', 800, 0, 'sql'),
                    nodeFixture('d', 1200, 0, 'worker'),
                ],
                [
                    edgeFixture('e1', 'a', 'b', 3),
                    edgeFixture('e2', 'b', 'c'),
                    edgeFixture('e3', 'a', 'c', 1),
                    edgeFixture('e4', 'c', 'd'),
                ],
            ),
        );
        const before = orderMap(engine);

        expect(engine.autoNumberOrder()).toBe(true);
        expect(engine.undoDepth).toBe(1);
        expect(orderMap(engine)).toEqual({ e1: 1, e2: 3, e3: 2, e4: 4 });

        engine.undo();

        expect(orderMap(engine)).toEqual(before);
        expect(engine.undoDepth).toBe(0);

        expect(engine.autoNumberOrder()).toBe(true);
        expect(engine.autoNumberOrder()).toBe(false);
        expect(engine.undoDepth).toBe(1);
    });

    it('não empilha desfazer quando a ordem não muda', () => {
        const engine = numbered();

        expect(engine.setEdgeOrder('e1', 1)).toBe(false);
        expect(engine.setEdgeOrder('sumiu', 2)).toBe(false);
        expect(engine.clearEdgeOrder('sumiu')).toBe(false);
        expect(engine.undoDepth).toBe(0);
    });
});

describe('desfazer', () => {
    it('undo_restores_previous_nodes_and_edges', () => {
        const engine = engineWith();

        engine.addNode('api');
        engine.addNode('sql');
        const [first, second] = engine.nodes.map((node) => node.id);
        engine.addEdge(first, second);

        expect(engine.undo()).toBe(true);
        expect(engine.edges).toHaveLength(0);
        expect(engine.nodes).toHaveLength(2);

        expect(engine.undo()).toBe(true);
        expect(engine.nodes).toHaveLength(1);

        expect(engine.redo()).toBe(true);
        expect(engine.nodes).toHaveLength(2);
    });

    it('redo_stack_is_cleared_by_new_action', () => {
        const engine = engineWith();

        engine.addNode('api');
        engine.addNode('sql');
        engine.undo();

        expect(engine.canRedo).toBe(true);

        engine.addNode('worker');

        expect(engine.canRedo).toBe(false);
        expect(engine.redo()).toBe(false);
    });

    it('toggling_connection_order_does_not_push_undo', () => {
        const engine = engineWith(
            stateFixture(
                [nodeFixture('a'), nodeFixture('b', 400)],
                [edgeFixture('e1', 'a', 'b', 1)],
            ),
        );

        const depth = engine.undoDepth;

        for (let round = 0; round < 5; round++) {
            expect(engine.setShowConnectionOrder(false)).toBe(true);
            expect(engine.showConnectionOrder).toBe(false);
            expect(engine.setShowConnectionOrder(true)).toBe(true);
        }

        expect(engine.setShowConnectionOrder(true)).toBe(false);
        expect(engine.undoDepth).toBe(depth);
        expect(engine.canUndo).toBe(false);

        // A bandeira apagada não mexe no número gravado na aresta.
        engine.setShowConnectionOrder(false);

        expect(engine.edges[0].order).toBe(1);
    });

    it('undo_does_not_revert_show_connection_order', () => {
        const engine = engineWith();

        engine.addNode('api');
        engine.setShowConnectionOrder(false);

        expect(engine.undo()).toBe(true);
        expect(engine.showConnectionOrder).toBe(false);
        expect(engine.nodes).toHaveLength(0);
    });

    it('pan_and_zoom_do_not_push_undo', () => {
        const engine = engineWith();

        engine.addNode('api');

        const depth = engine.undoDepth;

        engine.panBy(120, -60);
        engine.zoomBy(1.2);
        engine.zoomAt(0.8, 300, 200);
        engine.wheel(-240, 300, 200);
        engine.fit();
        engine.select(null);

        expect(engine.undoDepth).toBe(depth);
        expect(engine.canRedo).toBe(false);
    });

    it('a pilha guarda só nós e arestas', () => {
        const engine = engineWith();

        engine.addNode('api');
        engine.panBy(200, 200);
        engine.setShowConnectionOrder(false);
        engine.selectNode(engine.nodes[0].id);
        engine.undo();

        expect(engine.view).toMatchObject({ x: 200, y: 200 });
        expect(engine.showConnectionOrder).toBe(false);
        expect(engine.nodes).toHaveLength(0);
    });
});

describe('navegação', () => {
    it('faz pan somando o deslocamento do ponteiro', () => {
        const engine = engineWith();

        engine.panBy(40, -25);
        engine.panBy(10, 5);

        expect(engine.view).toMatchObject({ x: 50, y: -20, k: 1 });
    });

    it('ancora o zoom no ponto sob o cursor', () => {
        const engine = engineWith();

        engine.wheel(-200, 300, 200);

        const [worldX, worldY] = engine.toWorld(300, 200);

        expect(engine.view.k).toBeGreaterThan(1);
        expect(worldX).toBeCloseTo(300, 8);
        expect(worldY).toBeCloseTo(200, 8);
    });

    it('mantém a escala entre 30% e 240%', () => {
        const engine = engineWith();

        for (let step = 0; step < 40; step++) {
            engine.zoomBy(1.2);
        }

        expect(engine.view.k).toBe(MAX_SCALE);

        for (let step = 0; step < 60; step++) {
            engine.zoomBy(1 / 1.2);
        }

        expect(engine.view.k).toBe(MIN_SCALE);
    });

    it('enquadra tudo reservando a largura da legenda expandida', () => {
        const engine = engineWith(
            stateFixture([nodeFixture('a', 0, 0), nodeFixture('b', 1600, 900)]),
        );
        const rightEdge = (): number =>
            engine.view.x + (1600 + NODE_WIDTH) * engine.view.k;
        const legendEdge = STAGE.width - 238;

        engine.fit();

        expect(rightEdge()).toBeGreaterThan(legendEdge);

        const wide = engine.view.k;

        engine.setLegendWidth(238);
        engine.fit();

        expect(engine.view.k).toBeLessThan(wide);
        expect(rightEdge()).toBeLessThanOrEqual(legendEdge);
    });

    it('volta ao enquadramento padrão com o palco vazio', () => {
        const engine = engineWith();

        engine.panBy(300, 300);
        engine.fit();

        expect(engine.view).toEqual({ x: 0, y: 0, k: 1 });
    });
});
