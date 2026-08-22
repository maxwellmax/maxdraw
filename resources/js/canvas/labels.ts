import { MAX_LABEL_LENGTH } from './limits';

/**
 * O rótulo como ele entra no diagrama: aparado, cortado no limite e, quando o
 * usuário apaga tudo, de volta ao nome curto do componente.
 */
export function normalizeLabel(raw: string, fallback: string): string {
    const label = raw.trim().slice(0, MAX_LABEL_LENGTH);

    return label === '' ? fallback.slice(0, MAX_LABEL_LENGTH) : label;
}

/**
 * Quantos caracteres ainda cabem, considerando o que a digitação vai substituir.
 * A camada Vue usa isto para bloquear o excedente antes de ele entrar no campo.
 */
export function labelRoomLeft(current: string, replacing = 0): number {
    return MAX_LABEL_LENGTH - (current.length - replacing);
}
