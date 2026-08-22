import { diagramBounds } from './diagram';
import { clamp } from './geometry';
import { NODE_WIDTH } from './geometry';
import type { Node, NodeHeights, Point, Size, View } from './types';

export const MIN_SCALE = 0.3;

export const MAX_SCALE = 2.4;

/** Enquadrar tudo nunca amplia além de 120%: um diagrama de um bloco só fica ridículo. */
export const FIT_MAX_SCALE = 1.2;

export const FIT_PADDING = 70;

/** A largura mínima de trabalho quando a legenda come quase todo o palco. */
export const MIN_FIT_WIDTH = 260;

export const LEGEND_GUTTER = 24;

/** A largura do painel da legenda; ela não muda quando o painel é recolhido. */
export const LEGEND_WIDTH = 238;

export const GRID_SIZE = 26;

export const WHEEL_ZOOM_RATIO = 0.0016;

export const DEFAULT_VIEW: View = { x: 0, y: 0, k: 1 };

export function toWorld(view: View, x: number, y: number): Point {
    return [(x - view.x) / view.k, (y - view.y) / view.k];
}

export function panBy(view: View, dx: number, dy: number): View {
    return { ...view, x: view.x + dx, y: view.y + dy };
}

/**
 * Zoom ancorado: o ponto `(ax, ay)` da tela continua sobre o mesmo ponto do
 * mundo depois da mudança de escala.
 */
export function zoomAt(
    view: View,
    factor: number,
    ax: number,
    ay: number,
): View {
    const k = clamp(view.k * factor, MIN_SCALE, MAX_SCALE);
    const ratio = k / view.k;

    return {
        k,
        x: ax - (ax - view.x) * ratio,
        y: ay - (ay - view.y) * ratio,
    };
}

export function wheelZoom(
    view: View,
    deltaY: number,
    ax: number,
    ay: number,
): View {
    return zoomAt(view, Math.exp(-deltaY * WHEEL_ZOOM_RATIO), ax, ay);
}

/**
 * O enquadramento que faz todo o diagrama caber. A legenda expandida cobre o
 * canto direito do palco, então a largura dela sai da conta antes — senão o
 * desenho fica centralizado embaixo dela (US-3.4).
 */
export function fitView(
    nodes: readonly Node[],
    size: Size,
    options: { heights?: NodeHeights; legendWidth?: number } = {},
): View {
    const bounds = diagramBounds(nodes, options.heights ?? {});

    if (!bounds) {
        return { ...DEFAULT_VIEW };
    }

    const reserved = options.legendWidth
        ? options.legendWidth + LEGEND_GUTTER
        : 0;
    const usableWidth = Math.max(size.width - reserved, MIN_FIT_WIDTH);
    const width = bounds.x1 - bounds.x0 || 1;
    const height = bounds.y1 - bounds.y0 || 1;

    const k = clamp(
        Math.min(
            (usableWidth - FIT_PADDING * 2) / width,
            (size.height - FIT_PADDING * 2) / height,
        ),
        MIN_SCALE,
        FIT_MAX_SCALE,
    );

    return {
        k,
        x: (usableWidth - (bounds.x1 - bounds.x0) * k) / 2 - bounds.x0 * k,
        y: (size.height - (bounds.y1 - bounds.y0) * k) / 2 - bounds.y0 * k,
    };
}

/** O passo da grade acompanha o zoom; o deslocamento dela acompanha o pan. */
export function gridSize(view: View): number {
    return GRID_SIZE * view.k;
}

export function scalePercent(view: View): number {
    return Math.round(view.k * 100);
}

/**
 * Onde nasce um bloco criado pela paleta: o canto superior esquerdo que deixa o
 * bloco centrado na parte visível do palco.
 */
export function centerSpot(view: View, size: Size, height: number): Point {
    return [
        (size.width / 2 - view.x) / view.k - NODE_WIDTH / 2,
        (size.height / 2 - view.y) / view.k - height / 2,
    ];
}
