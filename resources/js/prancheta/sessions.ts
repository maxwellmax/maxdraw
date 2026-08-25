/**
 * A folha de sessões: listar, abrir, criar e excluir os treinos salvos
 * (US-11.1, US-11.2, US-11.3).
 *
 * Nenhuma requisição mora aqui — o módulo faz o arranjo da lista (data,
 * problema, duração escolhida, tempo usado e qual é a corrente) e guarda a
 * regra da confirmação de exclusão. Quem fala com o servidor é
 * `lib/sessionTransport.ts`, e quem orquestra a troca é a página.
 */

import type { Node } from '@/canvas/types';
import { formatClock } from './clock';
import type { ProblemOption } from './problems';
import { problemOf } from './problems';

/**
 * Uma sessão salva como o `TrainingSessionResource` a entrega na listagem — o
 * mesmo recurso da sessão corrente, do qual a folha usa só o cabeçalho.
 */
export type SessionSummary = {
    id: number;
    problem_id: number | null;
    name: string | null;
    duration_minutes: number;
    elapsed_seconds: number;
    nodes: Node[];
    last_opened_at: string | null;
};

/**
 * Uma linha da folha, já pronta para a tela. `title` e `metaLabel` chegam
 * montados de propósito: quem decide se o título é o nome da sessão ou o do
 * problema é o arranjo, e o componente só imprime.
 */
export type SessionRow = {
    id: number;
    name: string | null;
    title: string;
    metaLabel: string;
    problemName: string;
    date: string;
    durationLabel: string;
    elapsedLabel: string;
    blockCount: number;
    current: boolean;
};

/** O que a linha escreve quando a sessão não tem problema escolhido. */
export const FREE_BOARD_LABEL = 'Prancheta livre';

/**
 * O teto do nome da sessão, em caracteres, medido depois do aparo. Espelha o
 * `MAX_SESSION_NAME` da `TrainingSessionUpdateRequest`: passar dele no cliente
 * só adiantaria um 422 do servidor. O excesso é avisado, nunca cortado.
 */
export const SESSION_NAME_MAX_LENGTH = 60;

export const EMPTY_LIST_MESSAGE = 'Nenhuma sessão ainda.';

/** O selo que identifica a sessão corrente na lista. */
export const CURRENT_BADGE = 'corrente';

export const DELETE_LABEL = 'Apagar';

export const CONFIRM_DELETE_LABEL = 'Confirmar';

/** Data que o servidor não mandou, ou mandou ilegível. */
export const UNKNOWN_DATE = '—';

/** Os meses abreviados em pt-BR, como o protótipo os escreve. */
export const MONTH_ABBREVIATIONS = [
    'jan',
    'fev',
    'mar',
    'abr',
    'mai',
    'jun',
    'jul',
    'ago',
    'set',
    'out',
    'nov',
    'dez',
] as const;

/**
 * A data da sessão no fuso do usuário. Escrita à mão em vez de `Intl` de
 * propósito: o mês abreviado do protótipo é o mesmo em qualquer navegador, e
 * o formato não pode depender da tabela de locales que o runtime carregou.
 */
export function formatSessionDate(isoDate: string | null): string {
    const time = isoDate ? Date.parse(isoDate) : Number.NaN;

    if (Number.isNaN(time)) {
        return UNKNOWN_DATE;
    }

    const date = new Date(time);

    return [
        String(date.getDate()).padStart(2, '0'),
        MONTH_ABBREVIATIONS[date.getMonth()],
        date.getFullYear(),
    ].join(' ');
}

export function formatDurationChoice(minutes: number): string {
    return `${minutes} min`;
}

export function sessionCountLabel(count: number): string {
    return count === 1
        ? '1 sessão salva neste espaço.'
        : `${count} sessões salvas neste espaço.`;
}

/**
 * Da mais recente para a mais antiga. O servidor já entrega assim; a folha
 * repete a ordenação para que uma lista remontada no cliente — depois de uma
 * exclusão, por exemplo — não dependa disso.
 */
export function byRecency(left: SessionSummary, right: SessionSummary): number {
    const difference =
        timeOf(right.last_opened_at) - timeOf(left.last_opened_at);

    return difference === 0 ? right.id - left.id : difference;
}

export function sessionRows(
    sessions: readonly SessionSummary[],
    problems: readonly ProblemOption[],
    currentId: number | null,
): SessionRow[] {
    return [...sessions].sort(byRecency).map((session) => ({
        id: session.id,
        name: session.name,
        title: sessionTitle(session, problems),
        metaLabel: sessionMetaLabel(session, problems),
        problemName: problemNameOf(session, problems),
        date: formatSessionDate(session.last_opened_at),
        durationLabel: formatDurationChoice(session.duration_minutes),
        elapsedLabel: formatClock(session.elapsed_seconds),
        blockCount: session.nodes.length,
        current: session.id === currentId,
    }));
}

/**
 * O nome que o usuário deu à sessão, aparado nas pontas — nulo quando não deu
 * nenhum ou quando só sobraram espaços, a mesma normalização que a
 * `TrainingSessionUpdateRequest` faz antes de gravar.
 */
export function sessionName(name: string | null | undefined): string | null {
    const trimmed = (name ?? '').trim();

    return trimmed === '' ? null : trimmed;
}

function problemNameOf(
    session: SessionSummary,
    problems: readonly ProblemOption[],
): string {
    return problemOf(problems, session.problem_id)?.name ?? FREE_BOARD_LABEL;
}

/**
 * O nome da sessão manda no título; sem nome, o problema — que já cai em
 * `FREE_BOARD_LABEL` quando não há problema escolhido (US-11.1).
 */
function sessionTitle(
    session: SessionSummary,
    problems: readonly ProblemOption[],
): string {
    return sessionName(session.name) ?? problemNameOf(session, problems);
}

/**
 * Os metadados da linha. O problema só entra — e entra primeiro — quando o
 * título é o nome da sessão: sem nome ele já é o título e repeti-lo aqui seria
 * ruído.
 */
function sessionMetaLabel(
    session: SessionSummary,
    problems: readonly ProblemOption[],
): string {
    const tokens = [
        formatSessionDate(session.last_opened_at),
        formatDurationChoice(session.duration_minutes),
        formatClock(session.elapsed_seconds),
        `${session.nodes.length} blocos`,
    ];

    return (
        sessionName(session.name) === null
            ? tokens
            : [problemNameOf(session, problems), ...tokens]
    ).join(' · ');
}

/**
 * O que um clique no botão de excluir deve fazer. A confirmação é explícita e
 * anterior a qualquer requisição: o primeiro clique arma o pedido, e só o
 * segundo — no mesmo botão — manda excluir (US-11.3).
 */
export type DeleteIntent =
    { action: 'arm'; id: number } | { action: 'delete'; id: number };

export function deleteIntent(armed: number | null, id: number): DeleteIntent {
    return armed === id ? { action: 'delete', id } : { action: 'arm', id };
}

export function deleteLabel(armed: number | null, id: number): string {
    return armed === id ? CONFIRM_DELETE_LABEL : DELETE_LABEL;
}

function timeOf(isoDate: string | null): number {
    const parsed = isoDate ? Date.parse(isoDate) : Number.NaN;

    return Number.isNaN(parsed) ? 0 : parsed;
}
