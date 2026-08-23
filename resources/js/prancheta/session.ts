import { sequenceModeOf } from '@/canvas/sequence';
import type { Edge, Node, SequenceMode, SessionState } from '@/canvas/types';
import type { SessionPayload } from '@/types/board';

/**
 * Os parâmetros da calculadora de capacidade, com os nomes que o servidor
 * grava na coluna JSON `estimate`.
 */
export type SessionEstimate = {
    mode: string;
    dau: number;
    act: number;
    per_month: number;
    ratio: number;
    size: number;
    peak: number;
    ret: number;
};

/** O checklist, chaveado pelo id do item do catálogo. */
export type SessionChecks = Record<string, boolean>;

/**
 * O estado inteiro da sessão de treino. Estende o `SessionState` do motor do
 * canvas de propósito: o diagrama é o mesmo objeto que o `CanvasEngine`
 * governa, e o store cuida do resto — checklist, notas, estimativa, tempo,
 * duração (US-8.1).
 */
export type SessionRecord = SessionState & {
    problemId: number | null;
    checks: SessionChecks;
    notes: string;
    estimate: SessionEstimate;
    elapsedSeconds: number;
    durationMinutes: number;
};

/**
 * O corpo do `PUT /api/sessions/{id}`, com os nomes do servidor. É o único
 * lugar do frontend que conhece esse formato: nenhum componente monta payload.
 */
export type SessionBody = {
    problem_id: number | null;
    nodes: Node[];
    edges: Edge[];
    checks: SessionChecks;
    notes: string;
    elapsed_seconds: number;
    duration_minutes: number;
    seq_mode: SequenceMode;
    estimate: SessionEstimate;
};

export function bodyFrom(state: SessionRecord): SessionBody {
    return {
        problem_id: state.problemId,
        nodes: state.nodes,
        edges: state.edges,
        checks: state.checks,
        notes: state.notes,
        elapsed_seconds: state.elapsedSeconds,
        duration_minutes: state.durationMinutes,
        seq_mode: state.seqMode,
        estimate: state.estimate,
    };
}

/**
 * A sessão como o `TrainingSessionResource` a entrega, traduzida para o
 * payload que o autosave devolve ao servidor. É aqui — e só aqui — que os
 * nomes do servidor encontram os do cliente.
 */
export function bodyFromPayload(payload: SessionPayload): SessionBody {
    return {
        problem_id: payload.problem_id ?? null,
        nodes: (payload.nodes ?? []).map((node) => ({ ...node })),
        edges: (payload.edges ?? []).map((edge) => ({ ...edge })),
        checks: { ...(payload.checks ?? {}) },
        notes: payload.notes ?? '',
        elapsed_seconds: numberOf(payload.elapsed_seconds),
        duration_minutes: numberOf(payload.duration_minutes),
        seq_mode: sequenceModeOf(payload.seq_mode),
        estimate: estimateFrom(payload.estimate),
    };
}

function estimateFrom(
    estimate: Record<string, string | number> | null,
): SessionEstimate {
    const values = estimate ?? {};

    return {
        mode: String(values.mode ?? 'user'),
        dau: numberOf(values.dau),
        act: numberOf(values.act),
        per_month: numberOf(values.per_month),
        ratio: numberOf(values.ratio),
        size: numberOf(values.size),
        peak: numberOf(values.peak),
        ret: numberOf(values.ret),
    };
}

function numberOf(value: string | number | null | undefined): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

export function recordFrom(body: SessionBody): SessionRecord {
    return {
        problemId: body.problem_id ?? null,
        nodes: body.nodes.map((node) => ({ ...node })),
        edges: body.edges.map((edge) => ({ ...edge })),
        seqMode: body.seq_mode,
        showConnectionOrder: true,
        checks: { ...body.checks },
        notes: body.notes,
        estimate: { ...body.estimate },
        elapsedSeconds: body.elapsed_seconds,
        durationMinutes: body.duration_minutes,
    };
}

