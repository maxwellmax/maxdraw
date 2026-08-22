/**
 * Os limites do diagrama. São os mesmos números que a `TrainingSessionUpdateRequest`
 * valida no servidor: o motor recusa antes de sujar a sessão, o servidor recusa
 * de novo porque o cliente não é autoridade sobre isso.
 */
export const MAX_NODES = 200;

export const MAX_EDGES = 400;

export const MAX_LABEL_LENGTH = 60;
