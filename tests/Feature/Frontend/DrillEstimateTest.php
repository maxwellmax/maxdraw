<?php

use App\Models\EstimateMode;

/**
 * A calculadora de capacidade (Phase 17): os dois modos, as dez linhas de
 * saída, a formatação em pt-BR e a conclusão do pico. Quem testa a aritmética
 * é o Vitest; o que a suíte PHP guarda aqui é o contrato — as peças por
 * `data-testid`, a ligação com o store, a fidelidade da fixture ao catálogo e
 * a garantia de que modo e destaque não viraram lista fixa no cliente.
 */
it('coloca cada peça da calculadora no arquivo que a implementa', function (string $file, string $testId) {
    expect(frontendSource($file))->toContain('data-testid="'.$testId.'"');
})->with([
    ['components/prancheta/EstimatePanel.vue', 'calc'],
    ['components/prancheta/EstimatePanel.vue', 'est-modes'],
    ['components/prancheta/EstimatePanel.vue', 'est-fields'],
    ['components/prancheta/EstimatePanel.vue', 'est-out'],
    ['components/prancheta/EstimatePanel.vue', 'est-row'],
    ['components/prancheta/EstimatePanel.vue', 'est-row-label'],
    ['components/prancheta/EstimatePanel.vue', 'est-row-value'],
    ['components/prancheta/EstimatePanel.vue', 'est-row-unit'],
    ['components/prancheta/EstimatePanel.vue', 'est-note'],
    ['components/prancheta/EstimatePanel.vue', 'est-conclusion'],
    ['components/prancheta/EstimateField.vue', 'est-field'],
]);

it('preenche o painel de estimativas com o slot do DrillPanel', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('<template #calc>')
        ->toContain('<EstimatePanel')
        ->toContain(':estimate="store.estimate"')
        ->toContain(':modes="catalog.estimate_modes"')
        ->toContain('@pick-mode="pickEstimateMode"')
        ->toContain('@update-field="setEstimateField"');

    expect(frontendSource('components/prancheta/DrillPanel.vue'))
        ->toContain('<slot v-else-if="tab.id === \'calc\'" name="calc" />');
});

it('grava o modo escolhido na sessão pelo mesmo store do resto', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('store.setEstimate({ mode: slug });')
        ->toContain('store.setEstimate({ [key]: value });');

    expect(frontendSource('prancheta/session.ts'))
        ->toContain('setEstimate(estimate: Partial<SessionEstimate>): void')
        ->toContain('estimate: state.estimate,');
});

it('deriva as dez linhas da saída na ordem que o protótipo mostra', function () {
    preg_match_all(
        "/key: '([a-z_]+)',\n\s+label: [`']([^`']+)[`']/u",
        frontendSource('prancheta/estimate.ts'),
        $rows,
        PREG_SET_ORDER
    );

    expect(array_column($rows, 1))->toBe([
        'writes_month',
        'writes_day',
        'write_qps',
        'reads_day',
        'read_qps',
        'peak_qps',
        'data_day',
        'data_year',
        'retention',
        'outbound',
    ]);

    expect(array_column($rows, 2))->toBe([
        'Escritas por mês',
        'Escritas por dia',
        'Escritas por segundo',
        'Leituras por dia',
        'Leituras por segundo',
        'Total por segundo no pico',
        'Dados novos por dia',
        'Dados novos por ano',
        'Armazenamento em ${formatYears(retention)} ano(s)',
        'Banda de saída no pico',
    ]);
});

it('aplica as fórmulas que a fase define, com o mês de 30 dias', function (string $formula) {
    expect(frontendSource('prancheta/estimate.ts'))->toContain($formula);
})->with([
    'export const MONTH_DAYS = 30;',
    'export const YEAR_DAYS = 365;',
    'export const DAY_SECONDS = 86400;',
    'sanitize(estimate.per_month) / MONTH_DAYS',
    'sanitize(estimate.dau) * sanitize(estimate.act)',
    'const readsPerDay = writesPerDay * ratio;',
    'const writeQps = writesPerDay / DAY_SECONDS;',
    'const readQps = readsPerDay / DAY_SECONDS;',
    'peakQps: (writeQps + readQps) * peak,',
    'const dataPerDayKb = writesPerDay * size;',
    'dataPerYearKb: dataPerDayKb * YEAR_DAYS,',
    'retentionKb: dataPerDayKb * YEAR_DAYS * retention,',
    'outboundKbPerSecond: readQps * peak * size,',
]);

it('normaliza a entrada antes de calcular, sem deixar NaN chegar à tela', function () {
    expect(frontendSource('prancheta/estimate.ts'))
        ->toContain('return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;')
        ->toContain('return NON_FINITE;')
        ->toContain("export const NON_FINITE = '—';");
});

it('troca só os campos próprios ao trocar de modo, preservando os comuns', function () {
    expect(frontendSource('prancheta/estimate.ts'))
        ->toContain("{ key: 'dau', label: 'Usuários ativos por dia' }")
        ->toContain("{ key: 'act', label: 'Ações de escrita por usuário/dia' }")
        ->toContain("{ key: 'per_month', label: 'Escritas por mês' }")
        ->toContain("{ key: 'ratio', label: 'Leituras para cada escrita' }")
        ->toContain("{ key: 'size', label: 'Tamanho médio do registro (KB)' }")
        ->toContain("{ key: 'peak', label: 'Fator de pico' }")
        ->toContain("{ key: 'ret', label: 'Retenção (anos)' }")
        ->toContain('return [...(MODE_FIELDS[mode] ?? MODE_FIELDS.user), ...COMMON_FIELDS];');
});

