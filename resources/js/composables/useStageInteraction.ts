import { useEventListener, useResizeObserver } from '@vueuse/core';
import type { Ref } from 'vue';
import type { CanvasEngine } from '@/canvas/engine';

type Options = {
    /** Chamado quando o usuário pede para renomear pelo teclado (Enter ou F2). */
    onEditRequest: (nodeId: string) => void;
};

type Gesture =
    | { kind: 'drag' }
    | { kind: 'pan'; x: number; y: number; viewX: number; viewY: number }
    | null;

/** O que engole o ponteiro antes do palco: pan e zoom não valem sobre eles. */
const OVERLAYS = '[data-testid="legend"],[data-testid="zoombar"]';

/**
 * Traduz ponteiro, roda e teclado para o motor do canvas. É a única peça que
 * conhece o DOM do palco — o motor recebe coordenadas de mundo e decide o resto
 * (US-3.2, US-3.4, US-3.5).
 */
export function useStageInteraction(
    engine: CanvasEngine,
    stage: Ref<HTMLElement | null>,
    options: Options,
): void {
    let gesture: Gesture = null;

    useResizeObserver(stage, ([entry]) =>
        engine.setSize({
            width: entry.contentRect.width,
            height: entry.contentRect.height,
        }),
    );

    function toWorld(clientX: number, clientY: number): [number, number] {
        const rect = stage.value?.getBoundingClientRect();

        return engine.toWorld(
            clientX - (rect?.left ?? 0),
            clientY - (rect?.top ?? 0),
        );
    }

    function localPoint(clientX: number, clientY: number): [number, number] {
        const rect = stage.value?.getBoundingClientRect();

        return [clientX - (rect?.left ?? 0), clientY - (rect?.top ?? 0)];
    }

    useEventListener(stage, 'pointerdown', (event: PointerEvent) => {
        const target = event.target as HTMLElement | null;

        if (event.button === 2 || !target || target.closest(OVERLAYS)) {
            return;
        }

        const editing = document.querySelector('[contenteditable="true"]');

        if (editing instanceof HTMLElement && !editing.contains(target)) {
            editing.blur();
        }

        // As bolinhas de conexão são o gesto de ligar, que a Phase 11 implementa.
        if (target.closest('[data-handle]')) {
            return;
        }

        const nodeElement = target.closest<HTMLElement>('[data-node-id]');

        if (nodeElement?.dataset.nodeId) {
            const id = nodeElement.dataset.nodeId;

            engine.selectNode(id);

            if (
                target.closest('[data-testid="node-label"]') &&
                event.detail >= 2
            ) {
                return;
            }

            event.preventDefault();

            const [worldX, worldY] = toWorld(event.clientX, event.clientY);

            engine.beginDrag(id, worldX, worldY);
            gesture = { kind: 'drag' };
            stage.value?.setPointerCapture(event.pointerId);

            return;
        }

        const edgeElement = target.closest<HTMLElement>('[data-edge-id]');

        if (edgeElement?.dataset.edgeId) {
            engine.select({ kind: 'edge', id: edgeElement.dataset.edgeId });
            event.preventDefault();

            return;
        }

        engine.select(null);

        gesture = {
            kind: 'pan',
            x: event.clientX,
            y: event.clientY,
            viewX: engine.view.x,
            viewY: engine.view.y,
        };

        stage.value?.setPointerCapture(event.pointerId);
    });

    useEventListener(stage, 'pointermove', (event: PointerEvent) => {
        if (!gesture) {
            return;
        }

        if (gesture.kind === 'drag') {
            const [worldX, worldY] = toWorld(event.clientX, event.clientY);

            engine.dragTo(worldX, worldY);

            return;
        }

        engine.setView({
            k: engine.view.k,
            x: gesture.viewX + (event.clientX - gesture.x),
            y: gesture.viewY + (event.clientY - gesture.y),
        });
    });

    function endGesture(): void {
        if (gesture?.kind === 'drag') {
            engine.endDrag();
        }

        gesture = null;
    }

    useEventListener(stage, 'pointerup', endGesture);
    useEventListener(stage, 'pointercancel', endGesture);

    useEventListener(
        stage,
        'wheel',
        (event: WheelEvent) => {
            const target = event.target as HTMLElement | null;

            if (target?.closest(OVERLAYS)) {
                return;
            }

            event.preventDefault();

            const [x, y] = localPoint(event.clientX, event.clientY);

            if (
                event.ctrlKey ||
                event.metaKey ||
                Math.abs(event.deltaY) > Math.abs(event.deltaX) * 2
            ) {
                engine.wheel(event.deltaY, x, y);

                return;
            }

            engine.panBy(-event.deltaX, -event.deltaY);
        },
        { passive: false },
    );

    useEventListener(document, 'keydown', (event: KeyboardEvent) => {
        if (isTyping(event.target)) {
            return;
        }

        if (
            (event.metaKey || event.ctrlKey) &&
            event.key.toLowerCase() === 'z'
        ) {
            event.preventDefault();

            if (event.shiftKey) {
                engine.redo();
            } else {
                engine.undo();
            }

            return;
        }

        if (event.key === 'Delete' || event.key === 'Backspace') {
            if (engine.selection) {
                event.preventDefault();
                engine.deleteSelection();
            }

            return;
        }

        if (event.key === 'Escape') {
            engine.select(null);

            return;
        }

        if (
            (event.key === 'Enter' || event.key === 'F2') &&
            engine.selection?.kind === 'node'
        ) {
            event.preventDefault();
            options.onEditRequest(engine.selection.id);
        }
    });
}

/** Nenhum atalho dispara enquanto o foco está num campo ou num rótulo em edição. */
function isTyping(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return (
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.tagName === 'SELECT' ||
        target.isContentEditable
    );
}
