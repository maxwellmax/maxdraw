---
paths:
  - 'resources/js/canvas/**'
---

# Canvas

## O motor do canvas é TypeScript puro — nada de Vue nem de DOM
Nenhum arquivo de `resources/js/canvas/` pode importar Vue/Inertia nem tocar em `document`/`window` — `tests/Feature/Frontend/CanvasEngineTest.php` varre o pacote inteiro por isso. A camada Vue mede e traduz; o motor decide.

Consequências práticas: a altura real de cada bloco chega por `engine.measure(id, height)` (o `ResizeObserver` está no `CanvasNode.vue`) e o tamanho do palco por `engine.setSize()`. Sem isso, `bez()` usa a altura padrão 86 e `fit()` enquadra num palco de tamanho zero.

`CanvasEngine` é instanciado com `reactive(new CanvasEngine(...))` no `Board.vue`: use `private` do TS, nunca campos `#privados` — o proxy do Vue quebra com eles.

Os números do protótipo (132/86, pads 7 e 10, controle 0.45/26/110, ponta 9.5/3.9, snap 4, colisão 122×80, 200/400/60) são constantes exportadas e estão travadas por teste; os limites 200/400/60 são conferidos contra as constantes privadas de `TrainingSessionUpdateRequest`.

Recusa de mutação volta como `{ ok: false, reason }`. `nodeLimitReached`/`edgeLimitReached` têm o nome da chave em `prancheta/warnings.ts` para ir direto ao toast; `invalidLink` e `unknownComponent` são recusa silenciosa.

## Ligações: a bandeira dashed manda, o tipo só a semeia
Os nove tipos de ligação vêm do servidor (`catalog.link_types` → `engine.setLinkTypes`); não existe tabela de tipos no cliente. `links.ts` guarda selo e `dash_array`, nunca cor.

`dashOf(index, edge)` lê `edge.dashed`, não o tipo: escolher um tipo é que semeia a bandeira (`setEdgeKind` faz `dashed = dash_array !== null` e liga `bidir` no `ws`). Depois disso o usuário alterna as duas à mão, e `dashOf` devolve o `dash_array` do tipo ou `MANUAL_DASH_ARRAY` quando o tipo é contínuo. Não faça `dashOf` derivar do tipo direto — desligar o tracejado de um `event` deixaria de funcionar.

`edgeColor` é sempre `colorOf(categoria do nó de origem)`. Inverter a seta recolore o selo por consequência, não por regra extra.

Toda mudança de aresta (tipo, rótulo, tracejado, mão dupla, inversão) passa por `CanvasEngine.mutateEdge`, que é o único ponto que empilha desfazer.

## Numeração: a ordem é explícita e há uma sequência por diagrama
`order.ts` não deriva nada do desenho: cada aresta guarda o próprio número em `edge.order` (`number | null`), e a posição no array `edges` deixou de significar qualquer coisa. Existe **uma** sequência por diagrama — não há modo de numeração —, e `null` quer dizer "fora da sequência".

Toda mutação de ordem sai por `densify()`: é o único ponto de saída, e é ele que faz do conjunto `{1..N}` uma consequência em vez de uma invariante a manter em cada caminho. `setOrder()` empurra um número adiante quem ocupava `k` ou mais; `clearOrder()`, `removeEdge()` e `removeNode()` fecham o buraco uma vez só, no fim da remoção. Aresta nova nasce com `order: null` (`addEdge`).

`densify()` varre `state.edges` inteiro, órfã inclusa: a aresta cujo bloco sumiu mantém o número dela, conta para `N` e apenas não desenha badge, porque não é desenhada (`liveEdges`). Enquanto a órfã existir a tela pode mostrar `1, 3, 4` — é o preço de `snapshot()`/`restore()` não densificarem, que é o que faz desfazer devolver o mapa `id → order` exatamente como estava.

