import type { SessionStorage } from './resume';
import {
    newestVersion,
    readCache,
    SESSION_CACHE_VERSION,
    serverMovedOn,
    writeCache,
} from './resume';
import type { SessionBody, SessionStore } from './session';

/** Inércia até gravar no navegador. */
export const LOCAL_SAVE_DELAY_MS = 800;

/** Inércia até enviar ao servidor. */
export const SERVER_SAVE_DELAY_MS = 3000;

/**
 * O teto do envio: sem ele, uma alteração por segundo — o cronômetro correndo
 * é exatamente isso — reiniciaria a inércia para sempre e a sessão nunca
 * subiria.
 */
export const SERVER_SAVE_MAX_WAIT_MS = 15000;

/** Espera padrão depois de um 429, quando a resposta não diz outra. */
export const RATE_LIMIT_DELAY_MS = 20000;

export const RETRY_BASE_MS = 3000;

export const RETRY_MAX_MS = 48000;

export const CONFLICT_LABEL = 'versão mais nova na nuvem';

export const RATE_LIMITED_LABEL = 'aguardando para salvar';

export type SaveState = 'dirty' | 'saving' | 'saved';

const TIMER_NAMES = ['local', 'server', 'ceiling', 'retry'] as const;

type TimerName = (typeof TIMER_NAMES)[number];

export type SessionAck = {
    updated_at: string | null;
};

export type SessionTransport = (
    id: number,
    body: SessionBody,
) => Promise<SessionAck>;

/**
 * O que o alvo do evento de descarregar precisa ter. Tipado assim para o
 * autosave não depender do DOM: quem passa a `window` é a camada Vue.
 */
export type UnloadTarget = {
    addEventListener(type: string, listener: () => void): void;
    removeEventListener(type: string, listener: () => void): void;
};

/**
 * A recusa do servidor, já com o que o autosave precisa para decidir a espera.
 */
export class SaveRejected extends Error {
    readonly status: number;

    readonly retryAfterMs: number | null;

    constructor(status: number, retryAfterMs: number | null = null) {
        super('O servidor recusou a gravação da sessão: ' + status);

        this.name = 'SaveRejected';
        this.status = status;
        this.retryAfterMs = retryAfterMs;
    }
}

export type AutosaveOptions = {
    store: SessionStore;
    storage?: SessionStorage | null;
    send: SessionTransport;
    onServerIsNewer?: () => void;
    now?: () => number;
};

export function backoffDelay(attempt: number): number {
    return Math.min(
        RETRY_BASE_MS * 2 ** Math.max(0, attempt - 1),
        RETRY_MAX_MS,
    );
}

/**
 * O duplo debounce do autosave: 800 ms de inércia para gravar no navegador,
 * 3 s para enviar ao servidor (US-8.1). Falha de rede, rate limit e versão de
 * servidor mais nova não bloqueiam, não revertem e não descartam nada — o
 * trabalho continua na tela e no navegador enquanto o envio espera (US-8.3).
 */
export class Autosave {
    label: string | null = null;

    private store: SessionStore;

    private storage: SessionStorage | null;

    private send: SessionTransport;

    private onServerIsNewer: (() => void) | null;

    private now: () => number;

    private saving = false;

    private queued = false;

    private attempt = 0;

    /** A versão do servidor sobre a qual o usuário já foi avisado. */
    private warnedFor: string | null = null;

    private timers: Record<TimerName, ReturnType<typeof setTimeout> | null> = {
        local: null,
        server: null,
        ceiling: null,
        retry: null,
    };

    constructor(options: AutosaveOptions) {
        this.store = options.store;
        this.storage = options.storage ?? null;
        this.send = options.send;
        this.onServerIsNewer = options.onServerIsNewer ?? null;
        this.now = options.now ?? (() => Date.now());
    }

    /**
     * O indicador é derivado, não avisado: some a chance de a tela dizer
     * "salvo" enquanto o payload já mudou (US-8.2).
     */
    get chip(): SaveState {
        if (this.saving) {
            return 'saving';
        }

        return this.store.isDirty ? 'dirty' : 'saved';
    }

