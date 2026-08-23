<?php

use App\Models\Problem;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * A exportação em SVG (Phase 18): o motor que monta o arquivo, a legenda que
 * sai abaixo do desenho e o botão que baixa tudo no cliente. O comportamento é
 * testado pelo Vitest; o que a suíte PHP guarda aqui é o contrato — a pureza do
 * módulo, os números do protótipo e a cobertura que a fase exige.
 */
it('monta o SVG a partir do mesmo estado que a tela desenha', function () {
    expect(frontendSource('canvas/svg.ts'))
        ->toContain('export function buildSVG(context: SvgContext): string {')
        ->toContain('const bounds = diagramBounds(state.nodes, heights);')
        ->toContain('const geometry = bez(edge, state.nodes, heights);')
        ->toContain('state.showConnectionOrder ? edge.order : null,')
        ->not->toContain('seqMap(');

    expect(frontendSource('canvas/engine.ts'))
        ->toMatch('/toSVG\(palette: SvgPalette\): string \{\s*return buildSVG\(\{\s*state: this\.state,\s*index: this\.index,\s*linkIndex: this\.linkIndex,\s*palette,\s*heights: this\.heights,\s*\}\);\s*\}/');
});

it('congela os números do enquadramento e do chip', function (string $declaration) {
    expect(frontendSource('canvas/svg.ts'))->toContain($declaration);
})->with([
    'margem do enquadramento' => ['export const SVG_PADDING = 40;'],
    'vão até a legenda' => ['export const LEGEND_GAP = 30;'],
    'quebra do rótulo' => ['export const LABEL_WRAP_CHARS = 18;'],
    'linhas do rótulo' => ['export const MAX_LABEL_LINES = 3;'],
    'altura do pill do número' => ['export const SEQ_PILL_HEIGHT = 15;'],
    'respiro do pill do número' => ['export const SEQ_PILL_PADDING = 4;'],
    'largura de um dígito' => ['export const SEQ_DIGIT_WIDTH = 6;'],
    'vão entre pill e texto do chip' => ['export const SEQ_LEAD_GAP = 5;'],
    'largura mínima da legenda' => ['export const LEGEND_MIN_WIDTH = 90;'],
]);

it('reserva no enquadramento a largura e a altura calculadas da legenda', function () {
    expect(frontendSource('canvas/svg.ts'))
        ->toMatch('/Math\.max\(bounds\.x1 - bounds\.x0, legend\.width\) \+ SVG_PADDING \* 2/')
        ->toMatch('/\(legend\.height > 0 \? legend\.height \+ LEGEND_GAP : 0\)/')
        ->toContain('bounds.y1 + LEGEND_GAP,');
});

it('emite os chips depois dos blocos, para vizinho não cobrir rótulo', function () {
    $source = frontendSource('canvas/svg.ts');

    expect($source)->toContain('`${body}${chips}${legend.markup}</svg>`');

    expect(strpos($source, 'chips += chipMarkup('))
        ->toBeLessThan(strpos($source, 'body += nodeMarkup(node, index, heights, palette);'));
});

it('tira a cor da seta da categoria da origem e o traço do tipo', function () {
    expect(frontendSource('canvas/svg.ts'))
        ->toContain('const color = svgColor(palette, edgeColor(index, edge, state.nodes));')
        ->toContain('const dash = dashOf(linkIndex, edge);')
        ->toContain('${dash ? ` stroke-dasharray="${dash}"` : \'\'}')
        ->toMatch('/if \(edge\.bidir\) \{\s*body \+= arrowMarkup\(\s*geometry\.x1,\s*geometry\.y1,/');
});

it('resolve as cores do tema em vez de deixar variáveis CSS no arquivo', function () {
    expect(frontendSource('canvas/svg.ts'))
        ->toContain('const VAR_REFERENCE = /^var\((--[a-z0-9-]+)\)$/;')
        ->toContain('export function svgColor(palette: SvgPalette, reference: string): string {')
        ->toContain("export const FALLBACK_COLOR = 'currentColor';");

    expect(frontendSource('pages/Board.vue'))
        ->toContain('const { toggle: toggleTheme, vars: themeVars } = useTheme();')
        ->toContain('engine.toSVG(themeVars.value),');
});

it('monta a legenda do arquivo a partir da mesma legendData da tela', function () {
    expect(frontendSource('canvas/svg.ts'))
        ->toContain('legendData(state, index, linkIndex),')
        ->toContain('export function svgLegend(')
        ->toContain('if (data.empty) {')
        ->toContain('return { width: 0, height: 0, markup: \'\' };')
        ->toContain('name: UNTYPED_NAME,')
        ->toContain('gloss: UNTYPED_GLOSS,')
        ->toContain('name: ORDER_NAME,')
        ->toContain('gloss: ORDER_GLOSS,');
});

it('alarga o chip do arquivo conforme os dígitos do número, sem constante fixa', function () {
    $source = frontendSource('canvas/svg.ts');

    expect($source)
        ->toMatch('/export function seqPillWidth\(order: number\): number \{\s*return Math\.max\(\s*SEQ_PILL_HEIGHT,\s*String\(order\)\.length \* SEQ_DIGIT_WIDTH \+ SEQ_PILL_PADDING \* 2,\s*\);\s*\}/')
        ->toMatch('/export function seqLead\(order: number\): number \{\s*return seqPillWidth\(order\) \+ SEQ_LEAD_GAP;\s*\}/')
        ->toContain('const lead = order === null ? 0 : seqLead(order);')
        ->not->toContain('export const SEQ_LEAD =');
});

