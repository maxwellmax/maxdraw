import { describe, expect, it } from 'vitest';
import { exportFileName, FREE_BOARD_NAME, SVG_MIME_TYPE } from './export';

describe('exportFileName', () => {
    it('svg_file_is_named_after_the_chosen_problem_slug', () => {
        expect(exportFileName({ slug: 'typeahead' })).toBe('typeahead.svg');
        expect(exportFileName({ slug: 'url' })).toBe('url.svg');
    });

    it('svg_file_falls_back_to_prancheta_without_a_problem', () => {
        expect(exportFileName(null)).toBe(`${FREE_BOARD_NAME}.svg`);
        expect(SVG_MIME_TYPE).toBe('image/svg+xml;charset=utf-8');
    });
});
