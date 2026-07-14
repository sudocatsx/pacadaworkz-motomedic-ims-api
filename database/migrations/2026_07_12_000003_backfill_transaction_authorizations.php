<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $snapshot = function (?int $userId): array {
            if (! $userId) {
                return ['id' => null, 'name' => 'Unknown', 'role' => null, 'source' => 'legacy'];
            }
            $user = DB::table('users')->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->where('users.id', $userId)->select('users.id', 'users.name', 'roles.role_name')->first();

            return $user
                ? ['id' => (int) $user->id, 'name' => $user->name, 'role' => $user->role_name, 'source' => 'legacy']
                : ['id' => $userId, 'name' => 'Deleted user', 'role' => null, 'source' => 'legacy'];
        };

        DB::table('sales_transactions')->whereIn('status', ['voided', 'refunded', 'partially_refunded'])
            ->orderBy('id')->chunkById(100, function ($transactions) use ($snapshot) {
                foreach ($transactions as $transaction) {
                    $action = $transaction->status === 'voided' ? 'void' : 'refund';
                    $referenceType = $action === 'void' ? 'void' : 'return';
                    $movement = DB::table('stock_movements')->where('reference_type', $referenceType)
                        ->where('reference_id', $transaction->id)->orderByDesc('created_at')->first();
                    $log = DB::table('activity_logs')->where('module', 'Transactions')
                        ->where('description', 'like', '%'.$transaction->transaction_no.'%')->orderByDesc('created_at')->first();

                    $initiatorId = $log?->user_id ? (int) $log->user_id : null;
                    $authorizerId = $movement?->user_id ? (int) $movement->user_id : null;
                    if ($log && preg_match('/Initiator #(\d+) authorized by #(\d+)/', (string) $log->description, $matches)) {
                        $initiatorId = (int) $matches[1];
                        $authorizerId = (int) $matches[2];
                    } elseif (! $authorizerId && $initiatorId) {
                        $authorizerId = $initiatorId;
                    }

                    $items = DB::table('sales_items')->leftJoin('products', 'products.id', '=', 'sales_items.product_id')
                        ->where('sales_items.sales_transactions_id', $transaction->id)
                        ->get(['sales_items.id', 'sales_items.product_id', 'products.name', 'sales_items.quantity', 'sales_items.quantity_returned'])
                        ->map(fn ($item) => [
                            'sales_item_id' => (int) $item->id,
                            'product_id' => (int) $item->product_id,
                            'product_name' => $item->name,
                            'quantity' => (int) ($action === 'void' ? $item->quantity : $item->quantity_returned),
                        ])->filter(fn ($item) => $item['quantity'] > 0)->values()->all();

                    DB::table('transaction_authorizations')->insert([
                        'sales_transaction_id' => $transaction->id,
                        'action' => $action,
                        'result' => 'authorized',
                        'initiator_id' => $initiatorId,
                        'authorizer_id' => $authorizerId,
                        'initiator_snapshot' => json_encode($snapshot($initiatorId)),
                        'authorizer_snapshot' => json_encode($snapshot($authorizerId)),
                        'details' => json_encode([
                            'source' => 'legacy_backfill',
                            'reason' => $action === 'refund' ? $transaction->refund_reason : null,
                            'refund_type' => $action === 'refund' ? ($transaction->status === 'refunded' ? 'full' : 'partial') : null,
                            'refund_amount' => $action === 'refund' ? (float) $transaction->refund_amount : null,
                            'items' => $items,
                        ]),
                        'authorized_at' => $transaction->refunded_at ?? $log?->created_at ?? $movement?->created_at ?? $transaction->updated_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('transaction_authorizations')->whereRaw("details->>'source' = ?", ['legacy_backfill'])->delete();
    }
};
