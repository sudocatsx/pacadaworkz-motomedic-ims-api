<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PosProductResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        unset($data['cost_price'], $data['inventory']);

        return $data;
    }
}
