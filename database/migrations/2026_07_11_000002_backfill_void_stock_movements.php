<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_transactions')
            ->where('status', 'voided')
            ->orderBy('id')
            ->chunkById(100, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $activity = DB::table('activity_logs')
                        ->where('action', 'Void transaction')
                        ->where('description', "Voided sales transaction #{$transaction->transaction_no}")
                        ->latest('created_at')
                        ->first();

                    $items = DB::table('sales_items')
                        ->where('sales_transactions_id', $transaction->id)
                        ->get();

                    foreach ($items as $item) {
                        $exists = DB::table('stock_movements')
                            ->where('reference_type', 'void')
                            ->where('reference_id', $transaction->id)
                            ->where('product_id', $item->product_id)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        DB::table('stock_movements')->insert([
                            'product_id' => $item->product_id,
                            'user_id' => $activity?->user_id ?? $transaction->user_id,
                            'movement_type' => 'in',
                            'quantity' => $item->quantity,
                            'reference_type' => 'void',
                            'reference_id' => $transaction->id,
                            'notes' => "Voided Sale - Transaction #{$transaction->transaction_no} (historical backfill)",
                            'created_at' => $activity?->created_at ?? $transaction->updated_at ?? $transaction->created_at,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('stock_movements')
            ->where('reference_type', 'void')
            ->where('notes', 'like', '%(historical backfill)')
            ->delete();
    }
};
