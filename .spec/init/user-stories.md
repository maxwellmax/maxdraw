# Prancheta de System Design — User Stories

<!-- inputs: project-description.md@sha256:bed6595e6025 -->

## Overview

A Prancheta de System Design é uma bancada de treino individual para entrevistas de arquitetura: o usuário recebe um enunciado, dispara um cronômetro dividido em 5 fases com peso, desenha a arquitetura com um vocabulário fechado de 28 componentes e 9 tipos de ligação, marca um checklist de 25 itens e faz as estimativas de capacidade. A ferramenta **não avalia** o treino — o único gabarito é a lista estática de tópicos do problema, que só se abre depois de terminar.

O v1 é paridade funcional com o protótipo de referência (`docs/design/pranchetasystemdesign.html`) mais o que o backend Laravel acrescenta: **multiusuário com registro**, sessões persistidas por usuário e catálogo em tabelas populadas por seeder versionado. Ficam **fora do v1**: recuperação de senha por e-mail, verificação de e-mail obrigatória, modo apresentação, templates de partida por problema, histórico de desempenho entre sessões, autolayout e BFF como bloco próprio.

**User Types:**
- **Candidato em treino** - usuário autenticado que treina system design; dono exclusivo das próprias sessões, diagramas, notas e estimativas.
- **Visitante** - pessoa não autenticada; só pode se registrar ou entrar, e não enxerga nenhum dado de treino.

---

## 1. Conta e acesso

### US-1.1: Registrar uma conta
**As a** Visitante
**I want to** criar uma conta com nome, e-mail e senha
**So that** meus treinos fiquem guardados e me sigam entre máquinas

**Acceptance Criteria:**
- [ ] O formulário pede nome, e-mail e senha com confirmação
- [ ] E-mail precisa ser válido e único; e-mail já cadastrado devolve erro de validação no campo, sem revelar senha nem criar registro duplicado
- [ ] Senha mínima de 8 caracteres, confirmada em segundo campo
- [ ] A senha é gravada com hash (nunca em texto plano)
- [ ] Registro bem-sucedido autentica a pessoa e leva direto à prancheta
- [ ] Não há verificação de e-mail obrigatória no v1 — a conta já nasce utilizável

**Expected Result:** Uma conta nova existe, está autenticada e tem uma sessão de treino vazia pronta para usar.

---

### US-1.2: Entrar e sair
**As a** Candidato em treino
**I want to** entrar com e-mail e senha e sair quando quiser
**So that** eu retome meus treinos e ninguém use minha conta no mesmo navegador

**Acceptance Criteria:**
- [ ] Credenciais corretas autenticam e levam à sessão corrente do usuário
- [ ] Credenciais erradas devolvem uma mensagem genérica ("credenciais inválidas"), sem dizer se o e-mail existe
- [ ] Tentativas de login são limitadas por throttle (padrão do Laravel) para conter força bruta
- [ ] "Lembrar de mim" mantém a sessão entre visitas quando marcado
- [ ] Sair encerra a sessão, invalida o token e devolve ao login
- [ ] Rota de treino acessada sem autenticação redireciona para o login

**Expected Result:** O acesso é controlado por sessão do Laravel, e sair remove o acesso àquele navegador.

---

### US-1.3: Gerenciar meu perfil
**As a** Candidato em treino
**I want to** alterar meu nome, e-mail e senha, ou excluir minha conta
**So that** eu mantenha meus dados corretos e possa sair do serviço de vez

**Acceptance Criteria:**
- [ ] A tela de conta permite editar nome e e-mail, com a mesma validação do registro
- [ ] Trocar a senha exige a senha atual e a nova confirmada
- [ ] Excluir a conta exige confirmação com a senha atual
- [ ] Excluir a conta apaga todas as sessões de treino daquele usuário em cascata
- [ ] Após excluir, o usuário é deslogado e a conta não permite mais login

**Expected Result:** O usuário controla os próprios dados e consegue encerrar a conta sem deixar sessões órfãs.