`autoNumber()` é uma BFS determinística sobre as arestas vivas, e as raízes são um fallback de três degraus: primeiro o cliente sem entrada (`CLIENT_CATEGORY` em catalog.ts), depois as outras entradas do diagrama, por último todos os nós — é o terceiro degrau que alcança quem só existe dentro de um ciclo. A fila é array com índice de leitura e a expansão de cada bloco sai do array `edges`; nenhum `Set`/`Map` é iterado por conta própria, e é isso que torna o resultado reproduzível. Cada aresta é visitada uma vez, o que faz ciclo terminar. A travessia segue só `from → to`: `bidir` é bandeira de desenho, não tem semântica de grafo, não conta como aresta de entrada em `from` e ocupa **um** número como qualquer outra.

Mudar `order` é mutação de aresta: passa por `CanvasEngine.mutateEdge` e empilha desfazer — a numeração automática empilha **um** passo para o diagrama inteiro. O toggle `showConnectionOrder` NÃO empilha e não entra no `DiagramSnapshot`: é visualização (US-3.5), embora seja persistido na sessão em `show_connection_order`.

## A legenda é derivada, e o recolhimento dela não é do motor
`legendData()` (canvas/legend.ts) é a fonte única do que a legenda mostra — categorias presentes com contagem, tipos usados, bandeira de "sem tipo" e a seção de sequência. Ela não guarda estado nem aceita configuração: só arestas válidas (`liveEdges`) contam, e a seção de sequência só existe quando há pelo menos uma aresta viva numerada e `showConnectionOrder` está ligado. Quando a Phase 19 exportar SVG, é daqui que a legenda do arquivo sai — não monte outra.

A glosa dos tipos vem do catálogo do servidor (`gloss` do LinkType). O texto da seção de ordem é a exceção aprovada e delimitada (UI-07): `ORDER_NAME`/`ORDER_GLOSS` são constantes do próprio `canvas/legend.ts`, ao lado de `UNTYPED_NAME`/`UNTYPED_GLOSS`, porque não sobrou lookup para carregá-lo. O limite é este: **constante única no cliente sim, catálogo paralelo no cliente não** — a exceção vale para o par `ORDER_NAME`/`ORDER_GLOSS` e não autoriza recriar no cliente nenhuma tabela de lookup do servidor. `legendData` devolve `color` só nas categorias — a amostra de traço é neutra por contrato (US-5.1).

O recolhimento é preferência do navegador e mora fora do motor: `prancheta/legend.ts` (chave `sd-legend`, `'0'`/`'1'`) + `composables/useLegend.ts`, com o `ref` no módulo porque o Board precisa dele para zerar `setLegendWidth` — legenda recolhida ou vazia não reserva largura no "enquadrar tudo".

## Exportar SVG: o arquivo é o mesmo desenho, com as cores já resolvidas
`canvas/svg.ts` (`buildSVG`/`svgLegend`) não tem régua própria: bounds, curva, ponta, chip, numeração e legenda saem de `diagram.ts`, `geometry.ts`, `edges.ts`, `order.ts` e `legendData()`. Mudar o desenho da tela muda o arquivo de graça — não monte um segundo motor.

A ordem de emissão é arestas → blocos → chips → legenda. Os chips vêm depois dos blocos de propósito: no arquivo não há z-index, e bloco vizinho cobriria o rótulo.

O SVG recebe uma `SvgPalette` (token CSS → cor) porque o arquivo baixado não tem as variáveis do documento para consultar; quem a entrega é `useTheme().vars`. Token ausente cai em `currentColor`, nunca em atributo vazio. Nada de `var(--x)` na saída.

`UNTYPED_NAME`/`UNTYPED_GLOSS` moram em `canvas/legend.ts` e são importados tanto pelo `LegendContent.vue` quanto pelo `svg.ts` — a legenda da tela e a do arquivo são a mesma.

O download mora fora do motor (`lib/downloadFile.ts`, com DOM) e o nome do arquivo em `prancheta/export.ts`: `canvas/**` e `prancheta/**` são varridos por testes que proíbem `document.`/`window.`.
