<?php

namespace App\Http\Resources;

use App\Models\ChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChecklistItem
 */
class ChecklistItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * O `id` é a chave do mapa `checks` da sessão — é por ele que a marcação
     * sobrevive a uma reordenação do seeder.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
        ];
    }
}