---

### US-1.4: Ter meus treinos isolados dos outros usuários
**As a** Candidato em treino
**I want to** que minhas sessões sejam visíveis e editáveis só por mim
**So that** o projeto possa ser público sem que ninguém veja ou altere meu treino

**Acceptance Criteria:**
- [ ] Toda consulta de sessão é escopada ao usuário autenticado
- [ ] Requisição a uma sessão de outro usuário devolve 403 ou 404, nunca o conteúdo
- [ ] Requisição sem autenticação a qualquer rota de sessão devolve 401/redirect, nunca dado
- [ ] Existe teste de feature cobrindo leitura, escrita e exclusão cruzadas entre dois usuários

**Expected Result:** Nenhum caminho da aplicação expõe dados de treino de um usuário a outro.

---

## 2. Sessão de treino e problema

### US-2.1: Escolher um problema e iniciar o treino
**As a** Candidato em treino
**I want to** escolher um dos problemas do banco e ver seu enunciado
**So that** eu treine sobre um alvo concreto em vez de inventar o exercício

**Acceptance Criteria:**
- [ ] O seletor lista os 14 problemas com nome, etiqueta de tema e nível
- [ ] Escolher um problema mostra contexto em prosa, requisitos funcionais e escala alvo
- [ ] O bloco "Tópicos que este problema cobra" aparece **colapsado**, rotulado "abra só depois de terminar"
- [ ] O problema escolhido fica gravado na sessão corrente
- [ ] Sessão sem problema e sem blocos abre o seletor automaticamente ao carregar

**Expected Result:** A sessão corrente tem um problema associado e o enunciado visível durante todo o treino.

---

### US-2.2: Retomar a sessão onde parei
**As a** Candidato em treino
**I want to** que a aplicação abra na minha última sessão, com tudo como deixei
**So that** eu continue o treino sem remontar nada

**Acceptance Criteria:**
- [ ] Ao entrar, carrega-se a sessão corrente do usuário com diagrama, checklist, notas, estimativas, tempo decorrido e modo de numeração
- [ ] O catálogo (problemas, componentes, tipos de ligação, fases, itens) vem na mesma resposta inicial
- [ ] Usuário sem nenhuma sessão recebe uma sessão vazia criada na hora
- [ ] O cronômetro retoma pausado, no tempo decorrido gravado — nunca contando sozinho ao abrir

**Expected Result:** Abrir a aplicação devolve exatamente o estado do último salvamento.

---

### US-2.3: Escolher a duração do treino
**As a** Candidato em treino
**I want to** escolher entre 30, 45 e 60 minutos
**So that** o treino caiba no tempo que eu tenho e imite a entrevista real

**Acceptance Criteria:**
- [ ] Só 30, 45 e 60 são aceitos; qualquer outro valor é rejeitado na API
- [ ] A duração padrão de uma sessão nova é 45 minutos
- [ ] Mudar a duração recalcula o tamanho das fases pelos pesos (11% / 11% / 18% / 27% / 33%)
- [ ] Em 45 minutos as fases resultam em aproximadamente 5, 5, 8, 12 e 15 minutos
- [ ] Mudar a duração com o cronômetro rodando preserva o tempo já decorrido

**Expected Result:** O cronômetro e as fatias de fase refletem a duração escolhida, gravada na sessão.

---

## 3. Prancheta — blocos e ligações

### US-3.1: Colocar um bloco pela paleta
**As a** Candidato em treino
**I want to** clicar num componente da paleta e ver o bloco já pronto na prancheta
**So that** eu gaste o tempo pensando em arquitetura, não desenhando retângulos

**Acceptance Criteria:**
- [ ] A paleta mostra os 28 componentes agrupados nas 6 categorias, cada um com seu ícone e a cor da categoria
- [ ] Um clique posiciona o bloco na área visível, sem sobrepor blocos existentes
- [ ] O rótulo inicial é o **nome curto** do componente (ex.: `DLQ` para `DLQ — fila de falhas`)
- [ ] O campo de nome abre já focado para digitação imediata
- [ ] A cor do bloco vem da categoria e não pode ser alterada pelo usuário
- [ ] Uma sessão aceita no máximo **200 nós**; ao atingir o limite, a paleta informa e não adiciona

