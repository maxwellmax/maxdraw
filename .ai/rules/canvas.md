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

## Numeração: a ordem das saídas é a posição no array edges
`sequence.ts` deriva tudo do desenho: nenhuma aresta guarda número e não existe campo de ordem no estado. A ordem de saída de um bloco é a posição da aresta em `state.edges` — por isso `moveSeq()` reordena o array trocando a aresta com a vizinha *de mesma origem* (as arestas dos outros blocos ficam onde estão), e por isso reordenar muda tanto `outSeq` quanto a travessia de `flowSeq`.

`outSeq` só numera bloco com 2+ saídas válidas; `flowSeq` é DFS que começa pelo nó da categoria `client` sem entrada (`CLIENT_CATEGORY` em catalog.ts), marca cada aresta uma vez (é o que faz ciclo terminar) e cobre órfãos no fim.

Reordenar é mutação de aresta: passa por `CanvasEngine.mutateEdge` e empilha desfazer. Trocar de modo NÃO empilha — é visualização (US-4.3), mas é persistido em `seq_mode`. Os nomes dos três modos vêm do catálogo do servidor (`sequence_modes`); o cliente só define a ordem do menu em `SEQUENCE_MENU` (off, out, flow), que é diferente da `position` do seeder (ordem do ciclo).
