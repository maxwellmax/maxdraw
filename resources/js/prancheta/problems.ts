/**
 * O enunciado do treino: o catálogo dos 14 problemas como a folha do seletor e
 * a aba Enunciado o consomem (US-2.1, US-10.1).
 *
 * Nenhum texto de problema mora aqui — nome, etiqueta, nível, contexto e as
 * três listas chegam do servidor em `catalog.problems`, já na ordem gravada em
 * `problem_items`. O que este módulo faz é o arranjo: achar o problema da
 * sessão, resumir o contexto no cartão e dizer quando a folha abre sozinha.
 *
 * O gabarito (`topics`) trafega junto do resto, mas quem decide mostrá-lo é o
 * usuário: nada neste módulo o compara com o que foi desenhado.
 */

/** Um problema do catálogo como o `ProblemResource` o entrega. */
export type ProblemOption = {
    id: number;
    slug: string;
    name: string;
    tag: string;
    level: string;
    level_name: string;
    level_position: number;
    context: string;
    requirements: string[];
    scale: string[];
    topics: string[];
};

/** Os três degraus de dificuldade, na cor que o protótipo dá a cada um. */
export const LEVEL_TONES = ['ok', 'warn', 'crit'] as const;

export type LevelTone = (typeof LEVEL_TONES)[number];

/** Um cartão da folha do seletor. */
export type ProblemCard = {
    id: number;
    name: string;
    summary: string;
    tag: string;
    levelName: string;
    tone: LevelTone;
};

/** O que a barra superior escreve quando a sessão ainda não tem problema. */
export const NO_PROBLEM_LABEL = 'Escolher problema';

export const TOPICS_SUMMARY =
    'Tópicos que este problema cobra — abra só depois de terminar';

/** O atraso do protótipo: a folha não pode piscar junto com a prancheta. */
export const PICKER_DELAY_MS = 450;

/**
 * A folha abre sozinha só na sessão que ainda não começou — sem problema e sem
 * nada desenhado. Quem já desenhou na prancheta livre não é interrompido.
 */
export function opensPickerOnLoad(
    problemId: number | null,
    nodeCount: number,
): boolean {
    return problemId === null && nodeCount === 0;
}

export function problemOf(
    problems: readonly ProblemOption[],
    problemId: number | null,
): ProblemOption | null {
    return problems.find((problem) => problem.id === problemId) ?? null;
}

export function problemNameOf(
    problems: readonly ProblemOption[],
    problemId: number | null,
): string {
    return problemOf(problems, problemId)?.name ?? NO_PROBLEM_LABEL;
}

/**
 * O degrau vira tom pela posição do nível no catálogo, que é o `lv` do
 * protótipo. Nível fora dos três degraus cai no mais próximo em vez de ficar
 * sem cor.
 */
export function levelTone(position: number): LevelTone {
    const step = Math.min(
        Math.max(Math.trunc(position), 1),
        LEVEL_TONES.length,
    );

    return LEVEL_TONES[step - 1];
}

/**
 * A primeira frase do contexto, que é o resumo do cartão. Contexto sem ponto
 * final entra inteiro, e não sobra ponto solto quando não há o que resumir.
 */
export function firstSentence(context: string): string {
    const head = context.split('.')[0].trim();

    return head === '' ? '' : head + '.';
}

export function problemCards(
    problems: readonly ProblemOption[],
): ProblemCard[] {
    return problems.map((problem) => ({
        id: problem.id,
        name: problem.name,
        summary: firstSentence(problem.context),
        tag: problem.tag,
        levelName: problem.level_name,
        tone: levelTone(problem.level_position),
    }));
}
