<?php

namespace App\Http\Resources;

use App\Models\Component;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Component
 */
class ComponentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * A cor não vem daqui: o bloco herda o `color_token` da categoria.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'icon_key' => $this->icon_key,
        ];
    }
}
