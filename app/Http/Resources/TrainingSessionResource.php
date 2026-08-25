<?php

namespace App\Http\Resources;

use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\PreserveKeys;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `checks` é um mapa chaveado por `checklist_items.id`, e o filtro do Resource
 * reindexa arrays de chave numérica por padrão — sem `PreserveKeys` a marcação
 * do item 7 chegaria ao cliente como a marcação do item 0.
 *
 * @mixin TrainingSession
 */
#[PreserveKeys]
class TrainingSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `user_id` fica de fora de propósito: o payload é sempre do usuário
     * autenticado, e nenhuma tela precisa do dono para saber disso.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'problem_id' => $this->problem_id,
            'name' => $this->name,
            'duration_minutes' => $this->duration_minutes,
            'show_connection_order' => $this->show_connection_order,
            'elapsed_seconds' => $this->elapsed_seconds,
            'notes' => $this->notes,
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'checks' => $this->checks,
            'estimate' => $this->estimate,
            'last_opened_at' => $this->last_opened_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
