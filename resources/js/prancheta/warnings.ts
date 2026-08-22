/**
 * Os avisos que a prancheta emite. Todos passam pelo toast — é o canal único
 * de aviso da aplicação, para o usuário não perder um recado no meio do treino.
 */
export const BOARD_WARNINGS = {
    nodeLimitReached:
        'Limite de 200 blocos atingido. Apague algum antes de colocar outro.',
    edgeLimitReached:
        'Limite de 400 setas atingido. Apague alguma antes de ligar outra.',
    exportEmptyCanvas: 'Canvas vazio — não há nada para exportar.',
    serverVersionIsNewer:
        'A versão salva no servidor é mais nova que a desta aba. Recarregue antes de salvar.',
} as const;

export type BoardWarning = keyof typeof BOARD_WARNINGS;
