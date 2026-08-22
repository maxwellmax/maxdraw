# Prancheta de System Design — Project Description

## Overview

A **Prancheta de System Design** é uma ferramenta de **treino guiado para entrevistas de system design**. Ela não é um editor de diagramas de propósito geral: é uma bancada de treino onde o candidato recebe um **enunciado**, dispara um **cronômetro dividido em fases com peso**, desenha a arquitetura com um **vocabulário fechado de componentes**, confere um **checklist por fase** e faz as **estimativas de capacidade** — tudo numa tela só. O público é uma pessoa treinando sozinha; o produto é público, mas de uso individual.

A decisão que define o escopo é: **a ferramenta não avalia o treino**. Não há nota, não há revisor automático, não há IA julgando o desenho. O único "gabarito" é uma **lista estática de tópicos que o problema cobra**, colapsada e explicitamente marcada para abrir **só depois de terminar**. O valor está no ritual — enunciado, relógio, roteiro, desenho — e não em feedback gerado. Foram **rejeitados de propósito**: forma livre, cores customizadas, camadas, texto solto e multiplayer. É tudo aquilo que transforma Miro/Excalidraw em trabalho de desenhista em vez de treino de arquitetura.

Existe hoje um **protótipo funcional completo** em `docs/design/pranchetasystemdesign.html` (arquivo único, 1571 linhas, autocontido), publicado como artifact em `https://claude.ai/code/artifact/76b5b52c-e36a-437d-b164-6fb79e2e162d`. Esse artifact passa a ser **legado congelado**: nada mais será publicado nele, e a pendência de publicação (legenda, numeração de sequência, DLQ, tipo Falha/Retry) **deixa de existir** como tarefa. O HTML permanece no repositório como **referência visual e comportamental** — é o contrato de como a coisa deve parecer e reagir.

O projeto agora é **reconstruir essa ferramenta como aplicação Laravel** neste repositório (`maxdraw`), como **monólito modular**, seguindo SOLID e clean code, **sem over-engineering**. O MVP é **paridade funcional com o protótipo** mais o que o backend acrescenta: **multiusuário com registro**, sessões persistidas em banco por usuário e o catálogo (problemas, componentes, tipos de ligação, fases e checklist) em tabelas populadas por seeder versionado. Ficam **fora do v1**: modo apresentação, templates de partida por problema, histórico de desempenho entre sessões, autolayout e BFF como bloco próprio.

### Key Concepts