**Expected Result:** O bloco existe no diagrama, nomeado, na cor da sua categoria, e a sessão fica suja para salvar.

---

### US-3.2: Renomear e reposicionar blocos
**As a** Candidato em treino
**I want to** mover e renomear os blocos
**So that** o desenho fique legível e nomeie os serviços do meu jeito

**Acceptance Criteria:**
- [ ] Arrastar o bloco o move, com **snap de 4 px** na grade
- [ ] Duplo clique abre a edição do rótulo; Enter confirma, Esc cancela
- [ ] Rótulo aceita no máximo **60 caracteres**; o excedente é bloqueado na digitação
- [ ] Rótulo vazio volta ao nome curto do componente
- [ ] Mover e renomear entram na pilha de desfazer

**Expected Result:** O diagrama reflete a posição e os nomes escolhidos, alinhados à grade.

---

### US-3.3: Ligar dois blocos
**As a** Candidato em treino
**I want to** arrastar de um bloco até outro para criar uma seta
**So that** eu mostre o caminho da requisição e do dado

**Acceptance Criteria:**
- [ ] Cada bloco expõe 4 pontos de conexão; arrastar de um deles até outro bloco cria a ligação
- [ ] Soltar fora de um bloco cancela, sem criar aresta
- [ ] Não é possível ligar um bloco a ele mesmo
- [ ] A curva vai de centro a centro, recortada na borda dos retângulos, em bezier cúbico com controle no eixo dominante
- [ ] Mover qualquer um dos blocos redesenha a seta imediatamente
- [ ] Uma sessão aceita no máximo **400 arestas**

**Expected Result:** Existe uma seta direcionada entre os dois blocos, que acompanha o movimento deles.

---

### US-3.4: Navegar pela prancheta
**As a** Candidato em treino
**I want to** deslocar, aproximar e enquadrar o desenho
**So that** eu trabalhe em diagramas maiores que a tela

**Acceptance Criteria:**
- [ ] Arrastar no vazio faz pan
- [ ] A roda do mouse faz zoom, ancorado no cursor
- [ ] "Enquadrar tudo" ajusta escala e posição para todo o diagrama caber, **reservando a largura da legenda**
- [ ] `pointerdown` e `wheel` dentro do painel da legenda não fazem pan nem zoom
- [ ] Pan e zoom não entram na pilha de desfazer

**Expected Result:** O usuário alcança qualquer parte do diagrama sem perder o desenho de vista.

---

### US-3.5: Desfazer, refazer e apagar
**As a** Candidato em treino
**I want to** desfazer um passo errado e apagar o que não serve
**So that** um erro não me custe minutos do treino

**Acceptance Criteria:**
- [ ] `Del` apaga o item selecionado; apagar um bloco apaga também as arestas ligadas a ele
- [ ] `Ctrl+Z` desfaz e `Ctrl+Shift+Z` refaz
- [ ] A pilha guarda até **60 estados**, apenas de nós e arestas
- [ ] Uma nova ação depois de desfazer limpa a pilha de refazer
- [ ] Trocar o modo de numeração **não** entra na pilha de desfazer

**Expected Result:** Qualquer alteração estrutural do diagrama é reversível dentro dos últimos 60 passos.

---

## 4. Tipos de ligação e sequência

### US-4.1: Tipar uma ligação
**As a** Candidato em treino
**I want to** dizer qual é o protocolo ou a natureza de cada seta
**So that** o desenho diga se aquilo é síncrono, assíncrono ou caminho de falha

