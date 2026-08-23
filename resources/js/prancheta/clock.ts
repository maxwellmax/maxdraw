/**
 * O cronômetro do treino. As fatias do roteiro são derivadas: cada fase do
 * catálogo tem um peso, e é ele — aplicado sobre a duração escolhida — que diz
 * onde a fase começa e onde termina (US-6.1, US-2.3).
 *
 * Nenhum peso, nome de fase ou lista de durações mora aqui: tudo vem do
 * servidor. O que o cliente guarda é a aritmética.
 */

import type { SessionStore } from './session';

/** Uma fase do roteiro como o servidor a entrega (`PhaseResource`). */
export type PhaseOption = {
    id: number;
    slug: string;
    name: string;
    weight: number;
    position: number;
};

/** Uma duração de sessão do catálogo (`SessionDurationResource`). */
export type SessionDurationOption = {
    id: number;
    minutes: number;
    is_default: boolean;
};

/** A fatia de uma fase: quanto ela dura e em que segundo ela termina. */
export type PhaseBound = {
    duration: number;
    end: number;
};

export type PhaseState = 'done' | 'current' | 'todo';

/** Uma fatia da barra: a largura vem do peso, o preenchimento do decorrido. */
export type PhaseSegment = {
    weight: number;
    state: PhaseState;
    progress: number;
};

export type ClockTone = 'normal' | 'warn' | 'over';

/** A duração de uma sessão nova, quando o catálogo não disser outra. */
export const DEFAULT_DURATION_MINUTES = 45;

/** A fração da duração a partir da qual o relógio avisa que o tempo aperta. */
export const WARN_RATIO = 0.85;

export const CLOCK_TICK_MS = 1000;

/**
 * De quanto em quanto tempo o relógio correndo grava o decorrido no navegador.
 * É o que faz o tempo sobreviver ao fechamento da aba sem transformar cada
 * tique num envio ao servidor — quem cuida do envio é o debounce do autosave.
 */
export const CLOCK_PERSIST_MS = 20000;

export function totalSeconds(durationMinutes: number): number {
    return Math.max(0, durationMinutes) * 60;
}

/**
 * As fronteiras das fases para uma duração. O fim é acumulado a partir do peso
 * normalizado e a duração sai da diferença entre uma fronteira e a anterior:
 * assim a soma das fatias fecha exatamente a duração total, sem sobra de
 * arredondamento, e a última fase termina no segundo final.
 */
export function bounds(
    durationMinutes: number,
    phases: readonly PhaseOption[],
): PhaseBound[] {
    const total = totalSeconds(durationMinutes);
    const weights = phases.map((phase) => Math.max(0, phase.weight));
    const sum = weights.reduce(
        (accumulated, weight) => accumulated + weight,
        0,
    );

    let carried = 0;
    let previousEnd = 0;

    return weights.map((weight, index) => {
        carried += sum > 0 ? weight / sum : 1 / weights.length;

        const end = index === weights.length - 1 ? total : carried * total;
        const duration = end - previousEnd;

        previousEnd = end;

        return { duration, end };
    });
}

/**
 * A fase corrente: a primeira cujo fim ultrapassa o decorrido. Passado o total,
 * é a última que continua aberta — o tempo estourado não deixa o roteiro sem
 * fase. Sem fases, devolve `-1`.
 */
export function curPhase(
    elapsedSeconds: number,
    slices: readonly PhaseBound[],
): number {
    for (let index = 0; index < slices.length; index++) {
        if (elapsedSeconds < slices[index].end) {
            return index;
        }
    }

    return slices.length - 1;
}

export function phaseProgress(
    elapsedSeconds: number,
    slices: readonly PhaseBound[],
    index: number,
): number {
    const slice = slices[index];

    if (!slice || slice.duration <= 0) {
        return 0;
    }

    const start = index > 0 ? slices[index - 1].end : 0;

    return clamp((elapsedSeconds - start) / slice.duration, 0, 1);
}

/**
 * A barra de fases: uma fatia por fase, com a largura proporcional ao peso, as
 * passadas marcadas como concluídas e o progresso dentro da corrente.
 */
export function phaseSegments(
    elapsedSeconds: number,
    durationMinutes: number,
    phases: readonly PhaseOption[],
): PhaseSegment[] {
    const slices = bounds(durationMinutes, phases);
    const current = curPhase(elapsedSeconds, slices);

    return phases.map((phase, index) => ({
        weight: phase.weight,
        state: stateOf(index, current),
        progress:
            index === current
                ? phaseProgress(elapsedSeconds, slices, index)
                : 0,
    }));
}