it('recalcula a cada tecla sem reescrever o campo em edição', function () {
    expect(frontendSource('components/prancheta/EstimateField.vue'))
        ->toContain('if (!keepsDraft(draft.value, value)) {')
        ->toContain(':value="draft"')
        ->toContain('@input="onInput"')
        ->not->toContain('v-model')
        ->not->toContain('@change')
        ->not->toContain('@blur');

    expect(frontendSource('components/prancheta/EstimatePanel.vue'))
        ->toContain(':key="field.key"');
});

it('tira os modos e a linha destacada do catálogo, não de lista fixa', function () {
    seedCatalog();

    expect(frontendSource('components/prancheta/EstimatePanel.vue'))
        ->toContain('v-for="mode in modes"')
        ->toContain('{{ mode.name }}');

    expect(frontendSource('prancheta/estimate.ts'))
        ->toContain('return modeOf(modes, slug)?.highlighted_row ?? \'\';')
        ->toContain('ALWAYS_HIGHLIGHTED.includes(row.key) || row.label === highlighted,')
        ->toContain("export const ALWAYS_HIGHLIGHTED: EstimateRowKey[] = ['peak_qps', 'retention'];");

    $client = frontendSource('prancheta/estimate.ts')
        .frontendSource('components/prancheta/EstimatePanel.vue')
        .frontendSource('components/prancheta/EstimateField.vue');

    foreach (EstimateMode::query()->get() as $mode) {
        expect($client)->not->toContain($mode->name);
    }
});

it('destaca uma linha que a saída realmente tem', function () {
    seedCatalog();

    preg_match_all(
        "/label: [`']([^`']+)[`']/u",
        frontendSource('prancheta/estimate.ts'),
        $labels
    );

    foreach (EstimateMode::query()->get() as $mode) {
        expect($labels[1])->toContain($mode->highlighted_row);
    }
});

it('mantém a fixture do Vitest fiel aos modos do catálogo', function () {
    seedCatalog();

    preg_match(
        '/export function estimateModeOptionsFixture\(\): EstimateModeOption\[\] \{(.+?)\n\}/su',
        frontendSource('prancheta/fixtures.ts'),
        $block
    );

    preg_match_all(
        "/id: (\d+),\s+slug: '([^']+)',\s+name: '([^']+)',\s+highlighted_row: '([^']+)',/u",
        $block[1],
        $fixture,
        PREG_SET_ORDER
    );

    $client = array_map(fn (array $match): array => [
        'id' => (int) $match[1],
        'slug' => $match[2],
        'name' => $match[3],
        'highlighted_row' => $match[4],
    ], $fixture);

    expect($client)->toBe(EstimateMode::query()->get()
        ->map(fn (EstimateMode $mode): array => [
            'id' => $mode->id,
            'slug' => $mode->slug,
            'name' => $mode->name,
            'highlighted_row' => $mode->highlighted_row,
        ])
        ->all());
});

it('conclui pelo pico com os dois limiares inclusivos e a frase de cada faixa', function () {
    expect(frontendSource('prancheta/estimate.ts'))
        ->toContain('export const HEAVY_PEAK_QPS = 50000;')
        ->toContain('export const MODERATE_PEAK_QPS = 5000;')
        ->toContain("'esse pico exige cache e particionamento, diga isso em voz alta'")
        ->toContain("'dá para servir com réplicas de leitura e cache'")
        ->toContain("'uma instância bem dimensionada aguenta, não invente complexidade'")
        ->toContain('peakQps >= HEAVY_PEAK_QPS ? HEAVY_CONCLUSION : MODERATE_CONCLUSION;')
        ->toContain('peakQps < MODERATE_PEAK_QPS')
        ->toContain('Mês = 30 dias. Arredonde na conversa:');
});

it('recalcula a frase junto com a saída, sem estado próprio', function () {
    expect(frontendSource('components/prancheta/EstimatePanel.vue'))
        ->toContain('const rows = computed(() => estimateRows(props.estimate, props.modes));')
        ->toContain('const conclusion = computed(() => conclusionFor(props.estimate));')
        ->toContain('{{ ESTIMATE_NOTE }}')
        ->not->toContain('ref(');
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(pranchetaTestNames())->toContain($name);
})->with([
    'both_modes_produce_identical_output_for_equivalent_inputs',
    'month_is_thirty_days',
    'qps_divides_by_86400',
    'peak_applies_factor_to_combined_qps',
    'outbound_bandwidth_uses_read_qps_peak_and_size',
    'negative_inputs_are_normalized_to_zero',
    'empty_and_zero_inputs_do_not_produce_NaN',
    'formats_thousands_millions_and_billions_in_ptbr',
    'formats_bytes_from_B_to_PB',
    'non_finite_values_render_as_dash',
    'sub_ten_values_keep_one_decimal',
    'conclusion_thresholds_at_50k_and_5k',
    'conclusion_updates_with_any_parameter_change',
]);
