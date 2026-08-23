import { describe, expect, it } from 'vitest';
import {
    COMMON_FIELDS,
    conclusionFor,
    conclusionOf,
    DAY_SECONDS,
    estimateMetrics,
    estimateRows,
    fieldsOf,
    fieldValueOf,
    formatBytes,
    formatNumber,
    HEAVY_CONCLUSION,
    HEAVY_PEAK_QPS,
    keepsDraft,
    LIGHT_CONCLUSION,
    MODERATE_CONCLUSION,
    MODERATE_PEAK_QPS,
    MONTH_DAYS,
    NON_FINITE,
    YEAR_DAYS,
} from './estimate';
import { estimateModeOptionsFixture, sessionRecordFixture } from './fixtures';
import type { SessionEstimate } from './session';

const MODES = estimateModeOptionsFixture();

function estimateFixture(
    overrides: Partial<SessionEstimate> = {},
): SessionEstimate {
    return { ...sessionRecordFixture().estimate, ...overrides };
}

/** Os valores das dez linhas, que são o que os dois modos precisam repetir. */
function valuesOf(estimate: SessionEstimate): string[] {
    return estimateRows(estimate, MODES).map(
        (row) => `${row.label}|${row.value}|${row.unit}`,
    );
}

describe('estimate', () => {
    it('both_modes_produce_identical_output_for_equivalent_inputs', () => {
        const byUser = estimateFixture({
            mode: 'user',
            dau: 1_000_000,
            act: 10,
        });

        const byMonth = estimateFixture({
            mode: 'month',
            per_month: 1_000_000 * 10 * MONTH_DAYS,
        });

        expect(estimateMetrics(byUser)).toEqual(estimateMetrics(byMonth));
        expect(valuesOf(byUser)).toEqual(valuesOf(byMonth));

        expect(estimateRows(byUser, MODES).map((row) => row.key)).toEqual(
            estimateRows(byMonth, MODES).map((row) => row.key),
        );
    });

    it('month_is_thirty_days', () => {
        const byMonth = estimateFixture({
            mode: 'month',
            per_month: 3_000_000,
        });

        expect(estimateMetrics(byMonth).writesPerDay).toBe(3_000_000 / 30);

        const byUser = estimateFixture({ mode: 'user', dau: 500, act: 4 });

        expect(estimateMetrics(byUser).writesPerMonth).toBe(500 * 4 * 30);
    });

    it('qps_divides_by_86400', () => {
        expect(DAY_SECONDS).toBe(86400);

        const metrics = estimateMetrics(
            estimateFixture({ dau: 864_000, act: 1, ratio: 5 }),
        );

        expect(metrics.writeQps).toBe(metrics.writesPerDay / 86400);
        expect(metrics.readQps).toBe(metrics.readsPerDay / 86400);
        expect(metrics.writeQps).toBe(10);
        expect(metrics.readQps).toBe(50);
    });

    it('peak_applies_factor_to_combined_qps', () => {
        const metrics = estimateMetrics(
            estimateFixture({ dau: 864_000, act: 1, ratio: 5, peak: 3 }),
        );

        expect(metrics.peakQps).toBe((metrics.writeQps + metrics.readQps) * 3);
        expect(metrics.peakQps).toBe(180);
    });

    it('outbound_bandwidth_uses_read_qps_peak_and_size', () => {
        const metrics = estimateMetrics(
            estimateFixture({
                dau: 864_000,
                act: 1,
                ratio: 5,
                peak: 3,
                size: 2,
            }),
        );

        expect(metrics.outboundKbPerSecond).toBe(metrics.readQps * 3 * 2);
        expect(metrics.outboundKbPerSecond).toBe(300);
    });

    it('stores_data_per_day_per_year_and_over_the_retention', () => {
        const metrics = estimateMetrics(
            estimateFixture({ dau: 1000, act: 2, size: 4, ret: 5 }),
        );

        expect(metrics.dataPerDayKb).toBe(1000 * 2 * 4);
        expect(metrics.dataPerYearKb).toBe(metrics.dataPerDayKb * YEAR_DAYS);
        expect(metrics.retentionKb).toBe(metrics.dataPerYearKb * 5);
    });

    it('negative_inputs_are_normalized_to_zero', () => {
        const metrics = estimateMetrics(
            estimateFixture({
                dau: -1000,
                act: -10,
                ratio: -100,
                size: -5,
                peak: -3,
                ret: -2,
            }),
        );

        expect(Object.values(metrics)).toEqual(Array(10).fill(0));

        const monthly = estimateMetrics(
            estimateFixture({ mode: 'month', per_month: -900 }),
        );

        expect(monthly.writesPerDay).toBe(0);
    });

    it('empty_and_zero_inputs_do_not_produce_NaN', () => {
        const empty = {
            mode: 'user',
            dau: '',
            act: '',
            per_month: '',
            ratio: '',
            size: '',
            peak: '',
            ret: '',
        } as unknown as SessionEstimate;

        for (const estimate of [
            empty,
            estimateFixture({ dau: Number.NaN, act: Number.NaN }),
            estimateFixture({ dau: 0, act: 0, ratio: 0, size: 0, peak: 0 }),
            estimateFixture({ mode: 'month', per_month: 0, ratio: 0 }),
        ]) {
            expect(
                Object.values(estimateMetrics(estimate)).every((value) =>
                    Number.isFinite(value),
                ),
            ).toBe(true);

            for (const row of estimateRows(estimate, MODES)) {
                expect(row.value).not.toContain('NaN');
                expect(row.value).not.toContain('Infinity');
                expect(row.label).not.toContain('NaN');
            }
        }
    });

    it('lists_the_same_ten_rows_in_the_same_order_in_both_modes', () => {
        const rows = estimateRows(estimateFixture({ mode: 'user' }), MODES);

        expect(rows).toHaveLength(10);
        expect(rows.map((row) => row.label)).toEqual([
            'Escritas por mês',
            'Escritas por dia',
            'Escritas por segundo',
            'Leituras por dia',
            'Leituras por segundo',
            'Total por segundo no pico',
            'Dados novos por dia',
            'Dados novos por ano',
            'Armazenamento em 3 ano(s)',
            'Banda de saída no pico',
        ]);
    });

    it('highlights_the_row_the_catalog_names_plus_peak_and_retention', () => {
        const highlightedIn = (mode: string): string[] =>
            estimateRows(estimateFixture({ mode }), MODES)
                .filter((row) => row.highlighted)
                .map((row) => row.key);

        expect(highlightedIn('user')).toEqual([
            'writes_day',
            'peak_qps',
            'retention',
        ]);

        expect(highlightedIn('month')).toEqual([
            'writes_month',
            'peak_qps',
            'retention',
        ]);

        expect(
            estimateRows(estimateFixture(), []).filter((row) => row.highlighted)
                .length,
        ).toBe(2);
    });

    it('keeps_the_common_fields_in_both_modes_and_swaps_only_the_own_ones', () => {
        const common = COMMON_FIELDS.map((field) => field.key);

        expect(fieldsOf('user').map((field) => field.key)).toEqual([
            'dau',
            'act',
            ...common,
        ]);

        expect(fieldsOf('month').map((field) => field.key)).toEqual([
            'per_month',
            ...common,
        ]);

        expect(common).toEqual(['ratio', 'size', 'peak', 'ret']);
    });

    it('formats_thousands_millions_and_billions_in_ptbr', () => {
        expect(formatNumber(1500)).toBe('1,5 mil');
        expect(formatNumber(12_345)).toBe('12,3 mil');
        expect(formatNumber(2_500_000)).toBe('2,5 mi');
        expect(formatNumber(1_234_000_000)).toBe('1,2 bi');
        expect(formatNumber(1_234_000_000_000)).toBe('1.234 bi');
        expect(formatNumber(999)).toBe('999');
    });

    it('formats_bytes_from_B_to_PB', () => {
        expect(formatBytes(0)).toBe('0 B');
        expect(formatBytes(0.5)).toBe('512 B');
        expect(formatBytes(1)).toBe('1 KB');
        expect(formatBytes(1024)).toBe('1 MB');
        expect(formatBytes(1024 ** 2)).toBe('1 GB');
        expect(formatBytes(1024 ** 3)).toBe('1 TB');
        expect(formatBytes(1024 ** 4)).toBe('1 PB');
        expect(formatBytes(1024 ** 5)).toBe('1.024 PB');
    });

    it('non_finite_values_render_as_dash', () => {
        for (const value of [
            Number.NaN,
            Number.POSITIVE_INFINITY,
            Number.NEGATIVE_INFINITY,
        ]) {
            expect(formatNumber(value)).toBe(NON_FINITE);
            expect(formatBytes(value)).toBe(NON_FINITE);
            expect(formatNumber(value)).not.toContain('NaN');
            expect(formatBytes(value)).not.toContain('Infinity');
        }

        expect(NON_FINITE).toBe('—');
    });

    it('sub_ten_values_keep_one_decimal', () => {
        expect(formatNumber(9.5)).toBe('9,5');
        expect(formatNumber(0.5)).toBe('0,5');
        expect(formatNumber(12.5)).toBe('13');
        expect(formatNumber(999.4)).toBe('999');
    });

    it('conclusion_thresholds_at_50k_and_5k', () => {
        expect(HEAVY_PEAK_QPS).toBe(50000);
        expect(MODERATE_PEAK_QPS).toBe(5000);

        expect(conclusionOf(HEAVY_PEAK_QPS)).toBe(HEAVY_CONCLUSION);
        expect(conclusionOf(HEAVY_PEAK_QPS + 1)).toBe(HEAVY_CONCLUSION);
        expect(conclusionOf(HEAVY_PEAK_QPS - 0.01)).toBe(MODERATE_CONCLUSION);

        expect(conclusionOf(MODERATE_PEAK_QPS)).toBe(MODERATE_CONCLUSION);
        expect(conclusionOf(MODERATE_PEAK_QPS - 0.01)).toBe(LIGHT_CONCLUSION);
        expect(conclusionOf(0)).toBe(LIGHT_CONCLUSION);
    });

    it('conclusion_updates_with_any_parameter_change', () => {
        const base = estimateFixture({
            mode: 'user',
            dau: 1000,
            act: 1,
            per_month: 30_000,
            ratio: 1,
            size: 1,
            peak: 1,
            ret: 1,
        });

        expect(conclusionFor(base)).toBe(LIGHT_CONCLUSION);

        const changes: Array<[Partial<SessionEstimate>, string]> = [
            [{ dau: 1_000_000_000 }, MODERATE_CONCLUSION],
            [{ act: 1_000_000 }, MODERATE_CONCLUSION],
            [{ ratio: 10_000_000 }, HEAVY_CONCLUSION],
            [{ peak: 1_000_000 }, MODERATE_CONCLUSION],
            [{ mode: 'month', per_month: 300_000_000_000 }, HEAVY_CONCLUSION],
        ];

        for (const [change, expected] of changes) {
            expect(conclusionFor({ ...base, ...change })).toBe(expected);
        }
    });

    it('recalculates_per_keystroke_without_rewriting_the_field_in_edition', () => {
        expect(fieldValueOf('12')).toBe(12);
        expect(fieldValueOf('1.')).toBe(1);
        expect(fieldValueOf('')).toBe(0);
        expect(fieldValueOf('-5')).toBe(0);

        expect(keepsDraft('1.', 1)).toBe(true);
        expect(keepsDraft('', 0)).toBe(true);
        expect(keepsDraft('12', 12)).toBe(true);

        expect(keepsDraft('-5', 0)).toBe(false);
        expect(keepsDraft('', 42)).toBe(false);
        expect(keepsDraft('12', 7)).toBe(false);
    });
});