**Acceptance Criteria:**
- [ ] Selecionar uma seta abre a barra flutuante com os **9 tipos + "Sem tipo"**
- [ ] O menu mostra o padrão de traço real em cada amostra (contínuo, tracejado, pontilhado)
- [ ] O tipo aplica o traço: contínuo por padrão, `5 4.5` para async/replicação/lote, `2 4.5` para Falha/Retry
- [ ] A seta ganha o selo do tipo (`HTTP`, `gRPC`, `WS`, `async`, `query`, `cache`, `replica`, `batch`, `retry`) **colorido pela categoria da origem**
- [ ] Escolher WebSocket já deixa a seta em mão dupla
- [ ] Nenhum tipo introduz cor nova no diagrama

**Expected Result:** A seta carrega tipo, traço e selo coerentes, e a cor continua sendo função da categoria de origem.

---

### US-4.2: Rotular e ajustar a seta manualmente
**As a** Candidato em treino
**I want to** escrever um rótulo livre e forçar tracejado, mão dupla ou inversão
**So that** eu descreva a chamada concreta e corrija o sentido sem refazer a seta

**Acceptance Criteria:**
- [ ] O rótulo livre convive com o selo e aparece ao lado: `async · GET /feed`
- [ ] Rótulo aceita no máximo **60 caracteres**
- [ ] Tracejado, mão dupla e inversão de sentido podem ser ligados e desligados manualmente, independentemente do tipo
- [ ] Sem tipo e sem rótulo, o chip da seta perde borda e fundo
- [ ] Todas essas alterações entram na pilha de desfazer

**Expected Result:** A seta comunica protocolo e chamada específica na mesma etiqueta.

---

### US-4.3: Numerar a sequência automaticamente
**As a** Candidato em treino
**I want to** alternar entre numerar as saídas de cada bloco, numerar o fluxo inteiro ou desligar
**So that** eu narre a ordem dos passos sem escrever número nenhum à mão

**Acceptance Criteria:**
- [ ] O botão `1→2` alterna `out` → `flow` → `off`
- [ ] Modo `out` (padrão): quando um bloco tem 2+ saídas, cada uma recebe ①②③
- [ ] Modo `flow`: todas as setas recebem 1..N por busca em profundidade a partir dos clientes sem entrada, seguindo a ordem de saída de cada bloco
- [ ] O modo `flow` cobre nós órfãos ao final e não entra em laço infinito em diagramas com ciclo
- [ ] O número mora **dentro do chip** da seta e herda a cor da categoria da origem
- [ ] O modo escolhido é gravado na sessão (`seq_mode`) e restaurado ao reabrir
- [ ] Modo inválido ou ausente vindo do servidor é normalizado para `out`

**Expected Result:** A numeração é sempre derivada do estado do diagrama, nunca digitada, e sobrevive ao reload.

---

### US-4.4: Reordenar a saída de um bloco
**As a** Candidato em treino
**I want to** antecipar ou adiar uma das setas que saem de um bloco
**So that** a numeração conte a história na ordem em que as coisas acontecem

**Acceptance Criteria:**
- [ ] Com uma seta selecionada, a barra flutuante mostra `‹ n/total ›` referente às saídas daquele bloco
- [ ] As setas movem a aresta uma posição para frente ou para trás na ordem de saída
- [ ] A ordem é a posição da aresta na lista da sessão — não existe campo de ordem por aresta
- [ ] A mesma ordem governa a travessia do modo `flow`
- [ ] Nas pontas (primeira/última saída), o controle correspondente fica desabilitado

**Expected Result:** O usuário controla a ordem narrada sem editar número algum.

---

## 5. Legenda automática

### US-5.1: Ver a legenda montada a partir do desenho
**As a** Candidato em treino
**I want to** uma legenda que se monte sozinha com o que está no diagrama
**So that** eu aprenda o vocabulário enquanto desenho e o desenho se explique sozinho

