<?php

use App\Models\LinkType;
use App\Models\SequenceMode;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * A legenda automática (Phase 13): a estrutura que o motor deriva do desenho, o
 * painel que a mostra e o recolhimento que fica no navegador. O comportamento é
 * testado pelo Vitest; o que a suíte PHP guarda aqui é o contrato — a legenda
 * saindo só do diagrama, as glosas vindas do catálogo do servidor e a cobertura
 * que a fase exige.
 */
it('deriva a legenda inteira do diagrama, sem configuração do usuário', function () {
    expect(frontendSource('canvas/legend.ts'))
        ->toContain('export function legendData(')
        ->toContain('const counts = countByCategory(state.nodes, index);')
        ->toContain('const drawn = liveEdges(state.edges, state.nodes);');

    expect(frontendSource('canvas/engine.ts'))
        ->toMatch('/legendData\(\): LegendData \{\s*return legendData\(\s*this\.state,\s*this\.index,\s*this\.linkIndex,\s*this\.sequenceModeOptions,\s*\);\s*\}/');
});

it('lista só as categorias presentes, na ordem do catálogo, com a contagem', function () {
    expect(frontendSource('canvas/legend.ts'))
        ->toMatch('/catalogCategories\(index\)\s*\.filter\(\(category\) => counts\.has\(category\.slug\)\)/')
        ->toContain('count: counts.get(category.slug) ?? 0,');

    expect(frontendSource('canvas/catalog.ts'))
        ->toContain('export function catalogCategories(index: ComponentIndex): CatalogCategory[] {');
});

it('lista só os tipos usados por aresta válida, na ordem do catálogo', function () {
    expect(frontendSource('canvas/legend.ts'))
        ->toMatch('/\[\.\.\.linkIndex\.values\(\)\]\s*\.filter\(\(type\) => drawn\.some\(\(edge\) => edge\.kind === type\.slug\)\)/');
});

it('sinaliza as arestas sem tipo e a numeração ativa', function () {
    expect(frontendSource('canvas/legend.ts'))
        ->toMatch('/const untyped = drawn\.some\(\s*\(edge\) => linkTypeOf\(linkIndex, edge\.kind\) === null,\s*\);/')
        ->toMatch('/if \(Object\.keys\(numbers\)\.length === 0\) \{\s*return null;\s*\}/');
});

it('some por completo quando não há nada desenhado', function () {
    expect(frontendSource('canvas/legend.ts'))
        ->toContain('empty: categories.length === 0 && links.length === 0 && !untyped,');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('const legend = computed(() => engine.legendData());')
        ->toContain(':legend-visible="!legend.empty"');

    expect(frontendSource('components/prancheta/LegendPanel.vue'))
        ->toContain('v-if="visible"');
});

it('monta a seção Blocos com quadradinho, nome e contagem', function () {
    expect(frontendSource('components/prancheta/LegendContent.vue'))
        ->toContain('Blocos')
        ->toContain('data-testid="legend-category"')
        ->toContain('data-testid="legend-swatch"')
        ->toContain(':style="{ background: category.color }"')
        ->toContain('{{ category.name }}')
        ->toContain('data-testid="legend-count"');
});

