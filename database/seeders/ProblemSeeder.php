<?php

namespace Database\Seeders;

use App\Models\Problem;
use App\Models\ProblemItem;
use App\Models\ProblemItemType;
use App\Models\ProblemLevel;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Os 14 enunciados do protótipo. O conteúdo vive em
 * `database/seeders/data/problems.php`; aqui fica só o mapeamento para as duas
 * tabelas — `problems` e as três listas em `problem_items`.
 */
class ProblemSeeder extends Seeder
{
    public function run(): void
    {
        $levelIds = ProblemLevel::query()->pluck('id', 'slug');
        $typeIds = ProblemItemType::query()->pluck('id', 'slug');

        $problems = $this->problems();

        Problem::upsert(
            collect($problems)
                ->map(fn (array $problem, int $index): array => [
                    'slug' => $problem['slug'],
                    'name' => $problem['name'],
                    'tag' => $problem['tag'],
                    'problem_level_id' => $levelIds[$this->levelSlug($problem['lv'])],
                    'context' => $problem['context'],
                    'position' => $index + 1,
                    'is_active' => true,
                ])
                ->all(),
            ['slug'],
            ['name', 'tag', 'problem_level_id', 'context', 'position', 'is_active'],
        );

        $problemIds = Problem::query()->pluck('id', 'slug');

        $rows = [];
        $listLengths = [];

        foreach ($problems as $problem) {
            foreach ($this->listsByTypeSlug($problem) as $typeSlug => $contents) {
                foreach ($contents as $index => $content) {
                    $rows[] = [
                        'problem_id' => $problemIds[$problem['slug']],
                        'problem_item_type_id' => $typeIds[$typeSlug],
                        'position' => $index + 1,
                        'content' => $content,
                    ];
                }

                $listLengths[] = [$problemIds[$problem['slug']], $typeIds[$typeSlug], count($contents)];
            }
        }

        ProblemItem::upsert($rows, ['problem_id', 'problem_item_type_id', 'position'], ['content']);

        $this->pruneItemsBeyond($listLengths);
    }

    /**
     * Sobra de uma execução anterior com listas mais longas: as posições são
     * contíguas a partir de 1, então tudo acima do tamanho atual é órfão.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $listLengths
     */
    private function pruneItemsBeyond(array $listLengths): void
    {
        foreach ($listLengths as [$problemId, $typeId, $length]) {
            ProblemItem::query()
                ->where('problem_id', $problemId)
                ->where('problem_item_type_id', $typeId)
                ->where('position', '>', $length)
                ->delete();
        }
    }

    /**
     * @param  array{requirements: array<int, string>, scale: array<int, string>, topics: array<int, string>}  $problem
     * @return array<string, array<int, string>>
     */
    private function listsByTypeSlug(array $problem): array
    {
        return [
            'requirement' => $problem['requirements'],
            'scale' => $problem['scale'],
            'topic' => $problem['topics'],
        ];
    }

    /**
     * O `lv` do protótipo vira o slug de `problem_levels`; qualquer outro valor
     * é dado de catálogo quebrado e para o seed em vez de gravar nível errado.
     */
    private function levelSlug(int $lv): string
    {
        return match ($lv) {
            1 => 'base',
            2 => 'intermediate',
            3 => 'advanced',
            default => throw new InvalidArgumentException("Nível de problema desconhecido: [{$lv}]."),
        };
    }

    /**
     * @return array<int, array{slug: string, name: string, tag: string, lv: int, context: string, requirements: array<int, string>, scale: array<int, string>, topics: array<int, string>}>
     */
    private function problems(): array
    {
        return require database_path('seeders/data/problems.php');
    }
}
