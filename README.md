# Prancheta de System Design

Ferramenta de **treino guiado para entrevistas de system design**. Não é um editor
de diagramas de propósito geral: é uma bancada de treino onde você recebe um
**enunciado**, dispara um **cronômetro dividido em fases com peso**, desenha a
arquitetura com um **vocabulário fechado de componentes**, confere um **checklist
por fase** e faz as **estimativas de capacidade** — tudo numa tela só.

A ferramenta **não avalia o treino**: não há nota, revisor automático nem IA
julgando o desenho. O valor está no ritual — enunciado, relógio, roteiro, desenho.

Descrição completa do produto em [`.spec/init/project-description.md`](.spec/init/project-description.md).

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.4 · Laravel 13 |
| Frontend | Inertia 3 + Vue 3 + TypeScript · Vite |
| Estilo | Tailwind CSS 4 |
| Canvas | Módulo TypeScript próprio (DOM + SVG, sem biblioteca de diagramas) |
| Banco (dev/prod) | MySQL 8 |
| Banco (testes) | SQLite em memória |
| Testes | Pest (backend) · Vitest (motor do canvas) |
| Qualidade | Laravel Pint · Larastan/PHPStan · ESLint + Prettier |

## Como rodar

Há duas trilhas. Escolha **uma**: o Docker não é exigido pelo fluxo do dia a dia —
a aplicação sobe sem container.

### Trilha 1 — Sail (container)

Para quem clona o projeto e não quer instalar PHP, MySQL e Node na máquina.
Requer apenas Docker.

```bash
git clone <repo> maxdraw && cd maxdraw
cp .env.example .env

# instala as dependências PHP usando um container descartável,
# sem precisar de PHP no host
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

A aplicação fica em <http://localhost> (porta via `APP_PORT`) e o Vite em `5173`.
O `docker-compose.yml` versionado sobe **PHP 8.4** e **MySQL 8.0** com volume
persistente (`sail-mysql`), então o banco sobrevive a `sail down`.

Dentro dessa trilha, **todo** comando vai prefixado com `./vendor/bin/sail` —
não misture host e container no mesmo fluxo.

### Trilha 2 — nativa (sem container)

Para quem já tem PHP 8.4, MySQL 8 e Node na máquina — é como o projeto roda na VPS.

```bash
git clone <repo> maxdraw && cd maxdraw
cp .env.example .env

composer install
php artisan key:generate

# crie o banco e ajuste DB_DATABASE / DB_USERNAME / DB_PASSWORD no .env
php artisan migrate --seed