    get isSaving(): boolean {
        return this.saving;
    }

    /** Uma alteração chegou: reinicia os dois temporizadores. */
    touch(): void {
        this.label = null;

        this.clearTimer('retry');
        this.scheduleLocal();
        this.scheduleServer();
    }

    saveLocal(): void {
        this.clearTimer('local');

        const cached = readCache(this.storage, this.store.id);

        writeCache(this.storage, {
            version: SESSION_CACHE_VERSION,
            id: this.store.id,
            savedAt: this.now(),
            serverUpdatedAt: newestVersion(
                this.store.serverUpdatedAt,
                cached?.serverUpdatedAt ?? null,
            ),
            body: this.store.body(),
        });
    }

    /**
     * Envia agora, sem esperar a inércia — é o que o botão Salvar faz.
     */
    async save(): Promise<void> {
        this.clearTimers();

        if (this.saving) {
            this.queued = true;

            return;
        }

        const cached = readCache(this.storage, this.store.id);

        this.saveLocal();

        if (!this.store.isDirty) {
            return;
        }

        if (serverMovedOn(cached, this.store.serverUpdatedAt)) {
            this.warnServerIsNewer(cached?.serverUpdatedAt ?? null);

            return;
        }

        const signature = this.store.signature;

        this.saving = true;

        try {
            const ack = await this.send(this.store.id, this.store.body());

            this.store.markSaved(signature, ack.updated_at);
            this.attempt = 0;
            this.label = null;
            this.saving = false;
            this.saveLocal();
        } catch (error) {
            this.saving = false;
            this.scheduleRetry(error);

            return;
        }

        if (this.queued) {
            this.queued = false;

            await this.save();

            return;
        }

        if (this.store.isDirty) {
            this.touch();
        }
    }

    /**
     * Liga a última gravação local ao fechamento da aba. `pagehide` entra
     * junto porque é o único dos dois que o navegador do celular garante.
     */
    bindUnload(target: UnloadTarget): () => void {
        const handler = (): void => this.saveLocal();

        target.addEventListener('beforeunload', handler);
        target.addEventListener('pagehide', handler);

        return () => {
            target.removeEventListener('beforeunload', handler);
            target.removeEventListener('pagehide', handler);
        };
    }

    stop(): void {
        this.clearTimers();
    }

    private scheduleLocal(): void {
        this.clearTimer('local');

        this.timers.local = setTimeout(
            () => this.saveLocal(),
            LOCAL_SAVE_DELAY_MS,
        );
    }

    private scheduleServer(): void {
        this.clearTimer('server');

        this.timers.server = setTimeout(
            () => void this.save(),
            SERVER_SAVE_DELAY_MS,
        );

        if (!this.timers.ceiling) {
            this.timers.ceiling = setTimeout(
                () => void this.save(),
                SERVER_SAVE_MAX_WAIT_MS,
            );
        }
    }

    /**
     * O envio falhou: a sessão continua suja e o autosave tenta de novo. Rate
     * limit espera o que o servidor pedir; o resto dobra a espera a cada
     * tentativa (US-8.3).
     */
    private scheduleRetry(error: unknown): void {
        const rejection = error instanceof SaveRejected ? error : null;
        const rateLimited = rejection?.status === 429;

        if (rateLimited) {
            this.label = RATE_LIMITED_LABEL;
        } else {
            this.attempt++;
            this.label = null;
        }

        const delay = rateLimited
            ? (rejection?.retryAfterMs ?? RATE_LIMIT_DELAY_MS)
            : backoffDelay(this.attempt);

        this.timers.retry = setTimeout(() => void this.save(), delay);
    }

    private warnServerIsNewer(version: string | null): void {
        this.label = CONFLICT_LABEL;

        if (this.warnedFor === version) {
            return;
        }

        this.warnedFor = version;
        this.onServerIsNewer?.();
    }

    private clearTimer(which: TimerName): void {
        const timer = this.timers[which];

        if (timer) {
            clearTimeout(timer);
        }

        this.timers[which] = null;
    }

    private clearTimers(): void {
        for (const which of TIMER_NAMES) {
            this.clearTimer(which);
        }
    }
}