- **Sessão de treino:** a unidade de trabalho e de persistência. Guarda problema escolhido, diagrama (nós + ligações), estado do checklist, notas, parâmetros de estimativa, duração escolhida, tempo decorrido e modo de numeração. Pertence a um usuário; um usuário tem várias e retoma a última aberta.
- **Problema:** enunciado de entrevista. Tem contexto em prosa, **requisitos funcionais**, **escala alvo** e **tópicos que o problema cobra** (o gabarito). São **14** no v1: encurtador de URL, feed de rede social, mensageria em tempo real, streaming de vídeo, carona sob demanda, armazenamento de arquivos, rate limiter distribuído, sistema de notificações, autocomplete de busca, venda de ingressos, web crawler, métricas e monitoramento, carteira e pagamentos, ranking em tempo real.
- **Componente (bloco):** item do vocabulário fechado. São **28**, com ícone próprio, distribuídos em **6 categorias com cor**: *Cliente* (Navegador, App Mobile, Serviço Externo), *Rede & Borda* (DNS, CDN, WAF/Firewall, Load Balancer, API Gateway), *Computação* (API REST, GraphQL, gRPC, Serviço, Worker, Job Agendado, WebSocket/Push), *Dados* (Banco Relacional, Réplica de Leitura, NoSQL, Cache, Object Storage, Índice de Busca, Data Warehouse), *Assíncrono* (Fila, Stream/Log, DLQ), *Operação* (Auth/Identidade, Métricas & Logs, Config/Flags). Um componente pode ter **nome longo e nome curto** (ex.: `DLQ — fila de falhas` / `DLQ`); o curto é o rótulo padrão do bloco.
- **Categoria:** agrupa componentes e **é a única fonte de cor do sistema**. Cor = categoria do componente, sempre. Nada mais introduz cor nova.
- **Ligação (aresta):** seta direcionada entre dois blocos, criada arrastando de uma das 4 bolinhas de um bloco até outro. Tem origem, destino, tipo, rótulo livre, e flags manuais de tracejado, mão dupla e inversão.
- **Tipo de ligação:** **9 tipos** mais "sem tipo" — HTTP/REST (`HTTP`), gRPC (`gRPC`), WebSocket (`WS`, já mão dupla), Evento/assíncrono (`async`), Consulta ao banco (`query`), Cache lookup (`cache`), Replicação (`replica`), Lote/ETL (`batch`), Falha/Retry–DLQ (`retry`). O tipo define **o padrão do traço**, nunca a cor: contínuo por padrão, `5 4.5` para async/replicação/lote, `2 4.5` (pontilhado fino) para Falha/Retry. A seta ganha um **selo** colorido pela categoria da **origem**, e o rótulo livre aparece ao lado (ex.: `async · GET /feed`).
- **Numeração de sequência:** derivada do estado, nunca digitada. **3 modos**, guardados na sessão (`seqMode`): `out` (padrão) numera **as saídas de cada bloco** — ①②③ quando um bloco dispara mais de uma coisa; `flow` numera **o fluxo inteiro** de 1 a N por busca em profundidade a partir dos clientes sem entrada, seguindo a ordem de saída de cada bloco, cobrindo órfãos no fim e à prova de ciclo; `off` desliga. O número vive **dentro do chip da seta**, herda a cor da categoria da origem, e a ordem é a posição da aresta na lista — reordenável pela barra flutuante (`‹ 2/3 ›`).
- **Legenda automática:** painel recolhível no canto inferior direito, montado a partir do que está desenhado. Lista **categorias presentes** (quadradinho colorido + nome + contagem), **tipos de ligação usados** (amostra do traço real em tom neutro + selo + nome + uma glosa de uma linha ensinando o vocabulário) e o **modo de sequência ativo**, quando há numeração. Some quando o canvas está vazio, é reservada no enquadramento e sai no export SVG.
- **Fase:** etapa do roteiro, com **peso sobre a duração**. São **5**: Requisitos & escopo (11%), Estimativas (11%), API & modelo de dados (18%), Desenho de alto nível (27%), Escala & trade-offs (33%). A fase corrente abre sozinha conforme o cronômetro avança.
- **Item de checklist:** afirmação verificável dentro de uma fase, marcável durante o treino. São **25** no total (4 + 5 + 5 + 5 + 6).
- **Estimativa de capacidade:** calculadora com **2 modos** — *por usuários* (DAU × ações de escrita por usuário/dia) e *por volume mensal* (escritas por mês, o formato que o entrevistador costuma dar). **Mês = 30 dias.** Parâmetros comuns: leituras por escrita, tamanho médio do registro (KB), fator de pico, retenção em anos. Saída idêntica nos dois modos, com a linha destacada mudando conforme o modo.
- **Duração:** 30, 45 ou 60 minutos. Define o tamanho de cada fase pelo peso.
- **Export SVG:** o diagrama inteiro em SVG, com selos, padrões de traço, números de sequência e a legenda num bloco abaixo do desenho. Gerado no cliente.
- **Catálogo:** o conjunto imutável-em-runtime de problemas, componentes, categorias, tipos de ligação, fases e itens de checklist. Mora em tabelas, populado por **seeder versionado**; editar o catálogo é mexer no seeder, não na interface.
- **Motor do canvas:** módulo TypeScript puro, sem framework, que concentra geometria, tipos de ligação, `outSeq`/`flowSeq`, dados da legenda e export SVG. Testável isoladamente no Vitest; a camada Vue apenas monta e alimenta.

