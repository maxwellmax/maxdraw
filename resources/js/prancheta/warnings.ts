/**
 * Os avisos que a prancheta emite. Todos passam pelo toast — é o canal único
 * de aviso da aplicação, para o usuário não perder um recado no meio do treino.
 */
export const BOARD_WARNINGS = {
    nodeLimitReached:
        'Limite de 200 blocos atingido. Apague algum antes de colocar outro.',
    edgeLimitReached:
        'Limite de 400 setas atingido. Apague alguma antes de ligar outra.',
    notesLimitReached:
        'As notas da sessão têm no máximo 5.000 caracteres — o que passou disso não entrou.',
    exportEmptyCanvas: 'Canvas vazio — não há nada para exportar.',
    serverVersionIsNewer:
        'A versão salva no servidor é mais nova que a desta aba. Recarregue antes de salvar.',
    sessionListFailed:
        'Não foi possível carregar suas sessões. Tente abrir a folha de novo.',
    sessionSwitchFailed:
        'Não foi possível trocar de sessão. O treino desta prancheta continua aqui.',
    sessionDeleteFailed:
        'Não foi possível excluir a sessão. Tente de novo em instantes.',
} as const;

export type BoardWarning = keyof typeof BOARD_WARNINGS;
