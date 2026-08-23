import { http } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/TrainingSessionController';
import type { SessionAck } from '@/prancheta/autosave';
import { SaveRejected } from '@/prancheta/autosave';
import type { SessionBody } from '@/prancheta/session';

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