## Tech Stack

| Camada | Tecnologia |
|---|---|
| Linguagem backend | PHP 8.4.23 |
| Framework backend | Laravel 13.26.1 |
| Gerenciador de dependências | Composer 2.10.2 |
| Guidelines para agentes | Laravel Boost |
| Frontend | Inertia 3 + Vue 3 + TypeScript (a versão de Inertia que o starter kit oficial do Laravel 13 entrega — ver nota abaixo) |
| Estilo | Tailwind CSS 4 (tokens de cor do protótipo portados, incluindo tema claro/escuro) |
| Build frontend | Vite (versão do starter kit oficial do Laravel 13) · Node 20.20.2 / npm 10.8.2 |
| Canvas | Módulo TypeScript próprio — DOM + SVG, sem biblioteca de diagramas |
| Banco (dev/prod) | MySQL 8.0.46 |
| Banco (testes) | SQLite (`pdo_sqlite` disponível) |
| Autenticação | Starter kit oficial do Laravel (registro + login por e-mail e senha) |
| Ambiente containerizado | Docker Compose / Laravel Sail — versionado no repo para quem clonar o projeto público (Docker 29.1.3 disponível) |
| Ambiente do dia a dia (VPS) | MySQL local + `php artisan serve` + `npm run dev`, sem container |
| Testes backend | Pest (feature nas rotas/API, unit no domínio) |
| Testes frontend | Vitest sobre o motor do canvas (geometria, `outSeq`/`flowSeq`, legenda, export SVG) |
| Análise estática | Larastan / PHPStan |
| Formatação | Laravel Pint (PHP) · ESLint + Prettier (TS/Vue) |
| Fontes | Google Fonts — Archivo (UI), IBM Plex Mono (dados, selos, cronômetro), Source Serif 4 (só o enunciado) |
| Referência de design | `docs/design/pranchetasystemdesign.html` + `docs/design/z1.png`, `z2.png`, `out3.png` |

Disponível no ambiente mas **fora do v1**: Redis 7.0.15 e PostgreSQL 16.15.

> **Nota — Inertia 2 → 3.** Este documento foi escrito quando o starter kit oficial
> do Laravel ainda entregava Inertia 2. O starter kit do Laravel 13 instala
> **Inertia 3** (`@inertiajs/vue3` ^3.0, `inertiajs/inertia-laravel` ^3.0), e o
> layout raiz gerado por ele usa a sintaxe de componentes v3 (`<x-inertia::app />`,
> `<x-inertia::head>`) junto com o plugin `@inertiajs/vite`. Fazer downgrade para
> Inertia 2 significaria reescrever à mão o bootstrap do starter kit, sem ganho de
> produto. A decisão fechada é **manter o Inertia 3 do starter kit**.

## Core Workflows

### 1. Registrar-se, entrar e retomar o treino

1. Visitante acessa a raiz e é levado ao login; registro aberto (nome, e-mail, senha), fluxo padrão do starter kit.
2. Autenticado, o servidor carrega a **sessão corrente** do usuário — a última aberta — junto com o **catálogo** completo, numa única resposta Inertia.
3. Se o usuário não tem nenhuma sessão, cria-se uma sessão vazia e o **seletor de problemas abre sozinho** (o protótipo abre com ~450 ms de atraso quando não há problema nem blocos).
4. Todo dado de sessão é **escopado ao usuário autenticado**; nenhuma rota expõe sessão de terceiro.

### 2. Escolher o problema e iniciar a sessão

1. O usuário abre o seletor e escolhe um dos 14 problemas.
2. O painel mostra **contexto**, **requisitos funcionais** e **escala alvo**. Os **tópicos que o problema cobra** ficam num bloco colapsado, rotulado *"abra só depois de terminar"*.
3. O usuário escolhe a duração: **30, 45 ou 60 minutos**.
4. O cronômetro divide a duração pelos pesos das fases — em 45 min: ~5, ~5, ~8, ~12 e ~15 minutos.

