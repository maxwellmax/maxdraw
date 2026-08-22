<?php

namespace Database\Seeders;

use App\Models\LinkType;
use Illuminate\Database\Seeder;

/**
 * `dash_array` null é traço contínuo; `gloss` é a linha de ensino que a legenda
 * automática exibe abaixo do selo.
 */
class LinkTypeSeeder extends Seeder
{
    public function run(): void
    {
        LinkType::upsert([
            [
                'slug' => 'http',
                'name' => 'HTTP / REST',
                'badge_label' => 'HTTP',
                'dash_array' => null,
                'is_bidirectional_default' => false,
                'gloss' => 'requisição e resposta; o chamador fica esperando',
                'position' => 1,
                'is_active' => true,
            ],
            [
                'slug' => 'grpc',
                'name' => 'gRPC',
                'badge_label' => 'gRPC',
                'dash_array' => null,
                'is_bidirectional_default' => false,
                'gloss' => 'RPC binário entre serviços, com contrato tipado',
                'position' => 2,
                'is_active' => true,
            ],
            [
                'slug' => 'ws',
                'name' => 'WebSocket',
                'badge_label' => 'WS',
                'dash_array' => null,
                'is_bidirectional_default' => true,
                'gloss' => 'canal aberto nos dois sentidos; o servidor empurra',
                'position' => 3,
                'is_active' => true,
            ],
            [
                'slug' => 'event',
                'name' => 'Evento — assíncrono',
                'badge_label' => 'async',
                'dash_array' => '5 4.5',
                'is_bidirectional_default' => false,
                'gloss' => 'o produtor não espera resposta; entrega desacoplada',
                'position' => 4,
                'is_active' => true,
            ],
            [
                'slug' => 'query',
                'name' => 'Consulta ao banco',
                'badge_label' => 'query',
                'dash_array' => null,
                'is_bidirectional_default' => false,
                'gloss' => 'leitura ou escrita no banco; conta no QPS dele',
                'position' => 5,
                'is_active' => true,
            ],
            [
                'slug' => 'cache',
                'name' => 'Cache lookup',
                'badge_label' => 'cache',
                'dash_array' => null,
                'is_bidirectional_default' => false,
                'gloss' => 'consulta antes do banco; pode dar miss',
                'position' => 6,
                'is_active' => true,
            ],
            [
                'slug' => 'repl',
                'name' => 'Replicação',
                'badge_label' => 'replica',
                'dash_array' => '5 4.5',
                'is_bidirectional_default' => false,
                'gloss' => 'cópia do dado para outro nó; há atraso de replicação',
                'position' => 7,
                'is_active' => true,
            ],
            [
                'slug' => 'batch',
                'name' => 'Lote / ETL',
                'badge_label' => 'batch',
                'dash_array' => '5 4.5',
                'is_bidirectional_default' => false,
                'gloss' => 'volume grande em janelas; fora do caminho do usuário',
                'position' => 8,
                'is_active' => true,
            ],
            [
                'slug' => 'retry',
                'name' => 'Falha / Retry — DLQ',
                'badge_label' => 'retry',
                'dash_array' => '2 4.5',
                'is_bidirectional_default' => false,
                'gloss' => 'estourou as tentativas e foi para a DLQ; ninguém consumiu',
                'position' => 9,
                'is_active' => true,
            ],
        ], ['slug'], ['name', 'badge_label', 'dash_array', 'is_bidirectional_default', 'gloss', 'position', 'is_active']);
    }
}
