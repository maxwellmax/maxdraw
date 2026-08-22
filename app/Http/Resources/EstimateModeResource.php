<?php

namespace App\Http\Resources;

use App\Models\EstimateMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EstimateMode
 */
class EstimateModeResource extends JsonResource
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
            'highlighted_row' => $this->highlighted_row,
        ];
    }
}