**Acceptance Criteria:**
- [ ] *Blocos*: uma linha por categoria **presente** — quadradinho na cor da categoria, nome e contagem
- [ ] *Ligações*: uma linha por tipo **usado** — amostra do traço real em tom neutro, selo, nome e a glosa de uma linha (ex.: `retry — estourou as tentativas e foi para a DLQ; ninguém consumiu`)
- [ ] Setas sem tipo produzem a linha `sem tipo — clique na seta e escolha o protocolo`
- [ ] *Sequência*: aparece só quando há numeração no desenho, com o texto do modo ativo
- [ ] A legenda some por completo quando não há nada desenhado
- [ ] As amostras de traço são neutras: só os quadradinhos de categoria têm cor

**Expected Result:** A legenda reflete exatamente o conteúdo do diagrama, sem nenhuma configuração do usuário.

---

### US-5.2: Recolher a legenda
**As a** Candidato em treino
**I want to** recolher a legenda quando ela atrapalhar
**So that** eu use a tela inteira para o desenho

**Acceptance Criteria:**
- [ ] Um controle recolhe e expande o painel
- [ ] O estado recolhido persiste no navegador entre recarregamentos
- [ ] O estado da legenda é preferência do navegador, não da sessão — não vai para o servidor
- [ ] Recolhida, a legenda deixa de reservar largura no enquadramento

**Expected Result:** A preferência de exibição da legenda é lembrada por navegador.

---

## 6. Roteiro: cronômetro, checklist e notas

### US-6.1: Cronometrar o treino por fases
**As a** Candidato em treino
**I want to** um cronômetro que divida o tempo nas 5 fases do roteiro
**So that** eu não gaste metade da entrevista em requisitos e chegue sem tempo em trade-offs

**Acceptance Criteria:**
- [ ] Play inicia, pausa interrompe, e o tempo decorrido é preservado na sessão
- [ ] As 5 fases aparecem com nome e peso: Requisitos & escopo 11%, Estimativas 11%, API & modelo de dados 18%, Desenho de alto nível 27%, Escala & trade-offs 33%
- [ ] A fase corrente é destacada e **abre sozinha** quando o tempo cruza a fronteira dela
- [ ] O painel mostra quanto resta da fase corrente e do treino
- [ ] Ao zerar o tempo, o cronômetro para e sinaliza o fim, sem apagar nada
- [ ] O tempo decorrido é persistido periodicamente, sobrevivendo ao fechamento do navegador

**Expected Result:** O usuário sempre sabe em que fase está e quanto tempo ainda tem nela.

---

### US-6.2: Marcar o checklist da fase
**As a** Candidato em treino
**I want to** marcar os itens do roteiro conforme eu cubro cada ponto
**So that** eu perceba na hora o que ainda não falei

**Acceptance Criteria:**
- [ ] São 25 itens no total, distribuídos 4 / 5 / 5 / 5 / 6 nas cinco fases
- [ ] Cada item alterna marcado/desmarcado com um clique
- [ ] Cada fase mostra o progresso (marcados / total)
- [ ] O estado é por sessão e é restaurado ao reabrir
- [ ] Itens de fases já passadas continuam marcáveis a qualquer momento

**Expected Result:** O checklist da sessão registra o que foi coberto, item a item.

---

### US-6.3: Anotar durante o treino
**As a** Candidato em treino
**I want to** um bloco de notas dentro da sessão
**So that** eu registre trade-offs e números sem sair da ferramenta

**Acceptance Criteria:**
- [ ] O campo de notas é livre, multilinha, com limite de **5.000 caracteres**
- [ ] As notas pertencem à sessão e são restauradas ao reabrir
- [ ] Digitar marca a sessão como suja e dispara o autosave

**Expected Result:** Cada sessão guarda suas próprias notas junto com o diagrama.

---

## 7. Estimativas de capacidade

### US-7.1: Estimar por usuários
**As a** Candidato em treino
**I want to** calcular a carga a partir de DAU e ações de escrita por usuário/dia
**So that** eu chegue rápido a QPS, armazenamento e banda sem fazer conta de cabeça

