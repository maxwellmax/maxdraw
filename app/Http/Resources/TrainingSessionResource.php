<?php

namespace App\Http\Resources;

use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingSession
 */
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
            'duration_minutes' => $this->duration_minutes,
            'seq_mode' => $this->sequenceMode->slug,
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
