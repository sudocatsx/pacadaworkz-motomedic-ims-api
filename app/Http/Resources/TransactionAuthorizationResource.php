<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionAuthorizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'result' => $this->result,
            'initiator' => $this->initiator_snapshot,
            'authorizer' => $this->authorizer_snapshot,
            'details' => $this->details,
            'authorized_at' => $this->authorized_at,
        ];
    }
}
