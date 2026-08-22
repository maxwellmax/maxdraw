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
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

A aplicação fica em <http://localhost> (porta via `APP_PORT`) e o Vite em `5173`.
O `docker-compose.yml` versionado sobe **PHP 8.4** e **MySQL 8.0** com volume
persistente (`sail-mysql`), então o banco sobrevive a `sail down`.

### Trilha 2 — nativa (sem container)

Para quem já tem PHP 8.4, MySQL 8 e Node na máquina — é como o projeto roda na VPS.

```bash
git clone <repo> maxdraw && cd maxdraw
cp .env.example .env

composer install
php artisan key:generate

# crie o banco e ajuste DB_DATABASE / DB_USERNAME / DB_PASSWORD no .env
php artisan migrate

npm install
npm run dev          # em um terminal
php artisan serve    # em outro
```

A aplicação fica em <http://localhost:8000>.

## Testes e qualidade

```bash
php artisan test              # suíte Pest — roda em SQLite :memory:, não toca o MySQL
npm run test                  # Vitest sobre o motor do canvas

./vendor/bin/pint --test      # formatação PHP
./vendor/bin/phpstan analyse  # análise estática
npm run lint:check            # ESLint
npm run format:check          # Prettier
```

`composer test` encadeia Pint, PHPStan e a suíte Pest de uma vez.

O `phpunit.xml` força `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:`, então a
suíte roda isolada do banco de desenvolvimento em qualquer uma das duas trilhas.

## Licença

MIT — ver [`LICENSE`](LICENSE).