npm install
npm run dev          # em um terminal
php artisan serve    # em outro
```

A aplicação fica em <http://localhost:8000>.

## O catálogo é seedado, não editável pela interface

Problemas, componentes, tipos de ligação, fases, itens de checklist, durações e
modos de estimativa moram no banco, mas são **catálogo versionado**: entram por
seeder e a aplicação só lê. Não existe CRUD, tela de administração nem rota que
escreva nessas tabelas — um guard de runtime derruba qualquer escrita vinda de
uma requisição HTTP.

**Mudar o catálogo é editar o seeder e rodar o seed de novo**, nunca mexer na
interface nem no banco à mão:

```bash
# edite database/seeders/data/ e o seeder correspondente, depois:
php artisan db:seed --class=CatalogSeeder
```

Os seeders são idempotentes (`upsert` por slug), então rodar de novo atualiza o
conteúdo sem duplicar linha e sem tocar nas sessões de treino de ninguém.

## Referência de design

[`.spec/init/design/pranchetasystemdesign.html`](.spec/init/design/pranchetasystemdesign.html)
é o **protótipo congelado** — a referência visual **e** comportamental do produto.
Tokens de cor, tipografia, espaçamento, geometria das setas, textos da legenda e
regras de interação saem de lá, e a suíte compara o CSS da aplicação com o CSS do
protótipo. Ao mudar aparência ou comportamento, o protótipo é a fonte da verdade;
divergir dele é decisão consciente, e vai registrada na tabela abaixo.

## Paridade com o protótipo

Os onze Core Workflows do `project-description` foram percorridos na aplicação e
conferem com o protótipo. Onde cada um vive:

| # | Workflow | Onde está |
|---|---|---|
| 1 | Registrar-se, entrar e retomar o treino | Fortify · `BoardController` + `CurrentSessionResolver` + `CatalogService` · `pages/Board.vue` |
| 2 | Escolher o problema e iniciar a sessão | `ProblemPicker.vue` · `ProblemBrief.vue` · `prancheta/problems.ts` · `POST /api/sessions` |
| 3 | Desenhar o diagrama | `canvas/` (`nodes`, `edges`, `geometry`, `layout`, `view`, `undo`) · `StageCanvas.vue` · `ComponentPalette.vue` |
| 4 | Tipar as ligações e ordenar a sequência | `canvas/links.ts` · `canvas/sequence.ts` · `EdgeFloatBar.vue` · `LinkKindMenu.vue` · `SequenceMenu.vue` |
| 5 | Ler a legenda automática | `canvas/legend.ts` · `prancheta/legend.ts` · `LegendPanel.vue` · `useLegend` |
| 6 | Cronometrar as fases e marcar o checklist | `prancheta/clock.ts` · `prancheta/roteiro.ts` · `DrillClock.vue` · `PhaseAccordion.vue` |
| 7 | Fazer as estimativas de capacidade | `prancheta/estimate.ts` · `EstimatePanel.vue` |
| 8 | Salvar automaticamente | `prancheta/autosave.ts` · `useAutosave` · `PUT /api/sessions/{id}` + `SessionStateWriter` |
| 9 | Exportar o diagrama em SVG | `canvas/svg.ts` · `prancheta/export.ts` |
| 10 | Encerrar o treino e conferir o gabarito | `ProblemItemList.vue` · `prancheta/problems.ts` |
| 11 | Gerenciar sessões | `SessionList.vue` · `prancheta/sessions.ts` · `TrainingSessionController` |

Nenhuma funcionalidade do protótipo ficou sem equivalente. As diferenças são as
quatro abaixo, todas deliberadas:

| O protótipo faz | A aplicação faz | Por quê |
|---|---|---|
| Catálogo em constantes no próprio HTML | Catálogo em **tabelas de banco**, populadas por seeder | O protótipo é um arquivo só; aqui problemas, componentes e checklist são dados de domínio consultáveis, com integridade referencial e versionamento pelo seeder |
| Estado em `localStorage`, uma sessão por navegador | **Sessões por usuário** no banco, escopadas ao autenticado | Sem conta não há retomar o treino em outra máquina; o isolamento por usuário é requisito (US-1.4), e `localStorage` não isola nada |
| Autosave com debounce de **250 ms** e sincronização a cada **7 s** | Debounce de **800 ms** para o espelho local e **3 s** para o servidor | O protótipo só escrevia no navegador; aqui cada gravação é uma requisição HTTP com o estado inteiro, e 250 ms viraria uma rajada de PUTs no meio do desenho |
| `checks` chaveado por `"fase:índice"` | `checks` chaveado por **`checklist_items.id`** | O índice quebra assim que o seeder reordena ou insere um item no meio da fase; o id é estável e validado contra o catálogo no `PUT` |

O `localStorage` continua em uso para o que é **preferência de navegador**, e não
dado de treino: tema e legenda recolhida.

## Testes e qualidade

```bash
php artisan test              # suíte Pest — roda em SQLite :memory:, não toca o MySQL
npm run test                  # Vitest sobre o motor do canvas e a prancheta

./vendor/bin/pint --test      # formatação PHP
./vendor/bin/phpstan analyse  # análise estática
npm run lint                  # ESLint com --fix
npm run lint:check            # ESLint sem escrever
npm run format:check          # Prettier
npm run types:check           # vue-tsc
```

`composer ci:check` encadeia a cadeia inteira — ESLint, Prettier, `vue-tsc`,
Vitest, Pint, PHPStan e Pest — e é **exatamente** o que o CI roda a cada push e
em cada pull request (`.github/workflows/tests.yml`).

O `phpunit.xml` força `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:`, então a
suíte roda isolada do banco de desenvolvimento em qualquer uma das duas trilhas e
não depende de estado prévio. O CI ainda faz `php artisan migrate:fresh --seed`
antes da cadeia, para provar que o schema e o catálogo sobem do zero.

## Fora do v1

Decisões explícitas de escopo — não são pendências esquecidas:

- **Modo apresentação** — percorrer o diagrama passo a passo para uma plateia.
- **Templates de partida** — abrir uma sessão com um esqueleto de arquitetura pronto.
- **Histórico entre sessões** — comparar dois treinos do mesmo problema lado a lado.
- **Autolayout** — arrumar os blocos sozinho; o posicionamento é sempre manual.
- **BFF** — não há camada intermediária: o Inertia fala direto com o Laravel.
- **Redis** — sem cache e sem fila; o catálogo é pequeno e a gravação é síncrona.
- **Recuperação de senha** — quem esquecer a senha perde a conta e as sessões.

## Licença

MIT — ver [`LICENSE`](LICENSE).
