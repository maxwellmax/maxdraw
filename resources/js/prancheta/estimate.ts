/**
 * A calculadora de capacidade: os dois modos de entrada, as dez linhas de
 * saída, a formatação em pt-BR e a conclusão que se tira do pico (US-7.1,
 * US-7.2, US-7.3).
 *
 * Os dois modos compartilham tudo depois da primeira conta: o que muda é de
 * onde saem as escritas por dia — `dau × ações` ou `perMonth / 30` —, e daí
 * para frente a aritmética é uma só. Por isso as dez linhas são as mesmas nos
 * dois modos, na mesma ordem.
 *
 * Nenhum modo e nenhuma linha destacada mora aqui: os dois vêm de
 * `catalog.estimate_modes`. O que este módulo guarda é a aritmética, os
 * rótulos das linhas e o texto da conclusão.
 */

import type { SessionEstimate } from './session';

/** Um modo de estimativa como o `EstimateModeResource` o entrega. */
export type EstimateModeOption = {
    id: number;
    slug: string;
    name: string;
    highlighted_row: string;
};

/** Os campos numéricos da estimativa — tudo em `SessionEstimate` menos o modo. */
export type EstimateFieldKey = Exclude<keyof SessionEstimate, 'mode'>;

/** Um campo da calculadora: a chave que o store grava e o rótulo na tela. */
export type EstimateField = {
    key: EstimateFieldKey;
    label: string;
};

export type EstimateRowKey =
    | 'writes_month'
    | 'writes_day'
    | 'write_qps'
    | 'reads_day'
    | 'read_qps'
    | 'peak_qps'
    | 'data_day'
    | 'data_year'
    | 'retention'
    | 'outbound';

/** Uma linha da saída, já formatada. */
export type EstimateRow = {
    key: EstimateRowKey;
    label: string;
    value: string;
    unit: string;
    highlighted: boolean;
};

/** O resultado cru, antes da formatação. Armazenamento e banda em KB. */
export type EstimateMetrics = {
    writesPerDay: number;
    writesPerMonth: number;
    writeQps: number;
    readsPerDay: number;
    readQps: number;
    peakQps: number;
    dataPerDayKb: number;
    dataPerYearKb: number;
    retentionKb: number;
    outboundKbPerSecond: number;
};

/** O mês da entrevista tem 30 dias — é a convenção que a saída anuncia. */
export const MONTH_DAYS = 30;

export const YEAR_DAYS = 365;

export const DAY_SECONDS = 86400;

/** O slug do modo por volume mensal; qualquer outro conta por usuários. */
export const MONTHLY_MODE = 'month';

/** O que a tela mostra no lugar de um número que não existe. */
export const NON_FINITE = '—';

/** As escalas de byte, da menor à maior. O tamanho do registro chega em KB. */
export const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'] as const;

/** Os campos próprios de cada modo, pelo slug do catálogo. */
export const MODE_FIELDS: Record<string, EstimateField[]> = {
    user: [
        { key: 'dau', label: 'Usuários ativos por dia' },
        { key: 'act', label: 'Ações de escrita por usuário/dia' },
    ],
    month: [{ key: 'per_month', label: 'Escritas por mês' }],
};

/** Os campos que os dois modos têm em comum, e que trocar de modo preserva. */
export const COMMON_FIELDS: EstimateField[] = [
    { key: 'ratio', label: 'Leituras para cada escrita' },
    { key: 'size', label: 'Tamanho médio do registro (KB)' },
    { key: 'peak', label: 'Fator de pico' },
    { key: 'ret', label: 'Retenção (anos)' },
];

/**
 * As duas linhas que ficam destacadas em qualquer modo: o pico é o número que
 * decide a arquitetura, e a retenção é o que decide o armazenamento.
 */
export const ALWAYS_HIGHLIGHTED: EstimateRowKey[] = ['peak_qps', 'retention'];

/** A partir daqui o pico obriga a falar de cache e de particionamento. */
export const HEAVY_PEAK_QPS = 50000;

/** A partir daqui réplicas de leitura e cache dão conta. */
export const MODERATE_PEAK_QPS = 5000;

