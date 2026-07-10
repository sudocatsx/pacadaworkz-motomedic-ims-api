<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTrendResource extends JsonResource
{
    /**
     * Transform the associative array into numeric values.
     */
    public static function format(array $data): array
    {
        return collect($data)->map(fn($value) => (float) $value)->toArray();
    }
}
