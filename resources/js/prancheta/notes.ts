/**
 * As notas da sessão (US-6.3). O limite é o mesmo que a
 * `TrainingSessionUpdateRequest` valida: passar dele no cliente só adiantaria
 * um 422 do servidor.
 */

/** O teto das notas, em caracteres. */
export const NOTES_MAX_LENGTH = 5000;

export type AcceptedNotes = {
    notes: string;
    blocked: boolean;
};

/**
 * O que o campo aceita do que foi digitado ou colado. O excesso não é cortado
 * em silêncio: `blocked` é o que faz a tela avisar antes de o texto sumir.
 */
export function acceptNotes(text: string): AcceptedNotes {
    return {
        notes: text.slice(0, NOTES_MAX_LENGTH),
        blocked: text.length > NOTES_MAX_LENGTH,
    };
}
