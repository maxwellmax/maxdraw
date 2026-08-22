<?php

namespace App\Http\Resources;

use App\Models\SequenceMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SequenceMode
 */
class SequenceModeResource extends JsonResource
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
            'legend_text' => $this->legend_text,
            'position' => $this->position,
        ];
    }
}
