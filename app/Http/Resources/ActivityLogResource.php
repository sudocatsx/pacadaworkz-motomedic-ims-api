<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);

        return [
            'timestamp' => $this->created_at,
            'user' => $this->user->name,
            'module' => $this->module,
            'action' => $this->action,
            'details' => $this->description,
        ];
    }
}
