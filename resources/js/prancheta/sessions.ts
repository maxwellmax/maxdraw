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
    duration_minutes: number;
    elapsed_seconds: number;
    nodes: Node[];
    last_opened_at: string | null;
};

/** Uma linha da folha, já pronta para a tela. */
export type SessionRow = {
    id: number;
    problemName: string;
    date: string;
    durationLabel: string;
    elapsedLabel: string;
    blockCount: number;
    current: boolean;
};

/** O que a linha escreve quando a sessão não tem problema escolhido. */
export const FREE_BOARD_LABEL = 'Prancheta livre';

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
        problemName:
            problemOf(problems, session.problem_id)?.name ?? FREE_BOARD_LABEL,
        date: formatSessionDate(session.last_opened_at),
        durationLabel: formatDurationChoice(session.duration_minutes),
        elapsedLabel: formatClock(session.elapsed_seconds),
        blockCount: session.nodes.length,
        current: session.id === currentId,
    }));
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
