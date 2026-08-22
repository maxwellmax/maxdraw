import type { DiagramSnapshot } from './types';

export const UNDO_LIMIT = 60;

/**
 * A pilha de desfazer do diagrama. Guarda só nós e arestas — view, seleção e
 * modo de numeração ficam de fora por contrato (US-3.5): desfazer devolve o
 * desenho, nunca o enquadramento nem o que estava selecionado.
 *
 * Os estados são serializados na entrada e desserializados na saída, para que
 * nada que volte da pilha compartilhe referência com o estado vivo.
 */
export class UndoStack {
    private past: string[] = [];

    private future: string[] = [];

    get canUndo(): boolean {
        return this.past.length > 0;
    }

    get canRedo(): boolean {
        return this.future.length > 0;
    }

    get size(): number {
        return this.past.length;
    }

    get redoSize(): number {
        return this.future.length;
    }

    /** Empilha o estado anterior à ação. Toda ação nova invalida o refazer. */
    push(diagram: DiagramSnapshot): void {
        this.past.push(serialize(diagram));

        if (this.past.length > UNDO_LIMIT) {
            this.past.shift();
        }

        this.future = [];
    }

    undo(current: DiagramSnapshot): DiagramSnapshot | null {
        const previous = this.past.pop();

        if (previous === undefined) {
            return null;
        }

        this.future.push(serialize(current));

        return deserialize(previous);
    }

    redo(current: DiagramSnapshot): DiagramSnapshot | null {
        const next = this.future.pop();

        if (next === undefined) {
            return null;
        }

        this.past.push(serialize(current));

        return deserialize(next);
    }

    clear(): void {
        this.past = [];
        this.future = [];
    }
}

function serialize(diagram: DiagramSnapshot): string {
    return JSON.stringify({ nodes: diagram.nodes, edges: diagram.edges });
}

function deserialize(raw: string): DiagramSnapshot {
    return JSON.parse(raw) as DiagramSnapshot;
}