it('monta a seção Ligações com amostra, selo, nome e glosa', function () {
    $content = frontendSource('components/prancheta/LegendContent.vue');

    expect($content)
        ->toContain('Ligações')
        ->toContain('data-testid="legend-link"')
        ->toContain('<LegendLine :dash="link.dash" :bidir="link.bidir" />')
        ->toContain('data-testid="legend-badge"')
        ->toContain('{{ link.badge }}')
        ->toContain('link.name')
        ->toContain('data-testid="legend-gloss"')
        ->toContain('{{ link.gloss }}');

    $template = substr($content, strpos($content, '<template>'));

    $positions = array_map(
        fn (string $testId): int => strpos($template, 'data-testid="'.$testId.'"'),
        ['legend-line', 'legend-badge', 'legend-gloss'],
    );

    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

it('dá à seta sem tipo a linha que ensina a escolher o protocolo', function () {
    expect(frontendSource('canvas/legend.ts'))
        ->toContain("export const UNTYPED_NAME = 'sem tipo';")
        ->toContain("export const UNTYPED_GLOSS = 'clique na seta e escolha o protocolo';");

    expect(frontendSource('components/prancheta/LegendContent.vue'))
        ->toContain("import { UNTYPED_GLOSS, UNTYPED_NAME } from '@/canvas/legend';")
        ->toContain('v-if="data.untyped"');
});

it('mostra a seção Sequência só quando há numeração, com o texto do modo', function () {
    expect(frontendSource('components/prancheta/LegendContent.vue'))
        ->toContain('v-if="data.sequence"')
        ->toContain('Sequência')
        ->toContain('data-testid="legend-sequence"')
        ->toContain('data.sequence.name')
        ->toContain('{{ data.sequence.text }}');
});

it('mantém as amostras de traço neutras: a cor só está no quadradinho', function () {
    $line = frontendSource('components/prancheta/LegendLine.vue');

    expect($line)
        ->toContain('class="h-3 w-10 flex-none text-sd-ink-3"')
        ->toContain('stroke="currentColor"')
        ->toContain('fill="currentColor"')
        ->not->toContain('--c-')
        ->not->toContain('category.color');

    $content = frontendSource('components/prancheta/LegendContent.vue');

    expect(substr_count($content, ':style='))->toBe(1)
        ->and($content)->toContain(':style="{ background: category.color }"');
});

it('tira a glosa e o texto do modo do catálogo do servidor, sem tabela paralela', function () {
    seedCatalog();

    $client = frontendSource('canvas/legend.ts').frontendSource('components/prancheta/LegendContent.vue');

    foreach (LinkType::query()->pluck('gloss') as $gloss) {
        expect($client)->not->toContain($gloss);
    }

    foreach (SequenceMode::query()->pluck('legend_text')->filter() as $text) {
        expect($client)->not->toContain($text);
    }

    expect(frontendSource('canvas/legend.ts'))
        ->toContain('gloss: type.gloss,')
        ->toContain("text: option?.legend_text ?? '',");

    $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->count('catalog.link_types', 9)
            ->where('catalog.link_types.0.gloss', 'requisição e resposta; o chamador fica esperando')
            ->where('catalog.sequence_modes.0.legend_text', 'quando um bloco dispara mais de uma coisa, o número diz o que vem antes')
            ->etc());
});

it('recolhe e expande a legenda por um controle só', function () {
    expect(frontendSource('components/prancheta/LegendPanel.vue'))
        ->toContain('data-testid="legend-toggle"')
        ->toContain('const { open, toggle } = useLegend();')
        ->toContain('@click="toggle"')
        ->toContain(':aria-expanded="open"')
        ->toContain('v-show="open"');
});

it('guarda o recolhimento no navegador, sob a chave sd-legend', function () {
    expect(frontendSource('prancheta/legend.ts'))
        ->toContain("export const LEGEND_STORAGE_KEY = 'sd-legend';")
        ->toContain("export const LEGEND_CLOSED = '0';")
        ->toContain('return stored !== LEGEND_CLOSED;');

    expect(frontendSource('composables/useLegend.ts'))
        ->toContain('localStorage.getItem(LEGEND_STORAGE_KEY)')
        ->toContain('localStorage.setItem(LEGEND_STORAGE_KEY, legendStorageValue(value));');
});

it('não manda o estado da legenda para o servidor nem suja a sessão', function () {
    expect(frontendSource('composables/useLegend.ts'))
        ->not->toContain('@inertiajs')
        ->not->toContain('router.');

    expect(frontendSource('prancheta/session.ts'))->not->toContain('sd-legend');

    expect(file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php')))
        ->not->toContain('sd-legend');

    preg_match('/export type SessionBody = \{(.*?)\n\};/s', frontendSource('prancheta/session.ts'), $body);

    expect($body[1])->not->toContain('legend');
});

it('devolve ao enquadramento a largura que a legenda recolhida deixa de ocupar', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toContain('const { open: legendOpen } = useLegend();')
        ->toMatch('/watch\(\s*\[\(\) => legend\.value\.empty, legendOpen\],\s*\(\[empty, open\]\) =>\s*engine\.setLegendWidth\(empty \|\| !open \? 0 : LEGEND_WIDTH\),/');

    expect(frontendSource('canvas/view.ts'))
        ->toContain('? options.legendWidth + LEGEND_GUTTER');
});

it('não faz pan nem zoom quando o gesto nasce dentro da legenda', function () {
    expect(frontendSource('composables/useStageInteraction.ts'))
        ->toContain('const OVERLAYS = \'[data-testid="legend"],[data-testid="zoombar"]\';')
        ->toMatch('/if \(event\.button === 2 \|\| !target \|\| target\.closest\(OVERLAYS\)\) \{\s*return;/')
        ->toMatch('/if \(target\?\.closest\(OVERLAYS\)\) \{\s*return;/');
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect(canvasTestNames())->toContain($name);
})->with([
    'legendData_lists_only_present_categories_with_counts',
    'legendData_lists_only_used_link_types_in_catalog_order',
    'legendData_flags_untyped_edges',
    'legendData_flags_sequence_only_when_numbering_is_on',
    'legendData_is_empty_for_empty_diagram',
    'dangling_edges_do_not_appear_in_legend',
]);
