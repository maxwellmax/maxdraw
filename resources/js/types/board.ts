import type { CatalogCategory } from '@/canvas/catalog';
import type { LinkType } from '@/canvas/links';
import type { SequenceModeOption } from '@/canvas/sequence';
import type { Edge, Node } from '@/canvas/types';
import type { SessionDurationOption } from '@/prancheta/clock';
import type { RoteiroPhase } from '@/prancheta/roteiro';

/**
 * A sessão como o `TrainingSessionResource` a entrega. Os nomes são os do
 * servidor: o motor do canvas trabalha com `seqMode`, a resposta traz
 * `seq_mode`, e a conversão acontece na página.
 */
export type SessionPayload = {
    id: number;
    problem_id: number | null;
    duration_minutes: number;
    seq_mode: string;
    elapsed_seconds: number;
    notes: string | null;
    nodes: Node[];
    edges: Edge[];
    checks: Record<string, boolean>;
    estimate: Record<string, string | number>;
    last_opened_at: string | null;
    updated_at: string | null;
};

/**
 * O catálogo do `CatalogService`. As listas que ainda não têm consumidor ficam
 * como `unknown[]` — cada fase tipa a sua quando passa a lê-la.
 */
export type BoardCatalog = {
    problems: unknown[];
    component_categories: CatalogCategory[];
    link_types: LinkType[];
    phases: RoteiroPhase[];
    sequence_modes: SequenceModeOption[];
    session_durations: SessionDurationOption[];
    estimate_modes: unknown[];
};
