## O que muda

<!-- Uma frase sobre o comportamento que muda, não sobre os arquivos tocados. -->

## Por quê

<!-- Issue relacionada (`Closes #123`) ou o problema que isso resolve. -->

## Como testar

<!-- Passos para ver a mudança de pé, ou o comando de teste que cobre ela. -->

## Checklist

- [ ] `composer ci:check` passa localmente (audit de dependencias, pint, phpstan, eslint, prettier, vue-tsc, vitest, pest)
- [ ] Mudança coberta por teste — Pest para backend, Vitest para o motor do canvas
- [ ] Mexi em CSS/JS ou classes novas do Tailwind? Rodei `npm run build`
- [ ] Mexi em migration? A suíte roda em SQLite em memória e continua verde
- [ ] Sem dependência nova sem combinar antes na issue