/**
 * O store da sessão: a fonte única de verdade do que é persistido.
 *
 * "Sujo" não é uma bandeira que alguém levanta, e sim a comparação entre o
 * payload de agora e o último que o servidor confirmou. Sai de graça a regra
 * que a US-8.1 pede dos dois lados: qualquer alteração de campo persistido
 * suja a sessão, e pan, zoom, seleção e legenda — que não entram no payload —
 * nunca sujam.
 */
export class SessionStore {
    readonly id: number;

    state: SessionRecord;

    /** O `updated_at` da última versão que veio do servidor. */
    serverUpdatedAt: string | null;

    private savedSignature: string;

    constructor(
        id: number,
        state: SessionRecord,
        serverUpdatedAt: string | null = null,
        savedBody: SessionBody | null = null,
    ) {
        this.id = id;
        this.state = state;
        this.serverUpdatedAt = serverUpdatedAt;
        this.savedSignature = JSON.stringify(savedBody ?? bodyFrom(state));
    }

    get problemId(): number | null {
        return this.state.problemId;
    }

    get nodes(): Node[] {
        return this.state.nodes;
    }

    get edges(): Edge[] {
        return this.state.edges;
    }

    get seqMode(): SequenceMode {
        return this.state.seqMode;
    }

    get checks(): SessionChecks {
        return this.state.checks;
    }

    get notes(): string {
        return this.state.notes;
    }

    get estimate(): SessionEstimate {
        return this.state.estimate;
    }

    get elapsedSeconds(): number {
        return this.state.elapsedSeconds;
    }

    get durationMinutes(): number {
        return this.state.durationMinutes;
    }

    body(): SessionBody {
        return bodyFrom(this.state);
    }

    get signature(): string {
        return JSON.stringify(this.body());
    }

    get isDirty(): boolean {
        return this.signature !== this.savedSignature;
    }

    /**
     * Escolher o problema é gravação de sessão como qualquer outra: suja o
     * payload e sobe pelo mesmo autosave (US-2.1).
     */
    setProblemId(problemId: number | null): void {
        this.state.problemId = problemId;
    }

    setNotes(notes: string): void {
        this.state.notes = notes;
    }

    setCheck(itemId: number | string, done: boolean): void {
        this.state.checks = { ...this.state.checks, [String(itemId)]: done };
    }

    setChecks(checks: SessionChecks): void {
        this.state.checks = { ...checks };
    }

    setEstimate(estimate: Partial<SessionEstimate>): void {
        this.state.estimate = { ...this.state.estimate, ...estimate };
    }

    setElapsedSeconds(seconds: number): void {
        this.state.elapsedSeconds = Math.max(0, Math.round(seconds));
    }

    setDurationMinutes(minutes: number): void {
        this.state.durationMinutes = minutes;
    }

    setSequenceMode(mode: SequenceMode): void {
        this.state.seqMode = mode;
    }

    /** Reaplica um payload inteiro — o rascunho local que venceu no boot. */
    restore(body: SessionBody): void {
        const record = recordFrom(body);

        this.state.problemId = record.problemId;
        this.state.nodes = record.nodes;
        this.state.edges = record.edges;
        this.state.seqMode = record.seqMode;
        this.state.checks = record.checks;
        this.state.notes = record.notes;
        this.state.estimate = record.estimate;
        this.state.elapsedSeconds = record.elapsedSeconds;
        this.state.durationMinutes = record.durationMinutes;
    }

    /**
     * Marca como salvo o payload que foi enviado, não o de agora: o que mudou
     * durante o envio continua sujo e entra no próximo (US-8.3).
     */
    markSaved(
        signature: string = this.signature,
        serverUpdatedAt?: string | null,
    ): void {
        this.savedSignature = signature;

        if (serverUpdatedAt !== undefined) {
            this.serverUpdatedAt = serverUpdatedAt;
        }
    }
}
