<?php

namespace App\Http\Resources;

use App\Models\Phase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Phase
 */
class PhaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'weight' => (float) $this->weight,
            'position' => $this->position,
            'checklist_items' => ChecklistItemResource::collection($this->checklistItems)->resolve($request),
        ];
    }
}
