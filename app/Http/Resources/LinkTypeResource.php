<?php

namespace App\Http\Resources;

use App\Models\LinkType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LinkType
 */
class LinkTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * O tipo define traço e selo, nunca cor — o selo herda a categoria da origem.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'badge_label' => $this->badge_label,
            'dash_array' => $this->dash_array,
            'is_bidirectional_default' => $this->is_bidirectional_default,
            'gloss' => $this->gloss,
        ];
    }
}
