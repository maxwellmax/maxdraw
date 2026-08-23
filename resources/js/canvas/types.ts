/**
 * Os tipos do diagrama. Este pacote é o motor do canvas: TypeScript puro, sem
 * Vue, sem Inertia e sem tocar no DOM — a camada Vue apenas monta e alimenta.
 */

export type Node = {
    id: string;
    type: string;
    label: string;
    x: number;
    y: number;
};

/**
 * Uma ligação do diagrama. `order` é a ordem explícita da conexão na sequência
 * única do desenho: a posição da aresta no array `edges` deixou de ter
 * significado semântico, e `null` quer dizer "fora da sequência".
 */
export type Edge = {
    id: string;
    from: string;
    to: string;
    kind: string | null;
    label: string;
    dashed: boolean;
    bidir: boolean;
    order: number | null;
};

/** As duas bandeiras que o usuário alterna à mão, independentes do tipo. */
export type EdgeFlag = 'dashed' | 'bidir';

export type SequenceMode = 'off' | 'out' | 'flow';

/**
 * O estado da sessão que o motor governa. Notas, checklist, estimativa e tempo
 * decorrido são persistidos junto da sessão, mas não pertencem ao motor: quem
 * cuida deles é o store da sessão.
 */
export type SessionState = {
    nodes: Node[];
    edges: Edge[];
    seqMode: SequenceMode;
};

export type View = {
    x: number;
    y: number;
    k: number;
};

export type Point = [number, number];

export type Size = {
    width: number;
    height: number;
};

/**
 * A altura medida de cada bloco, por id. O rótulo de mais de uma linha estica o
 * bloco, e a aresta precisa da altura real para encostar na borda certa — só a
 * camada Vue sabe medir, então ela devolve o mapa para o motor.
 */
export type NodeHeights = Record<string, number>;

export type SelectionKind = 'node' | 'edge';

export type Selection = {
    kind: SelectionKind;
    id: string;
};

/**
 * O arrasto de ligação em curso: sai de uma das bolinhas do bloco de origem e
 * segue o cursor até soltar. `target` é o bloco sob o ponteiro, nunca o próprio
 * bloco de origem (US-3.3).
 */
export type LinkDrag = {
    from: string;
    x: number;
    y: number;
    target: string | null;
};

export type EdgeGeometry = {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    c1: Point;
    c2: Point;
    d: string;
};

export type ArrowHead = {
    tip: Point;
    left: Point;
    right: Point;
    d: string;
};

/**
 * O diagrama sozinho — é o que a pilha de desfazer guarda, e só isso: view,
 * seleção e modo de numeração ficam de fora por contrato (US-3.5).
 */
export type DiagramSnapshot = {
    nodes: Node[];
    edges: Edge[];
};

/**
 * Por que uma inserção foi recusada. As duas de limite têm o nome do aviso
 * correspondente, para o chamador repassá-las direto ao toast; as outras duas
 * são recusa silenciosa — ligar um bloco nele mesmo não é erro para avisar.
 */
export type RefusalReason =
    | 'nodeLimitReached'
    | 'edgeLimitReached'
    | 'invalidLink'
    | 'unknownComponent';

export type Refused = {
    ok: false;
    reason: RefusalReason;
};

export type Accepted<T> = {
    ok: true;
    value: T;
};

export type MutationResult<T> = Accepted<T> | Refused;