**Acceptance Criteria:**
- [ ] Campos do modo: usuários ativos por dia e ações de escrita por usuário/dia
- [ ] Campos comuns: leituras por escrita, tamanho médio do registro (KB), fator de pico, retenção (anos)
- [ ] Saída: escritas por mês/dia/segundo, leituras por dia/segundo, total por segundo no pico, dados novos por dia e por ano, armazenamento na retenção, banda de saída no pico
- [ ] Fórmulas: escritas/dia = `dau × ações`; leituras/dia = escritas × razão; qps = valor/86400; pico = `(wqps + rqps) × fator`; banda de saída = `rqps × fator × tamanho`
- [ ] Recalcula a cada tecla, sem perder o foco nem a posição do cursor no campo
- [ ] Valores negativos são normalizados para zero; campo vazio não quebra a saída
- [ ] A linha "Escritas por dia" é a destacada neste modo

**Expected Result:** As dez linhas de saída refletem os parâmetros informados, formatadas em pt-BR (mil/mi/bi, B→PB).

---

### US-7.2: Estimar por volume mensal
**As a** Candidato em treino
**I want to** informar direto "X escritas por mês"
**So that** eu use o número no formato que o entrevistador costuma dar

**Acceptance Criteria:**
- [ ] O seletor alterna entre "Por usuários" e "Por volume mensal", e o modo é gravado na sessão
- [ ] No modo mensal, o único campo próprio é escritas por mês; os campos comuns continuam iguais
- [ ] **Mês = 30 dias**: escritas/dia = `perMonth / 30`
- [ ] A saída tem exatamente as mesmas dez linhas do outro modo
- [ ] A linha "Escritas por mês" passa a ser a destacada neste modo
- [ ] Trocar de modo preserva os valores dos campos comuns

**Expected Result:** Os dois modos produzem a mesma tabela de saída, mudando só a entrada e a linha destacada.

---

### US-7.3: Ler a conclusão da estimativa
**As a** Candidato em treino
**I want to** uma frase que diga o que aquele pico obriga
**So that** eu treine a conclusão em voz alta, que é o que a entrevista cobra

**Acceptance Criteria:**
- [ ] Pico ≥ 50.000 qps: "esse pico exige cache e particionamento, diga isso em voz alta"
- [ ] Pico ≥ 5.000 e < 50.000 qps: "dá para servir com réplicas de leitura e cache"
- [ ] Pico < 5.000 qps: "uma instância bem dimensionada aguenta, não invente complexidade"
- [ ] A frase acompanha o aviso "Mês = 30 dias" e a orientação de arredondar
- [ ] A frase muda em tempo real ao alterar qualquer parâmetro

**Expected Result:** A calculadora entrega ordem de grandeza **e** a conclusão que se tira dela.

---

## 8. Persistência

### US-8.1: Salvar automaticamente o treino
**As a** Candidato em treino
**I want to** que tudo seja salvo sozinho enquanto trabalho
**So that** eu nunca perca um treino por esquecer de salvar

**Acceptance Criteria:**
- [ ] Qualquer alteração (diagrama, checklist, notas, estimativas, tempo, modo de numeração) marca a sessão como suja
- [ ] O estado é gravado localmente após **800 ms** de inércia e enviado ao servidor após **3 s** de inércia
- [ ] O servidor grava numa transação: `nodes`, `edges`, `checks` e `estimate` como colunas JSON; `notes`, `elapsed_seconds`, `duration_minutes` e `seq_mode` como colunas próprias
- [ ] O payload é validado: tipos de componente e de ligação precisam existir no catálogo; arestas precisam referenciar nós presentes no mesmo payload
- [ ] Payload que viola os limites (200 nós, 400 arestas, 60 caracteres de rótulo, 5.000 de notas) é rejeitado com 422 e mensagem por campo
- [ ] Fechar a aba dispara uma última gravação local

**Expected Result:** O estado no servidor acompanha o que está na tela, sem ação explícita do usuário.

---

### US-8.2: Ver o estado do salvamento
**As a** Candidato em treino
**I want to** um indicador dizendo se está salvo
**So that** eu saiba se posso fechar a aba sem perder nada

