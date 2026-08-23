import type { SessionDurationOption } from './clock';
import type { EstimateModeOption } from './estimate';
import type { ProblemOption } from './problems';
import type { SessionStorage } from './resume';
import type { RoteiroPhase } from './roteiro';
import type { SessionRecord } from './session';

/**
 * Uma sessão como o servidor a entrega logo depois de criada, no formato do
 * store. Vive no pacote porque é ele que define o formato; quem consome são os
 * testes.
 */
export function sessionRecordFixture(
    overrides: Partial<SessionRecord> = {},
): SessionRecord {
    return {
        problemId: null,
        nodes: [],
        edges: [],
        seqMode: 'out',
        checks: {},
        notes: '',
        estimate: {
            mode: 'user',
            dau: 1000000,
            act: 10,
            per_month: 10000000,
            ratio: 100,
            size: 1,
            peak: 3,
            ret: 3,
        },
        elapsedSeconds: 0,
        durationMinutes: 45,
        ...overrides,
    };
}

export type MemoryStorage = SessionStorage & {
    items: Map<string, string>;
};

/** O `localStorage` do navegador, do tamanho do que o autosave usa. */
export function memoryStorage(): MemoryStorage {
    const items = new Map<string, string>();

    return {
        items,
        getItem: (key: string): string | null => items.get(key) ?? null,
        setItem: (key: string, value: string): void =>
            void items.set(key, value),
        removeItem: (key: string): void => void items.delete(key),
    };
}

/**
 * As cinco fases do roteiro como o catálogo as entrega, com os 25 itens do
 * checklist. Pesos, textos e ids são os do `PhaseSeeder` e do
 * `ChecklistItemSeeder`, e `tests/Feature/Frontend/DrillClockTest.php` e
 * `DrillRoteiroTest.php` conferem fixture contra seeder — o roteiro é testado
 * contra o catálogo de verdade.
 */
export function phaseOptionsFixture(): RoteiroPhase[] {
    return [
        {
            id: 1,
            slug: 'requisitos-escopo',
            name: 'Requisitos & escopo',
            weight: 0.11,
            position: 1,
            checklist_items: [
                {
                    id: 1,
                    content:
                        'Requisitos funcionais listados — o que o sistema FAZ',
                },
                { id: 2, content: 'Fora de escopo declarado em voz alta' },
                {
                    id: 3,
                    content:
                        'Não-funcionais: latência, disponibilidade, consistência',
                },
                {
                    id: 4,
                    content: 'Perfil de uso: leitura pesada ou escrita pesada?',
                },
            ],
        },
        {
            id: 2,
            slug: 'estimativas-de-capacidade',
            name: 'Estimativas de capacidade',
            weight: 0.11,
            position: 2,
            checklist_items: [
                {
                    id: 5,
                    content: 'Usuários ativos por dia e ações por usuário',
                },
                { id: 6, content: 'QPS médio e QPS de pico' },
                {
                    id: 7,
                    content: 'Armazenamento por dia, por ano e na retenção',
                },
                { id: 8, content: 'Banda de entrada e de saída' },
                {
                    id: 9,
                    content:
                        'Conclusão: o que essa escala obriga (cache? shard? CDN?)',
                },
            ],
        },
        {
            id: 3,
            slug: 'api-modelo-de-dados',
            name: 'API & modelo de dados',
            weight: 0.18,
            position: 3,
            checklist_items: [
                { id: 10, content: 'Endpoints principais com entrada e saída' },
                { id: 11, content: 'Entidades e relacionamentos' },
                {
                    id: 12,
                    content: 'Escolha do banco justificada, não presumida',
                },
                { id: 13, content: 'Chave de particionamento definida' },
                {
                    id: 14,
                    content: 'Índices necessários para as consultas do produto',
                },
            ],
        },
        {
            id: 4,
            slug: 'desenho-de-alto-nivel',
            name: 'Desenho de alto nível',
            weight: 0.27,
            position: 4,
            checklist_items: [
                {
                    id: 15,
                    content: 'Caminho de ESCRITA desenhado ponta a ponta',
                },
                {
                    id: 16,
                    content: 'Caminho de LEITURA desenhado ponta a ponta',
                },
                {
                    id: 17,
                    content: 'Síncrono e assíncrono separados no desenho',
                },
                {
                    id: 18,
                    content: 'Onde entra cache e o que invalida cada entrada',
                },
                { id: 19, content: 'Fluxo narrado do clique até a resposta' },
            ],
        },
        {
            id: 5,
            slug: 'escala-trade-offs',
            name: 'Escala & trade-offs',
            weight: 0.33,
            position: 5,
            checklist_items: [
                { id: 20, content: 'Gargalo principal nomeado' },
                { id: 21, content: 'Estratégia de sharding e de replicação' },
                { id: 22, content: 'Nenhum ponto único de falha sobrou' },
                {
                    id: 23,
                    content: 'Consistência forte ou eventual — e o porquê',
                },
                { id: 24, content: 'Retry, idempotência e backpressure' },
                { id: 25, content: 'O que você mediria em produção' },
            ],
        },
    ];
}

/** As três durações do catálogo, com a padrão marcada. */
export function sessionDurationOptionsFixture(): SessionDurationOption[] {
    return [
        { id: 1, minutes: 30, is_default: false },
        { id: 2, minutes: 45, is_default: true },
        { id: 3, minutes: 60, is_default: false },
    ];
}

