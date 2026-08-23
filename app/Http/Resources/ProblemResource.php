<?php

namespace App\Http\Resources;

use App\Models\Problem;
use App\Models\ProblemItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Problem
 */
class ProblemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * As três listas do enunciado saem da mesma relação já carregada, separadas
     * pelo slug do tipo — nada de uma consulta por lista.
     *
     * O nível vai em três campos porque a tela usa os três: o slug identifica,
     * o nome é o que o seletor escreve e a posição é o degrau que o protótipo
     * pinta de `ok`, `warn` ou `crit`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tag' => $this->tag,
            'level' => $this->problemLevel->slug,
            'level_name' => $this->problemLevel->name,
            'level_position' => $this->problemLevel->position,
            'context' => $this->context,
            'requirements' => $this->itemsOfType('requirement'),
            'scale' => $this->itemsOfType('scale'),
            'topics' => $this->itemsOfType('topic'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function itemsOfType(string $slug): array
    {
        return $this->problemItems
            ->filter(fn (ProblemItem $item): bool => $item->problemItemType->slug === $slug)
            ->pluck('content')
            ->values()
            ->all();
    }
}
