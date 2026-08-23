---
paths:
  - 'resources/css/**'
---

# Css

## Tokens da prancheta vs. paleta shadcn: o --accent é da prancheta
`resources/css/prancheta.css` porta a paleta do protótipo com os nomes originais (`--paper`, `--panel`, `--ink`, `--accent`, `--c-*`, `--shadow-1/2`, `--r`). O starter kit também tinha `--accent`/`--accent-foreground` no `:root` do `app.css`: essas duas foram renomeadas para `--ui-accent`/`--ui-accent-foreground` (só o mapeamento `--color-accent` no `@theme` mudou, as classes `bg-accent`/`text-accent-foreground` dos componentes shadcn continuam valendo). Não reintroduza `--accent` no `app.css`.

Os tokens crus não viram utilitário direto: o `@theme inline` do `prancheta.css` os expõe sob o prefixo `sd-` (`bg-sd-panel`, `text-sd-ink-2`, `border-sd-line`, `rounded-sd`, `shadow-sd-1`). Tema claro em `:root`, escuro em `@media (prefers-color-scheme: dark) :root:not([data-theme='light'])` **e** em `:root[data-theme='dark']` — os dois blocos precisam continuar idênticos.

`tests/Feature/Frontend/ThemeTokensTest.php` compara token a token com `.spec/init/design/pranchetasystemdesign.html`. O Prettier normaliza o CSS (hex minúsculo, aspas simples, `0.07` no lugar de `.07`), por isso o helper `cssTokens()` do `tests/Pest.php` recebe um **regex** de seletor e normaliza os valores — não troque por comparação literal.

## Regra de marca mora em brand.css, nunca em prancheta.css
A seleção da variante do lockup (`resources/css/brand.css`, importado por `app.css`) cobre os três estados de tema: `:root[data-theme='dark']`, `:root[data-theme='light']` e `@media (prefers-color-scheme: dark) { :root:not([data-theme='light']) … }`. A media query vem ANTES dos seletores de atributo — as três regras têm a mesma especificidade, então o tema escolhido a dedo só ganha por ordem de fonte.

Não escreva regra nova em `resources/css/prancheta.css`: `cssTokens()` (`tests/Pest.php`) casa o PRIMEIRO bloco cujo seletor bate e `ThemeTokensTest` compara esse bloco token a token com o protótipo congelado — um seletor novo lá desloca o match e derruba a suíte de tema.