**Acceptance Criteria:**
- [ ] Estados visíveis: "não salvo", "salvando…" e "salvo"
- [ ] O indicador vai para "não salvo" no instante da alteração
- [ ] Existe um botão de salvar agora, que força o envio sem esperar a inércia
- [ ] Após um salvamento bem-sucedido, o indicador mostra "salvo"

**Expected Result:** O usuário sempre sabe se o trabalho já está no servidor.

---

### US-8.3: Não perder trabalho quando a rede falha
**As a** Candidato em treino
**I want to** que uma falha de rede não apague meu desenho
**So that** eu continue treinando mesmo com conexão instável

**Acceptance Criteria:**
- [ ] Falha no envio mantém o indicador em "não salvo" e agenda nova tentativa
- [ ] O trabalho continua utilizável durante a falha — nada é bloqueado nem revertido
- [ ] O estado local persiste no navegador e é reaplicado se a página recarregar antes do envio
- [ ] Quando a versão do servidor for mais nova que a local, o usuário é avisado em vez de sobrescrever silenciosamente
- [ ] Erro de rate limit espera e tenta de novo, sem perder alterações

**Expected Result:** Nenhuma requisição perdida custa desenho ao usuário.

---

## 9. Export

### US-9.1: Exportar o diagrama em SVG
**As a** Candidato em treino
**I want to** baixar o diagrama em SVG
**So that** eu revise depois ou anexe o desenho a um estudo

**Acceptance Criteria:**
- [ ] O arquivo é gerado no cliente, a partir do mesmo estado que a tela desenha
- [ ] O SVG preserva selos, padrões de traço e números de sequência
- [ ] A legenda sai num bloco **abaixo** do diagrama, com o mesmo conteúdo da tela
- [ ] Os chips das setas são desenhados **depois** dos blocos, para que bloco vizinho nunca cubra rótulo
- [ ] Com número e chip, o retângulo alarga 20 px e o círculo fica à esquerda; sem tipo e sem rótulo, sai só o círculo
- [ ] Diagrama vazio não gera arquivo — o botão avisa que não há nada a exportar

**Expected Result:** Um arquivo SVG fiel ao que está na tela, legenda inclusa.

---

## 10. Fechamento e gabarito

### US-10.1: Conferir os tópicos depois de terminar
**As a** Candidato em treino
**I want to** abrir a lista de tópicos que o problema cobra só no fim
**So that** eu me avalie sozinho sem ter espiado a resposta durante o treino

**Acceptance Criteria:**
- [ ] O bloco de tópicos nasce colapsado em toda sessão, com o rótulo "abra só depois de terminar"
- [ ] O conteúdo é a lista estática de tópicos do problema, sem nota, sem pontuação, sem avaliação automática
- [ ] Abrir o bloco é ação explícita do usuário e não altera nem o cronômetro nem o checklist
- [ ] A ferramenta em nenhum momento julga o diagrama desenhado

**Expected Result:** O usuário compara sozinho o que fez com os tópicos esperados.

---

## 11. Gerenciar sessões

### US-11.1: Listar e abrir minhas sessões
**As a** Candidato em treino
**I want to** ver minhas sessões anteriores e reabrir uma delas
**So that** eu retome um treino ou revise o que desenhei antes

**Acceptance Criteria:**
- [ ] A lista mostra data, problema, duração escolhida e tempo usado
- [ ] A lista é ordenada da mais recente para a mais antiga
- [ ] Abrir uma sessão a torna a corrente e restaura diagrama, checklist, notas, estimativas, tempo e modo de numeração
- [ ] A lista contém apenas sessões do usuário autenticado

**Expected Result:** Qualquer treino anterior é recuperável na íntegra.

---

### US-11.2: Começar uma sessão nova
**As a** Candidato em treino
**I want to** iniciar um treino novo a qualquer momento
**So that** eu ataque outro problema sem apagar o anterior