export const HEAVY_CONCLUSION =
    'esse pico exige cache e particionamento, diga isso em voz alta';

export const MODERATE_CONCLUSION =
    'dá para servir com réplicas de leitura e cache';

export const LIGHT_CONCLUSION =
    'uma instância bem dimensionada aguenta, não invente complexidade';

/** O aviso do mês de 30 dias e a orientação de arredondar, que abrem a frase. */
export const ESTIMATE_NOTE =
    'Mês = 30 dias. Arredonde na conversa: o número exato importa menos que a ordem de grandeza e a conclusão que você tira dela —';

/**
 * Todo parâmetro entra saneado: negativo vira zero e o que não é número vira
 * zero também. É o que faz campo vazio e valor absurdo não produzirem `NaN` na
 * saída — a tela nunca precisa se defender depois.
 */
export function sanitize(value: number | string | null | undefined): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
}

export function isMonthly(mode: string): boolean {
    return mode === MONTHLY_MODE;
}

/**
 * O resultado dos dois modos. A única bifurcação está na primeira linha: as
 * escritas por dia. Tudo o que vem depois é derivado dela, então informar
 * `dau × ações` ou `perMonth / 30` equivalentes dá exatamente a mesma saída.
 */
export function estimateMetrics(estimate: SessionEstimate): EstimateMetrics {
    const ratio = sanitize(estimate.ratio);
    const size = sanitize(estimate.size);
    const peak = sanitize(estimate.peak);
    const retention = sanitize(estimate.ret);

    const writesPerDay = isMonthly(estimate.mode)
        ? sanitize(estimate.per_month) / MONTH_DAYS
        : sanitize(estimate.dau) * sanitize(estimate.act);

    const readsPerDay = writesPerDay * ratio;
    const writeQps = writesPerDay / DAY_SECONDS;
    const readQps = readsPerDay / DAY_SECONDS;
    const dataPerDayKb = writesPerDay * size;

    return {
        writesPerDay,
        writesPerMonth: writesPerDay * MONTH_DAYS,
        writeQps,
        readsPerDay,
        readQps,
        peakQps: (writeQps + readQps) * peak,
        dataPerDayKb,
        dataPerYearKb: dataPerDayKb * YEAR_DAYS,
        retentionKb: dataPerDayKb * YEAR_DAYS * retention,
        outboundKbPerSecond: readQps * peak * size,
    };
}

/**
 * As dez linhas na ordem em que o painel as mostra. O destaque sai do
 * catálogo: cada modo declara qual linha destacar pelo rótulo dela, e as duas
 * linhas de sempre se somam a essa.
 */
export function estimateRows(
    estimate: SessionEstimate,
    modes: readonly EstimateModeOption[] = [],
): EstimateRow[] {
    const metrics = estimateMetrics(estimate);
    const retention = sanitize(estimate.ret);
    const highlighted = highlightedRowOf(modes, estimate.mode);

    const rows: Array<Omit<EstimateRow, 'highlighted'>> = [
        {
            key: 'writes_month',
            label: 'Escritas por mês',
            value: formatNumber(metrics.writesPerMonth),
            unit: '/mês',
        },
        {
            key: 'writes_day',
            label: 'Escritas por dia',
            value: formatNumber(metrics.writesPerDay),
            unit: '/dia',
        },
        {
            key: 'write_qps',
            label: 'Escritas por segundo',
            value: formatNumber(metrics.writeQps),
            unit: 'qps',
        },
        {
            key: 'reads_day',
            label: 'Leituras por dia',
            value: formatNumber(metrics.readsPerDay),
            unit: '/dia',
        },
        {
            key: 'read_qps',
            label: 'Leituras por segundo',
            value: formatNumber(metrics.readQps),
            unit: 'qps',
        },
        {
            key: 'peak_qps',
            label: 'Total por segundo no pico',
            value: formatNumber(metrics.peakQps),
            unit: 'qps',
        },
        {
            key: 'data_day',
            label: 'Dados novos por dia',
            value: formatBytes(metrics.dataPerDayKb),
            unit: '',
        },
        {
            key: 'data_year',
            label: 'Dados novos por ano',
            value: formatBytes(metrics.dataPerYearKb),
            unit: '',
        },
        {
            key: 'retention',
            label: `Armazenamento em ${formatYears(retention)} ano(s)`,
            value: formatBytes(metrics.retentionKb),
            unit: '',
        },
        {
            key: 'outbound',
            label: 'Banda de saída no pico',
            value: `${formatBytes(metrics.outboundKbPerSecond)}/s`,
            unit: '',
        },
    ];

    return rows.map((row) => ({
        ...row,
        highlighted:
            ALWAYS_HIGHLIGHTED.includes(row.key) || row.label === highlighted,
    }));
}

