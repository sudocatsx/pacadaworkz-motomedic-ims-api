<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAuthorization extends Model
{
    protected $fillable = [
        'sales_transaction_id', 'action', 'result', 'initiator_id', 'authorizer_id',
        'initiator_snapshot', 'authorizer_snapshot', 'details', 'authorized_at',
    ];

    protected function casts(): array
    {
        return [
            'initiator_snapshot' => 'array',
            'authorizer_snapshot' => 'array',
            'details' => 'array',
            'authorized_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }
}
