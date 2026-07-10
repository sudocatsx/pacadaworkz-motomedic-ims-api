<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class DashboardActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'description' => $this->description ?? $this->action,
            'user' => $this->user ? $this->user->name : 'System',
            'module' => $this->module,
            'timestamp' => Carbon::parse($this->created_at)->format('n/j/Y, g:i:s A'),
        ];
    }
}
