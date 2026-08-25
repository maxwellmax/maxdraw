import { http } from '@inertiajs/vue3';
import {
    destroy,
    index,
    open,
    store,
    update,
} from '@/actions/App/Http/Controllers/TrainingSessionController';
import type { SessionAck } from '@/prancheta/autosave';
import { SaveRejected } from '@/prancheta/autosave';
import type { SessionBody } from '@/prancheta/session';
import type { SessionSummary } from '@/prancheta/sessions';

/**
 * O envio do autosave. Vai pelo cliente HTTP do Inertia de propósito: é ele
 * que manda o cookie de sessão e devolve o token CSRF no cabeçalho
 * `X-XSRF-TOKEN`. Não há token de API neste projeto (US-8.1).
 */
export async function sendSessionState(
    id: number,
    body: SessionBody,
): Promise<SessionAck> {
    try {
        const response = await http.getClient().request({
            method: 'put',
            url: update.url(id),
            data: body,
            headers: { Accept: 'application/json' },
        });

        return ackFrom(response.data);
    } catch (error) {
        throw rejectionFrom(error);
    }
}

/**
 * O envio do nome da sessão. Vai pelo mesmo `PUT` do autosave, mas com corpo
 * próprio — só `{ name }`: o nome fora do `SessionBody` é o que o mantém longe
 * do que suja a sessão e do clobber entre abas (US-11.1).
 */
export async function renameSession(
    id: number,
    body: { name: string | null },
): Promise<SessionAck> {
    try {
        const response = await http.getClient().request({
            method: 'put',
            url: update.url(id),
            data: body,
            headers: { Accept: 'application/json' },
        });

        return ackFrom(response.data);
    } catch (error) {
        throw rejectionFrom(error);
    }
}

function ackFrom(data: string): SessionAck {
    try {
        const parsed = JSON.parse(data) as Partial<SessionAck>;

        return { updated_at: parsed.updated_at ?? null };
    } catch {
        return { updated_at: null };
    }
}

/**
 * Sem resposta, a falha é de rede — status zero, que o autosave trata como
 * tentativa a repetir com recuo. O `Retry-After` do 429 vem em segundos.
 */
function rejectionFrom(error: unknown): SaveRejected {
    const response =
        error && typeof error === 'object' && 'response' in error
            ? (
                  error as {
                      response?: {
                          status?: number;
                          headers?: Record<string, string>;
                      };
                  }
              ).response
            : undefined;

    const retryAfter = Number(response?.headers?.['retry-after']);

    return new SaveRejected(
        response?.status ?? 0,
        Number.isFinite(retryAfter) && retryAfter > 0
            ? retryAfter * 1000
            : null,
    );
}

/**
 * A lista de sessões do usuário autenticado, da mais recente para a mais
 * antiga — o servidor escopa ao dono e ordena (US-11.1).
 */
export async function fetchSessions(): Promise<SessionSummary[]> {
    const body = await requestSession('get', index.url());

    return (JSON.parse(body) as { data?: SessionSummary[] }).data ?? [];
}

/**
 * Abre uma sessão salva: ela passa a ser a corrente. Quem restaura o estado é
 * o recarregamento da prancheta, pelo mesmo caminho do boot.
 */
export async function openSession(id: number): Promise<void> {
    await requestSession('post', open.url(id));
}

/** Cria a sessão vazia que passa a ser a corrente (US-11.2). */
export async function createSession(): Promise<void> {
    await requestSession('post', store.url());
}

/** Exclui a sessão e todo o conteúdo dela (US-11.3). */
export async function deleteSession(id: number): Promise<void> {
    await requestSession('delete', destroy.url(id));
}

async function requestSession(
    method: 'get' | 'post' | 'delete',
    url: string,
): Promise<string> {
    const response = await http.getClient().request({
        method,
        url,
        headers: { Accept: 'application/json' },
    });

    return response.data;
}