```http
POST /api/sessions
{ "problem_id": 1, "duration_minutes": 45 }

201 Created
{ "id": 12, "problem_id": 1, "duration_minutes": 45, "elapsed_seconds": 0,
  "seq_mode": "out", "nodes": [], "edges": [], "checks": {}, "notes": "",
  "estimate": { "mode": "user", "dau": 1000000, "writes_per_user_day": 10,
                "writes_per_month": 10000000, "read_write_ratio": 100,
                "record_size_kb": 1, "peak_factor": 3, "retention_years": 3 } }
```

### 3. Desenhar o diagrama

1. **Clique na paleta** posiciona o bloco já pronto na área visível, sem colisão com blocos existentes, e abre o campo de nome. Não se desenha retângulo.
2. O rótulo inicial é o **nome curto** do componente; duplo clique renomeia.
3. **Ligação:** arrastar de uma das 4 bolinhas do bloco até outro bloco. A geometria é centro a centro, recortada na borda do retângulo, com bezier cúbico de controle no eixo dominante.
4. Navegação: **pan** arrastando o vazio, **zoom** na roda, **enquadrar tudo** (que reserva a largura da legenda), **snap de 4 px**.
5. **Del** apaga o selecionado. **Ctrl+Z / Ctrl+Shift+Z** desfazem e refazem — a pilha guarda apenas nós e arestas, com limite de 60 estados.
6. Regra invariante: **cor = categoria**. Qualquer adição futura obedece.

### 4. Tipar as ligações e ordenar a sequência

1. Selecionar uma seta abre a barra flutuante com o menu dos **9 tipos + "Sem tipo"**; o menu mostra o padrão de traço real em cada amostra.
2. Escolher o tipo aplica o traço, o selo (colorido pela categoria da origem) e, no caso do WebSocket, a mão dupla. Tracejado, mão dupla e inversão continuam forçáveis manualmente.
3. O **rótulo livre** convive com o selo: `async · GET /feed`.
4. O botão `1→2` da barra de zoom alterna os modos `out` → `flow` → `off`; o modo fica salvo na sessão. **Trocar de modo não entra na pilha de undo** — é ajuste de visualização.
5. Com uma seta selecionada, `‹ 2/3 ›` antecipa ou adia **aquela saída do bloco**, o que também define a ordem da travessia no modo `flow`.

### 5. Ler a legenda automática

1. A legenda se monta sozinha a partir do desenho e **some quando não há nada desenhado**.
2. *Blocos*: uma linha por categoria presente — quadradinho na cor da categoria, nome e contagem.
3. *Ligações*: uma linha por tipo usado — amostra do traço real em tom neutro, selo, nome e a glosa (ex.: `retry — estourou as tentativas e foi para a DLQ; ninguém consumiu`). Setas sem tipo produzem `sem tipo — clique na seta e escolha o protocolo`.
4. *Sequência*: aparece só quando há numeração, com o texto do modo ativo.
5. É recolhível, com o estado guardado localmente no navegador; `pointerdown` e `wheel` dentro dela não fazem pan nem zoom.

### 6. Cronometrar as fases e marcar o checklist

1. O usuário dá play; o cronômetro conta e mostra a fase corrente e o quanto resta dela.
2. A fase corrente **abre sozinha** conforme o tempo cruza cada fronteira; as outras ficam colapsadas.
3. O usuário marca os itens; o estado é por sessão.
4. Pausar/retomar preserva o tempo decorrido; o valor é persistido junto com o resto da sessão.

### 7. Fazer as estimativas de capacidade

1. O usuário escolhe o modo — *por usuários* ou *por volume mensal* — e o modo fica salvo na sessão.
2. Preenche os campos do modo escolhido mais os comuns: leituras por escrita, tamanho médio do registro (KB), fator de pico, retenção (anos).
3. A saída é idêntica nos dois modos, recalculada a cada tecla:

```
Escritas por mês · Escritas por dia · Escritas por segundo
Leituras por dia · Leituras por segundo · Total por segundo no pico
Dados novos por dia · Dados novos por ano
Armazenamento em N ano(s) · Banda de saída no pico
```

4. Fórmulas: escritas/dia = `perMonth / 30` ou `dau × ações`; leituras/dia = escritas × razão; qps = /86400; pico = `(wqps + rqps) × fator`; banda de saída no pico = `rqps × fator × tamanho`.
5. A **frase de conclusão** muda com a ordem de grandeza do pico: ≥ 50k qps → *"esse pico exige cache e particionamento, diga isso em voz alta"*; ≥ 5k → *"dá para servir com réplicas de leitura e cache"*; abaixo disso → *"uma instância bem dimensionada aguenta, não invente complexidade"*.

### 8. Salvar automaticamente

1. Qualquer alteração marca a sessão como suja e mostra o indicador *"não salvo"*.
2. O cliente **debounce** e envia o estado inteiro da sessão; o indicador passa a *"salvando…"* e depois *"salvo"*.
3. O servidor grava numa transação: `nodes`, `edges`, `checks`, `estimate` como **colunas JSON** da sessão; `notes`, `elapsed_seconds`, `duration_minutes`, `seq_mode` como colunas próprias.
4. Falha de rede mantém o indicador sujo e tenta de novo; o usuário nunca perde o desenho por causa de uma requisição perdida.

```http
PUT /api/sessions/12
{ "nodes": [ { "id": "n1", "type": "api", "label": "API REST", "x": 320, "y": 180 } ],
  "edges": [ { "id": "e1", "from": "n1", "to": "n2", "kind": "query",
               "label": "", "dashed": false, "bidir": false } ],
  "checks": { "3:1": true }, "notes": "…", "elapsed_seconds": 742,
  "seq_mode": "flow", "estimate": { "mode": "month", "writes_per_month": 500000000, … } }

200 OK
{ "id": 12, "updated_at": "2026-08-22T18:04:11Z" }
```

### 9. Exportar o diagrama em SVG

1. O usuário exporta; o arquivo é gerado **no cliente**, a partir do mesmo estado que a tela desenha.
2. O SVG preserva selos, padrões de traço, números de sequência e a **legenda num bloco abaixo do diagrama**.
3. Os chips das setas são desenhados **depois dos blocos**, para bater com a ordem do DOM — senão um bloco vizinho cobre o rótulo no arquivo exportado.
4. O número de sequência vai **dentro do chip**: o retângulo alarga 20 px e o círculo fica à esquerda; sem tipo e sem rótulo, sobra só o círculo.

### 10. Encerrar o treino e conferir o gabarito

1. O tempo acaba (ou o usuário para).
2. Só então o usuário abre o bloco **"Tópicos que este problema cobra"** e compara com o que desenhou e marcou.
3. Nada é pontuado, nada é enviado para avaliação. A conferência é do próprio usuário.

### 11. Gerenciar sessões

1. O usuário lista suas sessões — data, problema, duração, tempo usado.
2. Abrir uma sessão a torna a corrente e restaura diagrama, checklist, notas, estimativas, tempo e modo de numeração.
3. O usuário pode criar uma nova sessão a qualquer momento e excluir sessões antigas.

## Open Questions

- **Hospedagem do projeto público:** onde a aplicação vai rodar em produção (VPS própria, PaaS) e sob qual domínio — indefinido.
- **Importação do legado:** as sessões do artifact antigo (`data/state.json` / `localStorage['sd-prancheta-v1']`) devem ser importáveis para a conta do usuário, ou começa-se do zero no app Laravel?
- **Nível do PHPStan/Larastan:** partir de qual nível e com qual meta de subida.
- **Redis:** entra como cache/fila em algum momento, ou o v1 fecha sem ele?
