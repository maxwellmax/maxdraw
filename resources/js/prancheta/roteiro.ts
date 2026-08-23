/**
 * O roteiro do treino: as cinco fases do catálogo com os itens marcáveis de
 * cada uma (US-6.2).
 *
 * Nenhum item mora aqui — fases e itens chegam do servidor, e a marcação é um
 * mapa chaveado pelo `checklist_items.id`, o que faz uma reordenação do seeder
 * não reinterpretar o que já foi marcado. O que o cliente guarda é o arranjo:
 * qual fase está aberta, quanto de cada uma já foi feito e quantos minutos a
 * fatia corrente reserva para ela.
 */

import type { PhaseOption, PhaseState } from './clock';
import { bounds, curPhase, phaseStateOf } from './clock';
import type { SessionChecks } from './session';

/** Um item do checklist como o `ChecklistItemResource` o entrega. */
export type ChecklistItemOption = {
    id: number;
    content: string;
};

/** Uma fase do catálogo com os itens que ela cobra. */
export type RoteiroPhase = PhaseOption & {
    checklist_items: ChecklistItemOption[];
};

/** Um item do roteiro na tela, já com a marcação da sessão aplicada. */
export type ChecklistLine = ChecklistItemOption & {
    checked: boolean;
};

/** Uma fase do acordeão: cabeçalho, progresso e itens. */
export type PhaseRow = {
    index: number;
    number: number;
    name: string;
    minutes: number;
    state: PhaseState;
    open: boolean;
    done: number;
    total: number;
    items: ChecklistLine[];
};

/**
 * Enquanto o usuário não abrir uma fase à mão, o acordeão segue o cronômetro —
 * é o que faz a fase corrente abrir sozinha na virada.
 */
export const FOLLOW_CURRENT = null;

/** Nenhuma fase aberta: o que sobra quando o usuário fecha a que estava aberta. */
export const ALL_COLLAPSED = -1;

export type PhaseChoice = number | null;

/** Ausência é desmarcado: o mapa só precisa carregar o que foi marcado. */
export function isChecked(
    checks: SessionChecks,
    itemId: number | string,
): boolean {
    return checks[String(itemId)] === true;
}

export function openIndexOf(choice: PhaseChoice, current: number): number {
    return choice === FOLLOW_CURRENT ? current : choice;
}

/**
 * Clicar no cabeçalho da fase aberta a fecha; em qualquer outra, abre aquela.
 * A escolha é do usuário e prevalece até a próxima virada de fase, que devolve
 * o acordeão ao `FOLLOW_CURRENT`.
 */
export function toggleChoice(
    choice: PhaseChoice,
    current: number,
    index: number,
): number {
    return openIndexOf(choice, current) === index ? ALL_COLLAPSED : index;
}

/**
 * O acordeão inteiro, derivado do catálogo, da marcação e do tempo: os minutos
 * de cada fase saem da mesma fatia que a barra do cronômetro usa, então trocar
 * a duração reescreve o roteiro sem tocar no que já foi marcado (US-6.1).
 */
export function phaseRows(
    phases: readonly RoteiroPhase[],
    checks: SessionChecks,
    elapsedSeconds: number,
    durationMinutes: number,
    choice: PhaseChoice = FOLLOW_CURRENT,
): PhaseRow[] {
    const slices = bounds(durationMinutes, phases);
    const current = curPhase(elapsedSeconds, slices);
    const open = openIndexOf(choice, current);

    return phases.map((phase, index) => {
        const items = linesOf(phase, checks);

        return {
            index,
            number: index + 1,
            name: phase.name,
            minutes: Math.round(slices[index].duration / 60),
            state: phaseStateOf(index, current),
            open: index === open,
            done: items.filter((item) => item.checked).length,
            total: items.length,
            items,
        };
    });
}

function linesOf(phase: RoteiroPhase, checks: SessionChecks): ChecklistLine[] {
    return phase.checklist_items.map((item) => ({
        ...item,
        checked: isChecked(checks, item.id),
    }));
}
