<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopProductResource extends JsonResource
{
    /**
     * Transform the collection/array into numeric values.
     */
    public static function format($data): array
    {
        return collect($data)->map(fn($value) => (float) $value)->toArray();
    }
}
