import { describe, expect, it } from 'vitest';
import { isLegendOpen, LEGEND_STORAGE_KEY, legendStorageValue } from './legend';

describe('preferência de recolhimento da legenda', () => {
    it('legend_defaults_to_open_without_a_stored_preference', () => {
        expect(isLegendOpen(null)).toBe(true);
        expect(isLegendOpen('1')).toBe(true);
        expect(isLegendOpen('0')).toBe(false);
    });

    it('legend_preference_round_trips_through_storage', () => {
        expect(isLegendOpen(legendStorageValue(false))).toBe(false);
        expect(isLegendOpen(legendStorageValue(true))).toBe(true);
        expect(LEGEND_STORAGE_KEY).toBe('sd-legend');
    });
});
