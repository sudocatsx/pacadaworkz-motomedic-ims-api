<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Import AttributesValueResource

class AttributeResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'attribute_values' => AttributesValueResource::collection($this->attribute_values),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
