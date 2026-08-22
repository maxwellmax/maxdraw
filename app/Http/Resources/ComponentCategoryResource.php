<?php

namespace App\Http\Resources;

use App\Models\ComponentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ComponentCategory
 */
class ComponentCategoryResource extends JsonResource
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
            'color_token' => $this->color_token,
            'components' => ComponentResource::collection($this->components)->resolve($request),
        ];
    }
}