export function modeOf(
    modes: readonly EstimateModeOption[],
    slug: string,
): EstimateModeOption | null {
    return modes.find((mode) => mode.slug === slug) ?? null;
}

export function highlightedRowOf(
    modes: readonly EstimateModeOption[],
    slug: string,
): string {
    return modeOf(modes, slug)?.highlighted_row ?? '';
}

/** Os campos do modo, sempre com os comuns depois dos próprios. */
export function fieldsOf(mode: string): EstimateField[] {
    return [...(MODE_FIELDS[mode] ?? MODE_FIELDS.user), ...COMMON_FIELDS];
}

/**
 * O número como a saída o mostra: `mil`, `mi` e `bi` a partir de cada potência
 * de mil, com uma casa decimal no máximo e os separadores de português. Abaixo
 * de mil, só o que é menor que dez ganha decimal — acima disso a casa não
 * informa nada que a ordem de grandeza já não diga.
 */
export function formatNumber(value: number): string {
    if (!Number.isFinite(value)) {
        return NON_FINITE;
    }

    const magnitude = Math.abs(value);

    if (magnitude >= 1e9) {
        return `${decimal(value / 1e9, 1)} bi`;
    }

    if (magnitude >= 1e6) {
        return `${decimal(value / 1e6, 1)} mi`;
    }

    if (magnitude >= 1e3) {
        return `${decimal(value / 1e3, 1)} mil`;
    }

    return decimal(value, magnitude < 10 ? 1 : 0);
}

/**
 * O tamanho como a saída o mostra. A entrada é o tamanho do registro em KB, e
 * a escala sobe de B até PB — acima de PB o número cresce, a unidade não.
 */
export function formatBytes(kilobytes: number): string {
    if (!Number.isFinite(kilobytes)) {
        return NON_FINITE;
    }

    let value = kilobytes * 1024;
    let unit = 0;

    while (Math.abs(value) >= 1024 && unit < BYTE_UNITS.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${decimal(value, 1)} ${BYTE_UNITS[unit]}`;
}

/**
 * A conclusão que o pico obriga. As duas fronteiras são inclusivas: 50.000 qps
 * exatos já pedem particionamento, e 5.000 exatos já pedem réplicas (US-7.3).
 */
export function conclusionOf(peakQps: number): string {
    if (!Number.isFinite(peakQps) || peakQps < MODERATE_PEAK_QPS) {
        return LIGHT_CONCLUSION;
    }

    return peakQps >= HEAVY_PEAK_QPS ? HEAVY_CONCLUSION : MODERATE_CONCLUSION;
}

export function conclusionFor(estimate: SessionEstimate): string {
    return conclusionOf(estimateMetrics(estimate).peakQps);
}

/**
 * O valor que o campo entrega ao store: negativo vira zero e texto que não é
 * número também, para que a saída nunca dependa do que foi digitado.
 */
export function fieldValueOf(text: string): number {
    return sanitize(Number.parseFloat(text));
}

/**
 * Se o texto que está no campo ainda representa o valor gravado. É o que
 * permite recalcular a cada tecla sem reescrever o campo em edição — e sem
 * reescrever, o foco e a posição do cursor ficam onde o usuário os deixou.
 */
export function keepsDraft(text: string, value: number): boolean {
    const trimmed = text.trim();

    return trimmed === '' ? value === 0 : Number.parseFloat(trimmed) === value;
}

function formatYears(years: number): string {
    return decimal(years, Number.isInteger(years) ? 0 : 1);
}

function decimal(value: number, digits: number): string {
    return value.toLocaleString('pt-BR', { maximumFractionDigits: digits });
}
