<?php

namespace App\Http\Resources;

use App\Models\SessionDuration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SessionDuration
 */
class SessionDurationResource extends JsonResource
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
            'minutes' => $this->minutes,
            'is_default' => $this->is_default,
        ];
    }
}