**Acceptance Criteria:**
- [ ] Criar sessão nova salva a corrente antes de trocar
- [ ] A sessão nova nasce vazia: sem blocos, sem marcações, sem notas, tempo zerado, duração 45, modo de numeração `out`
- [ ] As estimativas nascem com os valores padrão do modo por usuários
- [ ] A sessão nova vira a corrente e o seletor de problemas abre automaticamente

**Expected Result:** O usuário acumula sessões, uma por treino, sem sobrescrever nada.

---

### US-11.3: Excluir uma sessão
**As a** Candidato em treino
**I want to** apagar sessões que não me servem mais
**So that** minha lista fique limpa

**Acceptance Criteria:**
- [ ] Excluir pede confirmação explícita
- [ ] A exclusão remove a sessão e todo o conteúdo dela
- [ ] Excluir a sessão corrente promove a mais recente restante a corrente
- [ ] Excluir a última sessão existente cria uma sessão vazia no lugar
- [ ] Não é possível excluir sessão de outro usuário

**Expected Result:** A lista contém apenas os treinos que o usuário quer manter, e nunca fica sem sessão corrente.

---

## Open Questions

- **Recuperação de senha:** ficou fora do v1 por decisão explícita. Sem ela, quem esquecer a senha perde o acesso à conta e às sessões — definir se entra logo depois do v1 ou se o projeto público sai assim.
- **Importação do legado:** as sessões do artifact antigo (`data/state.json` / `localStorage['sd-prancheta-v1']`) viram histórias de importação ou o app Laravel começa do zero?
- **Hospedagem pública:** onde a aplicação roda em produção e sob qual domínio.
- **Redis:** entra como cache/fila em algum momento, ou o v1 fecha sem ele?

---

## Appendix: User Story Status

| ID | Story | Priority | Status |
|----|-------|----------|--------|
| US-1.1 | Registrar uma conta | High | Pending |
| US-1.2 | Entrar e sair | High | Pending |
| US-1.4 | Ter meus treinos isolados dos outros usuários | High | Pending |
| US-2.1 | Escolher um problema e iniciar o treino | High | Pending |
| US-2.2 | Retomar a sessão onde parei | High | Pending |
| US-2.3 | Escolher a duração do treino | High | Pending |
| US-3.1 | Colocar um bloco pela paleta | High | Pending |
| US-3.2 | Renomear e reposicionar blocos | High | Pending |
| US-3.3 | Ligar dois blocos | High | Pending |
| US-3.4 | Navegar pela prancheta | High | Pending |
| US-3.5 | Desfazer, refazer e apagar | High | Pending |
| US-4.1 | Tipar uma ligação | High | Pending |
| US-4.2 | Rotular e ajustar a seta manualmente | High | Pending |
| US-4.3 | Numerar a sequência automaticamente | High | Pending |
| US-4.4 | Reordenar a saída de um bloco | High | Pending |
| US-5.1 | Ver a legenda montada a partir do desenho | High | Pending |
| US-5.2 | Recolher a legenda | High | Pending |
| US-6.1 | Cronometrar o treino por fases | High | Pending |
| US-6.2 | Marcar o checklist da fase | High | Pending |
| US-7.1 | Estimar por usuários | High | Pending |
| US-7.2 | Estimar por volume mensal | High | Pending |
| US-7.3 | Ler a conclusão da estimativa | High | Pending |
| US-8.1 | Salvar automaticamente o treino | High | Pending |
| US-8.2 | Ver o estado do salvamento | High | Pending |
| US-8.3 | Não perder trabalho quando a rede falha | High | Pending |
| US-10.1 | Conferir os tópicos depois de terminar | High | Pending |
| US-11.2 | Começar uma sessão nova | High | Pending |
| US-1.3 | Gerenciar meu perfil | Medium | Pending |
| US-6.3 | Anotar durante o treino | Medium | Pending |
| US-9.1 | Exportar o diagrama em SVG | Medium | Pending |
| US-11.1 | Listar e abrir minhas sessões | Medium | Pending |
| US-11.3 | Excluir uma sessão | Low | Pending |
