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
