import { describe, expect, it } from 'vitest';
import { problemOptionsFixture } from './fixtures';
import {
    firstSentence,
    levelTone,
    NO_PROBLEM_LABEL,
    opensPickerOnLoad,
    PICKER_DELAY_MS,
    problemCards,
    problemNameOf,
    problemOf,
} from './problems';

const PROBLEMS = problemOptionsFixture();

describe('seletor de problemas', () => {
    it('every_card_carries_name_tag_and_level', () => {
        const cards = problemCards(PROBLEMS);

        expect(cards).toHaveLength(PROBLEMS.length);

        for (const card of cards) {
            expect(card.name).not.toBe('');
            expect(card.tag).not.toBe('');
            expect(card.levelName).not.toBe('');
        }

        expect(cards[0]).toEqual({
            id: 1,
            name: 'Encurtador de URL',
            summary:
                'Um serviço que recebe uma URL longa e devolve um link curto.',
            tag: 'Chave-valor · Cache',
            levelName: 'Base',
            tone: 'ok',
        });
    });

    it('level_tone_follows_the_catalog_step', () => {
        expect(problemCards(PROBLEMS).map((card) => card.tone)).toEqual([
            'ok',
            'crit',
            'warn',
        ]);
    });

    it('level_outside_the_three_steps_falls_back_to_the_nearest', () => {
        expect(levelTone(0)).toBe('ok');
        expect(levelTone(1)).toBe('ok');
        expect(levelTone(2)).toBe('warn');
        expect(levelTone(3)).toBe('crit');
        expect(levelTone(9)).toBe('crit');
    });

    it('summary_is_the_first_sentence_of_the_context', () => {
        expect(firstSentence('Uma coisa. E outra. E mais uma.')).toBe(
            'Uma coisa.',
        );
        expect(firstSentence('Sem ponto final')).toBe('Sem ponto final.');
        expect(firstSentence('')).toBe('');
    });

    it('picker_opens_by_itself_only_on_a_session_that_has_not_started', () => {
        expect(opensPickerOnLoad(null, 0)).toBe(true);
        expect(opensPickerOnLoad(3, 0)).toBe(false);
        expect(opensPickerOnLoad(null, 2)).toBe(false);
        expect(opensPickerOnLoad(3, 2)).toBe(false);
    });

    it('keeps_the_prototype_delay', () => {
        expect(PICKER_DELAY_MS).toBe(450);
    });
});

describe('problema da sessão', () => {
    it('resolves_the_problem_of_the_current_session', () => {
        expect(problemOf(PROBLEMS, 3)?.slug).toBe('drive');
        expect(problemOf(PROBLEMS, null)).toBeNull();
        expect(problemOf(PROBLEMS, 99)).toBeNull();
    });

    it('topbar_shows_the_problem_name_or_the_invitation', () => {
        expect(problemNameOf(PROBLEMS, 2)).toBe('Feed de rede social');
        expect(problemNameOf(PROBLEMS, null)).toBe(NO_PROBLEM_LABEL);
        expect(NO_PROBLEM_LABEL).toBe('Escolher problema');
    });

    it('the_three_lists_keep_the_order_they_were_stored_in', () => {
        const problem = problemOf(PROBLEMS, 1);

        expect(problem?.requirements[0]).toBe(
            'Encurtar uma URL e devolver o link curto',
        );
        expect(problem?.scale[0]).toBe('500 milhões de links novos por mês');
        expect(problem?.topics[0]).toBe(
            'Geração da chave: hash truncado com tratamento de colisão vs contador distribuído em base62',
        );
    });
});
