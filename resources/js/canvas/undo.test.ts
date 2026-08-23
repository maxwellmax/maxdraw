import { describe, expect, it } from 'vitest';
import { restore, snapshot } from './diagram';
import { edgeFixture, nodeFixture, stateFixture } from './fixtures';
import type { DiagramSnapshot } from './types';
import { UNDO_LIMIT, UndoStack } from './undo';

function diagram(id: string): DiagramSnapshot {
    return { nodes: [nodeFixture(id)], edges: [] };
}

describe('UndoStack', () => {
    it('undo_stack_caps_at_60_states', () => {
        const stack = new UndoStack();

        for (let step = 0; step <= UNDO_LIMIT; step++) {
            stack.push(diagram(`n${step}`));
        }

        expect(stack.size).toBe(UNDO_LIMIT);

        let current = diagram('atual');
        let oldest = current;

        for (let step = 0; step < UNDO_LIMIT; step++) {
            oldest = stack.undo(current)!;
            current = oldest;
        }

        expect(oldest.nodes[0].id).toBe('n1');
        expect(stack.undo(current)).toBeNull();
    });

    it('undo_restores_previous_nodes_and_edges', () => {
        const stack = new UndoStack();
        const before: DiagramSnapshot = {
            nodes: [nodeFixture('a'), nodeFixture('b', 400)],
            edges: [edgeFixture('e1', 'a', 'b')],
        };

        stack.push(before);

        const restored = stack.undo({ nodes: [], edges: [] });

        expect(restored).toEqual(before);
        expect(restored?.nodes[0]).not.toBe(before.nodes[0]);
    });

    it('diagram_snapshot_serializes_only_nodes_and_edges', () => {
        const state = stateFixture(
            [nodeFixture('a'), nodeFixture('b', 400)],
            [edgeFixture('e1', 'a', 'b', 1)],
            false,
        );

        expect(Object.keys(snapshot(state))).toEqual(['nodes', 'edges']);

        const stack = new UndoStack();

        stack.push(snapshot(state));

        const restored = stack.undo(snapshot(state))!;

        expect(Object.keys(restored)).toEqual(['nodes', 'edges']);
        expect(restored).not.toHaveProperty('showConnectionOrder');

        // Devolver o diagrama à pilha não devolve a bandeira de exibição.
        restore(state, restored);

        expect(state.showConnectionOrder).toBe(false);
        expect(state.edges[0].order).toBe(1);
    });

    it('redo_stack_is_cleared_by_new_action', () => {
        const stack = new UndoStack();

        stack.push(diagram('primeiro'));
        stack.undo(diagram('segundo'));

        expect(stack.canRedo).toBe(true);

        stack.push(diagram('terceiro'));

        expect(stack.canRedo).toBe(false);
        expect(stack.redo(diagram('atual'))).toBeNull();
    });

    it('refaz o que acabou de ser desfeito', () => {
        const stack = new UndoStack();

        stack.push(diagram('antes'));

        const atual = diagram('depois');
        const undone = stack.undo(atual)!;

        expect(undone.nodes[0].id).toBe('antes');
        expect(stack.redo(undone)?.nodes[0].id).toBe('depois');
    });

    it('devolve nulo com a pilha vazia', () => {
        const stack = new UndoStack();

        expect(stack.canUndo).toBe(false);
        expect(stack.undo(diagram('a'))).toBeNull();
        expect(stack.redo(diagram('a'))).toBeNull();
    });
});
