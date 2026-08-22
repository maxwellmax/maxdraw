<?php

namespace Database\Seeders;

use App\Models\Component;
use App\Models\ComponentCategory;
use Illuminate\Database\Seeder;

/**
 * Os 28 componentes da paleta, agrupados pelo slug da categoria e na ordem do
 * protótipo. `icon_key` é a chave do ícone no motor do canvas
 * (`resources/js/canvas/icons.ts`).
 */
class ComponentSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = ComponentCategory::query()->pluck('id', 'slug');

        $rows = [];

        foreach ($this->components() as $categorySlug => $components) {
            foreach ($components as $index => $component) {
                $rows[] = [
                    'component_category_id' => $categoryIds[$categorySlug],
                    'slug' => $component['slug'],
                    'name' => $component['name'],
                    'short_name' => $component['short_name'] ?? $component['name'],
                    'icon_key' => $component['slug'],
                    'position' => $index + 1,
                    'is_active' => true,
                ];
            }
        }

        Component::upsert(
            $rows,
            ['slug'],
            ['component_category_id', 'name', 'short_name', 'icon_key', 'position', 'is_active'],
        );
    }

    /**
     * `short_name` só é declarado onde o protótipo define uma forma curta; nos
     * demais o rótulo padrão do bloco é o próprio nome.
     *
     * @return array<string, array<int, array{slug: string, name: string, short_name?: string}>>
     */
    private function components(): array
    {
        return [
            'client' => [
                ['slug' => 'browser', 'name' => 'Navegador'],
                ['slug' => 'mobile', 'name' => 'App Mobile'],
                ['slug' => 'thirdparty', 'name' => 'Serviço Externo'],
            ],
            'edge' => [
                ['slug' => 'dns', 'name' => 'DNS'],
                ['slug' => 'cdn', 'name' => 'CDN'],
                ['slug' => 'waf', 'name' => 'WAF / Firewall'],
                ['slug' => 'lb', 'name' => 'Load Balancer'],
                ['slug' => 'gateway', 'name' => 'API Gateway'],
            ],
            'compute' => [
                ['slug' => 'api', 'name' => 'API REST'],
                ['slug' => 'graphql', 'name' => 'GraphQL'],
                ['slug' => 'grpc', 'name' => 'gRPC'],
                ['slug' => 'service', 'name' => 'Serviço'],
                ['slug' => 'worker', 'name' => 'Worker'],
                ['slug' => 'cron', 'name' => 'Job Agendado'],
                ['slug' => 'ws', 'name' => 'WebSocket / Push'],
            ],
            'data' => [
                ['slug' => 'sql', 'name' => 'Banco Relacional'],
                ['slug' => 'replica', 'name' => 'Réplica de Leitura'],
                ['slug' => 'nosql', 'name' => 'NoSQL'],
                ['slug' => 'cache', 'name' => 'Cache'],
                ['slug' => 'blob', 'name' => 'Object Storage'],
                ['slug' => 'search', 'name' => 'Índice de Busca'],
                ['slug' => 'warehouse', 'name' => 'Data Warehouse'],
            ],
            'async' => [
                ['slug' => 'queue', 'name' => 'Fila'],
                ['slug' => 'stream', 'name' => 'Stream / Log'],
                ['slug' => 'dlq', 'name' => 'DLQ — fila de falhas', 'short_name' => 'DLQ'],
            ],
            'ops' => [
                ['slug' => 'auth', 'name' => 'Auth / Identidade'],
                ['slug' => 'monitor', 'name' => 'Métricas & Logs'],
                ['slug' => 'config', 'name' => 'Config / Flags'],
            ],
        ];
    }
}
