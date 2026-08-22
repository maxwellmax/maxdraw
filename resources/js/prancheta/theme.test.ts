import { describe, expect, it } from 'vitest';
import { isTheme, nextTheme, resolveTheme } from './theme';

describe('nextTheme', () => {
    it('sai do sistema para o oposto da preferência do sistema', () => {
        expect(nextTheme(null, true)).toBe('light');
        expect(nextTheme(null, false)).toBe('dark');
    });

    it('alterna entre claro e escuro depois da primeira escolha', () => {
        expect(nextTheme('dark', true)).toBe('light');
        expect(nextTheme('light', true)).toBe('dark');
    });

    it('nunca volta para "sem preferência"', () => {
        let current = nextTheme(null, false);

        for (let step = 0; step < 6; step++) {
            current = nextTheme(current, false);
            expect(current).not.toBeNull();
        }
    });

    it('cicla claro e escuro alternadamente a partir de qualquer sistema', () => {
        const systemPrefersDark = true;
        const first = nextTheme(null, systemPrefersDark);
        const second = nextTheme(first, systemPrefersDark);
        const third = nextTheme(second, systemPrefersDark);

        expect([first, second, third]).toEqual(['light', 'dark', 'light']);
    });
});

describe('resolveTheme', () => {
    it('segue o sistema quando não há preferência gravada', () => {
        expect(resolveTheme(null, true)).toBe('dark');
        expect(resolveTheme(null, false)).toBe('light');
    });

    it('ignora o sistema quando há preferência gravada', () => {
        expect(resolveTheme('light', true)).toBe('light');
        expect(resolveTheme('dark', false)).toBe('dark');
    });
});

describe('isTheme', () => {
    it('aceita só os dois temas conhecidos', () => {
        expect(isTheme('light')).toBe(true);
        expect(isTheme('dark')).toBe(true);
        expect(isTheme('system')).toBe(false);
        expect(isTheme(null)).toBe(false);
    });
});
