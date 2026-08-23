import { nodeById, nodeHeight, NODE_HEIGHT } from './nodes';
import type {
    ArrowHead,
    Edge,
    EdgeGeometry,
    Node,
    NodeHeights,
    Point,
} from './types';

export const NODE_WIDTH = 132;

export { NODE_HEIGHT };

/** O recorte é mais folgado no destino para a ponta da seta não encostar no bloco. */
export const SOURCE_PAD = 7;

export const TARGET_PAD = 10;

export const CONTROL_RATIO = 0.45;

export const CONTROL_MIN = 26;

export const CONTROL_MAX = 110;

export const HEAD_LENGTH = 9.5;

export const HEAD_WIDTH = 3.9;

export function clamp(value: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, value));
}

/**
 * Recorta o ponto na borda do retângulo centrado em `(cx, cy)`, na direção de
 * `(tx, ty)`: escala pelo menor entre a razão horizontal, a vertical e 1 — o 1
 * garante que um alvo dentro do retângulo devolva o próprio alvo, sem esticar.
 */
export function clipPt(
    cx: number,
    cy: number,
    w: number,
    h: number,
    tx: number,
    ty: number,
    pad: number,
): Point {
    const dx = tx - cx;
    const dy = ty - cy;

    if (dx === 0 && dy === 0) {
        return [cx, cy];
    }

    const sx = dx === 0 ? Infinity : (w / 2 + pad) / Math.abs(dx);
    const sy = dy === 0 ? Infinity : (h / 2 + pad) / Math.abs(dy);
    const s = Math.min(sx, sy, 1);

    return [cx + dx * s, cy + dy * s];
}

/**
 * A curva entre dois pontos, com os controles do bezier no eixo dominante: o
 * traçado sai horizontal quando o vão horizontal manda, e vertical quando é o
 * vertical que manda.
 */
export function curve(
    x1: number,
    y1: number,
    x2: number,
    y2: number,
): EdgeGeometry {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const horizontal = Math.abs(dx) >= Math.abs(dy);
    const k = clamp(
        (horizontal ? Math.abs(dx) : Math.abs(dy)) * CONTROL_RATIO,
        CONTROL_MIN,
        CONTROL_MAX,
    );

    const c1: Point = horizontal
        ? [x1 + Math.sign(dx) * k, y1]
        : [x1, y1 + Math.sign(dy) * k];
    const c2: Point = horizontal
        ? [x2 - Math.sign(dx) * k, y2]
        : [x2, y2 - Math.sign(dy) * k];

    return {
        x1,
        y1,
        x2,
        y2,
        c1,
        c2,
        d: `M${x1} ${y1}C${c1[0]} ${c1[1]} ${c2[0]} ${c2[1]} ${x2} ${y2}`,
    };
}

/**
 * A curva da aresta: centro a centro, recortada na borda dos dois blocos.
 * Devolve `null` quando a aresta é um laço ou aponta para um bloco que não
 * existe mais — a órfã fica no estado, mas não é desenhada.
 */
export function bez(
    edge: Edge,
    nodes: readonly Node[],
    heights: NodeHeights = {},
): EdgeGeometry | null {
    const from = nodeById(nodes, edge.from);
    const to = nodeById(nodes, edge.to);

    if (!from || !to || from === to) {
        return null;
    }

    const fromHeight = nodeHeight(from, heights);
    const toHeight = nodeHeight(to, heights);
    const ax = from.x + NODE_WIDTH / 2;
    const ay = from.y + fromHeight / 2;
    const bx = to.x + NODE_WIDTH / 2;
    const by = to.y + toHeight / 2;

    const [x1, y1] = clipPt(ax, ay, NODE_WIDTH, fromHeight, bx, by, SOURCE_PAD);
    const [x2, y2] = clipPt(bx, by, NODE_WIDTH, toHeight, ax, ay, TARGET_PAD);

    return curve(x1, y1, x2, y2);
}

/**
 * A curva fantasma do arrasto de ligação: sai da borda do bloco de origem, na
 * direção do ponteiro, e termina no próprio ponteiro — que ainda não é bloco
 * nenhum e por isso não tem borda para recortar (US-3.3).
 */
export function ghostCurve(
    from: Node,
    height: number,
    x: number,
    y: number,
): EdgeGeometry {
    const ax = from.x + NODE_WIDTH / 2;
    const ay = from.y + height / 2;
    const [x1, y1] = clipPt(ax, ay, NODE_WIDTH, height, x, y, SOURCE_PAD);

    return curve(x1, y1, x, y);
}

/**
 * O ponto do bezier cúbico em `t`. Em `t = 0.5` é onde o chip do rótulo e a
 * barra flutuante da aresta se apoiam.
 */
export function ptAt(geometry: EdgeGeometry, t: number): Point {
    const u = 1 - t;
    const at = (a: number, b: number, c: number, d: number): number =>
        u * u * u * a + 3 * u * u * t * b + 3 * u * t * t * c + t * t * t * d;

    return [
        at(geometry.x1, geometry.c1[0], geometry.c2[0], geometry.x2),
        at(geometry.y1, geometry.c1[1], geometry.c2[1], geometry.y2),
    ];
}

/**
 * O triângulo da ponta da seta em `(x, y)`, deitado sobre a tangente que vem de
 * `(fx, fy)` — na prática o último ponto de controle do bezier.
 */
export function head(x: number, y: number, fx: number, fy: number): ArrowHead {
    const angle = Math.atan2(y - fy, x - fx);
    const along = [
        HEAD_LENGTH * Math.cos(angle),
        HEAD_LENGTH * Math.sin(angle),
    ];
    const across = [HEAD_WIDTH * Math.sin(angle), HEAD_WIDTH * Math.cos(angle)];

    const left: Point = [x - along[0] + across[0], y - along[1] - across[1]];
    const right: Point = [x - along[0] - across[0], y - along[1] + across[1]];

    return {
        tip: [x, y],
        left,
        right,
        d: `M${x} ${y}L${left[0].toFixed(1)} ${left[1].toFixed(1)}L${right[0].toFixed(1)} ${right[1].toFixed(1)}Z`,
    };
}