it('desenha o número do arquivo como pill sólido, na cor da seta', function () {
    $source = frontendSource('canvas/svg.ts');

    preg_match('/function seqPillMarkup\((.*?)\n\}/s', $source, $pill);

    expect($pill[1])
        ->toContain('rx="${SEQ_PILL_HEIGHT / 2}" fill="${color}"')
        ->toContain('fill="${paper}">${value}</text>');

    // A amostra da legenda é o mesmo pill, e o chip da seta também.
    expect($source)
        ->toMatch('/function seqSampleMarkup\([^)]*\): string \{\s*return seqPillMarkup\(/')
        ->toContain(': seqPillMarkup(mx, my, order, color, paper);')
        ->not->toContain('<circle');
});

it('desenha na tela o mesmo pill do arquivo, pela mesma constante', function () {
    expect(frontendSource('components/prancheta/EdgeChip.vue'))
        ->toContain("} from '@/canvas/svg';")
        ->toContain('minWidth: `${seqPillWidth(props.order ?? 1)}px`,')
        ->toContain('height: `${SEQ_PILL_HEIGHT}px`,')
        ->toContain('paddingInline: `${SEQ_PILL_PADDING}px`,');

    expect(frontendSource('components/prancheta/LegendContent.vue'))
        ->toContain("import { SEQ_PILL_HEIGHT, SEQ_PILL_PADDING, seqPillWidth } from '@/canvas/svg';")
        ->toContain('minWidth: `${seqPillWidth(1)}px`,');
});

it('mantém a amostra de traço da legenda neutra, como na tela', function () {
    $source = frontendSource('canvas/svg.ts');

    preg_match('/function sampleMarkup\((.*?)\n\}/s', $source, $sample);

    expect($sample[1])
        ->toContain('stroke="${color}"')
        ->not->toContain('--c-');

    expect($source)->toContain("const muted = svgColor(palette, '--ink-3');");
});

it('mantém o módulo do SVG livre de framework e de DOM', function () {
    expect(frontendSource('canvas/svg.ts'))
        ->not->toContain("from 'vue'")
        ->not->toContain("from '@inertiajs")
        ->not->toContain('document.')
        ->not->toContain('window.');
});

it('gera o arquivo no cliente, sem passar pelo servidor', function () {
    expect(frontendSource('lib/downloadFile.ts'))
        ->toContain('URL.createObjectURL(new Blob([contents], { type: mimeType }))')
        ->toContain("document.createElement('a')")
        ->toContain('anchor.click();')
        ->toContain('URL.revokeObjectURL(url);')
        ->not->toContain('@inertiajs')
        ->not->toContain('fetch(');

    expect(collect(app('router')->getRoutes())->contains(
        fn ($route): bool => str_contains($route->uri(), 'export') || str_contains($route->uri(), 'svg')
    ))->toBeFalse();
});

it('nomeia o arquivo pelo slug do problema, ou prancheta sem problema', function () {
    seedCatalog();

    expect(frontendSource('prancheta/export.ts'))
        ->toContain("export const FREE_BOARD_NAME = 'prancheta';")
        ->toContain("export const SVG_EXTENSION = '.svg';")
        ->toContain('return (problem?.slug ?? FREE_BOARD_NAME) + SVG_EXTENSION;');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('exportFileName(problem.value),');

    $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Board')
            ->where('catalog.problems.0.slug', Problem::query()->orderBy('id')->value('slug'))
            ->etc());
});

it('recusa exportar prancheta vazia e avisa por toast', function () {
    expect(frontendSource('pages/Board.vue'))
        ->toMatch('/if \(engine\.isEmpty\) \{\s*warn\(\'exportEmptyCanvas\'\);\s*\n\s*return;\s*\}/');

    expect(frontendSource('prancheta/warnings.ts'))
        ->toContain("exportEmptyCanvas: 'Canvas vazio — não há nada para exportar.',");

    expect(frontendSource('canvas/svg.ts'))
        ->toMatch('/if \(!bounds\) \{\s*return \'\';\s*\}/');
});

it('mantém o botão SVG na barra superior, ligado à exportação', function () {
    expect(frontendSource('components/prancheta/BoardTopBar.vue'))
        ->toContain('data-testid="export-button"')
        ->toContain('title="Baixar o diagrama em SVG"')
        ->toContain("'export-svg': [];")
        ->toContain('@click="$emit(\'export-svg\')"');

    expect(frontendSource('pages/Board.vue'))
        ->toContain('@export-svg="exportSvg"');
});

it('cobre no Vitest cada teste que a fase pede', function (string $name) {
    expect([...canvasTestNames(), ...pranchetaTestNames()])->toContain($name);
})->with([
    'svg_chips_are_emitted_after_node_groups',
    'svg_viewbox_covers_all_nodes_with_padding',
    'svg_width_accommodates_a_wider_legend',
    'svg_edge_stroke_matches_source_category_color',
    'svg_bidirectional_edge_has_two_arrowheads',
    'svg_chip_widens_by_the_pill_lead',
    'svg_pill_grows_with_the_digit_count',
    'svg_pill_is_solid_in_the_edge_color',
    'svg_hides_every_badge_when_show_connection_order_is_off',
    'svg_edge_without_order_has_no_badge',
    'svg_bare_edge_with_number_renders_only_the_pill',
    'svg_labels_wrap_to_at_most_three_lines',
    'svg_legend_matches_screen_legend_content',
    'svg_legend_samples_are_neutral_colored',
    'svg_legend_is_absent_for_empty_diagram',
    'svg_legend_dimensions_feed_the_viewbox',
    'svg_file_is_named_after_the_chosen_problem_slug',
    'svg_file_falls_back_to_prancheta_without_a_problem',
]);
