<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\Phase;
use Illuminate\Database\Seeder;

/**
 * Os 25 itens do roteiro, com o texto literal do protótipo, agrupados pelo slug
 * da fase e numerados na ordem em que aparecem.
 */
class ChecklistItemSeeder extends Seeder
{
    public function run(): void
    {
        $phaseIds = Phase::query()->pluck('id', 'slug');

        $rows = [];

        foreach ($this->contentsByPhase() as $phaseSlug => $contents) {
            foreach ($contents as $index => $content) {
                $rows[] = [
                    'phase_id' => $phaseIds[$phaseSlug],
                    'position' => $index + 1,
                    'content' => $content,
                    'is_active' => true,
                ];
            }
        }

        ChecklistItem::upsert($rows, ['phase_id', 'position'], ['content', 'is_active']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function contentsByPhase(): array
    {
        return [
            'requisitos-escopo' => [
                'Requisitos funcionais listados — o que o sistema FAZ',
                'Fora de escopo declarado em voz alta',
                'Não-funcionais: latência, disponibilidade, consistência',
                'Perfil de uso: leitura pesada ou escrita pesada?',
            ],
            'estimativas-de-capacidade' => [
                'Usuários ativos por dia e ações por usuário',
                'QPS médio e QPS de pico',
                'Armazenamento por dia, por ano e na retenção',
                'Banda de entrada e de saída',
                'Conclusão: o que essa escala obriga (cache? shard? CDN?)',
            ],
            'api-modelo-de-dados' => [
                'Endpoints principais com entrada e saída',
                'Entidades e relacionamentos',
                'Escolha do banco justificada, não presumida',
                'Chave de particionamento definida',
                'Índices necessários para as consultas do produto',
            ],
            'desenho-de-alto-nivel' => [
                'Caminho de ESCRITA desenhado ponta a ponta',
                'Caminho de LEITURA desenhado ponta a ponta',
                'Síncrono e assíncrono separados no desenho',
                'Onde entra cache e o que invalida cada entrada',
                'Fluxo narrado do clique até a resposta',
            ],
            'escala-trade-offs' => [
                'Gargalo principal nomeado',
                'Estratégia de sharding e de replicação',
                'Nenhum ponto único de falha sobrou',
                'Consistência forte ou eventual — e o porquê',
                'Retry, idempotência e backpressure',
                'O que você mediria em produção',
            ],
        ];
    }
}
