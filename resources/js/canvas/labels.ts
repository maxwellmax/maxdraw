import { MAX_LABEL_LENGTH } from './limits';

/**
 * O rótulo como ele entra no diagrama: aparado e cortado no limite. É o que a
 * seta grava — vazio ali é um estado legítimo, a seta simplesmente fica sem
 * rótulo (US-4.2).
 */
export function clampLabel(raw: string): string {
    return raw.trim().slice(0, MAX_LABEL_LENGTH);
}

/**
 * O rótulo do bloco: o mesmo aparo, mais o nome curto do componente de volta
 * quando o usuário apaga tudo.
 */
export function normalizeLabel(raw: string, fallback: string): string {
    const label = clampLabel(raw);

    return label === '' ? clampLabel(fallback) : label;
}

/**
 * Quantos caracteres ainda cabem, considerando o que a digitação vai substituir.
 * A camada Vue usa isto para bloquear o excedente antes de ele entrar no campo.
 */
export function labelRoomLeft(current: string, replacing = 0): number {
    return MAX_LABEL_LENGTH - (current.length - replacing);
}
