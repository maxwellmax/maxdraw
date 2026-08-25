import type { CatalogCategory } from '@/canvas/catalog';
import type { LinkType } from '@/canvas/links';
import type { Edge, Node } from '@/canvas/types';
import type { SessionDurationOption } from '@/prancheta/clock';
import type { EstimateModeOption } from '@/prancheta/estimate';
import type { ProblemOption } from '@/prancheta/problems';
import type { RoteiroPhase } from '@/prancheta/roteiro';

/**
 * A sessão como o `TrainingSessionResource` a entrega. Os nomes são os do
 * servidor: o motor do canvas trabalha com `showConnectionOrder`, a resposta
 * traz `show_connection_order`, e a conversão mora no store da sessão.
 *
 * A bandeira é opcional enquanto o servidor não a devolve: até lá o cliente
 * assume acesa, que é o padrão de toda sessão nova. `name` é opcional pelo
 * mesmo motivo: um rascunho gravado antes da coluna existir não o traz.
 */
export type SessionPayload = {
    id: number;
    problem_id: number | null;
    name?: string | null;
    duration_minutes: number;
    show_connection_order?: boolean;
    elapsed_seconds: number;
    notes: string | null;
    nodes: Node[];
    edges: Edge[];
    checks: Record<string, boolean>;
    estimate: Record<string, string | number>;
    last_opened_at: string | null;
    updated_at: string | null;
};

/** O catálogo do `CatalogService`, como a prancheta o consome. */
export type BoardCatalog = {
    problems: ProblemOption[];
    component_categories: CatalogCategory[];
    link_types: LinkType[];
    phases: RoteiroPhase[];
    session_durations: SessionDurationOption[];
    estimate_modes: EstimateModeOption[];
};