/**
 * Três problemas do catálogo, um por nível, com o texto literal do
 * `ProblemSeeder`. `tests/Feature/Frontend/ProblemBriefTest.php` confere a
 * fixture contra o seeder — o enunciado é testado contra o catálogo de
 * verdade, gabarito incluído.
 */
export function problemOptionsFixture(): ProblemOption[] {
    return [
        {
            id: 1,
            slug: 'url',
            name: 'Encurtador de URL',
            tag: 'Chave-valor · Cache',
            level: 'base',
            level_name: 'Base',
            level_position: 1,
            context:
                'Um serviço que recebe uma URL longa e devolve um link curto. Quem abre o link curto é redirecionado ao destino original. É o problema mais simples do gênero — e o melhor para treinar o roteiro inteiro sem se perder.',
            requirements: [
                'Encurtar uma URL e devolver o link curto',
                'Alias personalizado opcional',
                'Redirecionar o link curto para o destino',
                'Expiração opcional do link',
                'Contagem de cliques por link',
            ],
            scale: [
                '500 milhões de links novos por mês',
                'Leitura : escrita ≈ 100 : 1',
                'Retenção de 5 anos',
                'Redirecionamento p99 abaixo de 50 ms',
                'Disponibilidade de 99,99%',
            ],
            topics: [
                'Geração da chave: hash truncado com tratamento de colisão vs contador distribuído em base62',
                'Tamanho da chave derivado do volume total, não chutado',
                '301 permanente mata a contagem de cliques; 302 preserva',
                'Banco chave-valor particionado pela própria chave curta',
                'Cache dos links quentes na frente do banco (distribuição de cauda longa)',
                'Redirect servido na borda/CDN para cortar latência',
                'Expurgo de expirados sem varrer a tabela inteira',
                'Rate limit por conta para conter abuso e spam',
            ],
        },
        {
            id: 2,
            slug: 'feed',
            name: 'Feed de rede social',
            tag: 'Fan-out · Ranking',
            level: 'advanced',
            level_name: 'Avançado',
            level_position: 3,
            context:
                'Uma timeline que mostra os posts das contas que o usuário segue. O clássico onde a resposta certa depende inteiramente de quem posta e de quem lê — não existe uma solução única.',
            requirements: [
                'Publicar um post (texto e mídia)',
                'Seguir e deixar de seguir contas',
                'Ver a timeline das contas seguidas',
                'Curtir e comentar',
                'Paginação ao rolar',
            ],
            scale: [
                '200 milhões de usuários ativos por dia',
                'Leitura : escrita ≈ 100 : 1',
                'O usuário médio segue 300 contas',
                'Existem contas com mais de 50 milhões de seguidores',
                'Timeline carregada em até 200 ms',
            ],
            topics: [
                'Fan-out na escrita (empurra) vs fan-out na leitura (puxa) — custo de cada um',
                'Híbrido: empurrar para o usuário comum, puxar para celebridades',
                'Timeline materializada em cache, com um teto de itens por usuário',
                'Post store separado do feed store',
                'Paginação por cursor, nunca por offset',
                'Ordenação cronológica vs ranqueada e o que isso quebra',
                'Invalidação quando alguém deleta um post ou deixa de seguir',
                'Mídia servida por CDN, nunca pelo serviço de feed',
            ],
        },
        {
            id: 3,
            slug: 'drive',
            name: 'Armazenamento de arquivos',
            tag: 'Sincronização · Blocos',
            level: 'intermediate',
            level_name: 'Intermediário',
            level_position: 2,
            context:
                'Guardar arquivos na nuvem e mantê-los sincronizados entre vários dispositivos do mesmo usuário. O problema bonito aqui é enviar o mínimo possível de bytes e resolver conflito de edição.',
            requirements: [
                'Enviar e baixar arquivos',
                'Sincronizar entre dispositivos',
                'Compartilhar com outras pessoas',
                'Histórico de versões',
                'Trabalhar offline e sincronizar depois',
            ],
            scale: [
                '100 milhões de usuários, 50 GB por usuário',
                'Arquivo médio de 1 MB, com arquivos de até 50 GB',
                'Alteração propagada em menos de 10 s',
            ],
            topics: [
                'Arquivo dividido em blocos com hash — só o bloco alterado sobe',
                'Deduplicação por hash de bloco entre usuários',
                'Metadados (árvore de pastas, versões) em banco separado dos blocos',
                'Serviço de notificação para avisar os outros dispositivos',
                'Resolução de conflito: última escrita vence vs manter as duas versões',
                'Upload retomável para arquivos grandes',
                'Permissões de compartilhamento herdadas na árvore',
                'Cliente mantém um journal local para trabalhar offline',
            ],
        },
    ];
}

/**
 * Os dois modos da calculadora como o catálogo os entrega, com a linha que
 * cada um destaca. `tests/Feature/Frontend/DrillEstimateTest.php` confere a
 * fixture contra o `EstimateModeSeeder` — o destaque é testado contra o
 * catálogo de verdade, não contra uma cópia no cliente.
 */
export function estimateModeOptionsFixture(): EstimateModeOption[] {
    return [
        {
            id: 1,
            slug: 'user',
            name: 'Por usuários',
            highlighted_row: 'Escritas por dia',
        },
        {
            id: 2,
            slug: 'month',
            name: 'Por volume mensal',
            highlighted_row: 'Escritas por mês',
        },
    ];
}