export function isFinished(
    elapsedSeconds: number,
    durationMinutes: number,
): boolean {
    return elapsedSeconds >= totalSeconds(durationMinutes);
}

/**
 * A aparência do relógio muda duas vezes: quando o decorrido passa de 85% da
 * duração e de novo quando estoura o total (US-6.1).
 */
export function clockTone(
    elapsedSeconds: number,
    durationMinutes: number,
): ClockTone {
    const total = totalSeconds(durationMinutes);

    if (elapsedSeconds >= total) {
        return 'over';
    }

    return elapsedSeconds > total * WARN_RATIO ? 'warn' : 'normal';
}

export function formatClock(totalSeconds: number): string {
    const safe = Math.max(0, Math.round(totalSeconds));
    const minutes = Math.floor(safe / 60);
    const seconds = safe % 60;

    return `${pad(minutes)}:${pad(seconds)}`;
}

export function durationsFrom(
    options: readonly SessionDurationOption[],
): number[] {
    return options.map((option) => option.minutes);
}

export type SessionClockOptions = {
    store: SessionStore;
    persist?: () => void;
    tickMs?: number;
    persistEveryMs?: number;
};

/**
 * O relógio da sessão, sobre o mesmo estado que o autosave persiste.
 *
 * Ele nasce sempre pausado: reabrir a prancheta retoma o tempo gravado sem
 * voltar a contar sozinho. E para por conta própria quando o tempo restante
 * zera — sem apagar nada, só sinalizando o fim (US-6.1).
 */
export class SessionClock {
    running = false;

    private store: SessionStore;

    private persist: () => void;

    private tickMs: number;

    private persistEveryMs: number;

    private timer: ReturnType<typeof setInterval> | null = null;

    private sincePersistMs = 0;

    constructor(options: SessionClockOptions) {
        this.store = options.store;
        this.persist = options.persist ?? ((): void => {});
        this.tickMs = options.tickMs ?? CLOCK_TICK_MS;
        this.persistEveryMs = options.persistEveryMs ?? CLOCK_PERSIST_MS;
    }

    get elapsedSeconds(): number {
        return this.store.elapsedSeconds;
    }

    get durationMinutes(): number {
        return this.store.durationMinutes;
    }

    get finished(): boolean {
        return isFinished(this.elapsedSeconds, this.durationMinutes);
    }

    get tone(): ClockTone {
        return clockTone(this.elapsedSeconds, this.durationMinutes);
    }

    /** Com o tempo esgotado não há o que contar: só zerar ou esticar a sessão. */
    start(): void {
        if (this.running || this.finished) {
            return;
        }

        this.running = true;
        this.sincePersistMs = 0;
        this.timer = setInterval(() => this.tick(), this.tickMs);
    }

    pause(): void {
        this.running = false;
        this.clearTimer();
        this.sincePersistMs = 0;
        this.persist();
    }

    toggle(): void {
        if (this.running) {
            this.pause();

            return;
        }

        this.start();
    }

    /** Zerar para o relógio e devolve o roteiro à primeira fase. */
    reset(): void {
        this.running = false;
        this.clearTimer();
        this.sincePersistMs = 0;
        this.store.setElapsedSeconds(0);
        this.persist();
    }

    /**
     * Trocar a duração recalcula as fatias sem tocar no decorrido. Se a duração
     * nova já estiver vencida, o relógio para na hora.
     */
    setDurationMinutes(minutes: number): void {
        this.store.setDurationMinutes(minutes);

        if (this.running && this.finished) {
            this.pause();
        }
    }

    /** Desliga o temporizador sem gravar — é o desmonte da tela. */
    stop(): void {
        this.running = false;
        this.clearTimer();
    }

    private tick(): void {
        this.store.setElapsedSeconds(this.elapsedSeconds + this.tickMs / 1000);
        this.sincePersistMs += this.tickMs;

        if (this.finished) {
            this.pause();

            return;
        }

        if (this.sincePersistMs >= this.persistEveryMs) {
            this.sincePersistMs = 0;
            this.persist();
        }
    }

    private clearTimer(): void {
        if (this.timer) {
            clearInterval(this.timer);
        }

        this.timer = null;
    }
}

function stateOf(index: number, current: number): PhaseState {
    if (index < current) {
        return 'done';
    }

    return index === current ? 'current' : 'todo';
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}
