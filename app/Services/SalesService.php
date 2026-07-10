<?php

namespace App\Services;

use App\Exceptions\Sales\SalesTransactionNotFoundException;
use App\Exceptions\Sales\InvalidRefundSalesTransactionException;
use App\Models\Inventory;
use App\Models\SalesTransaction;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

use App\Models\StockMovement;

class SalesService
{
    public function __construct(private ActivityLogService $activityLogService)
    {
    }

    public function getAllSales($search = null, $filters = [])
    {
        $query = SalesTransaction::with(['user', 'sales_items.product']);

        if ($search) {
            $query->where('transaction_no', 'LIKE', "%{$search}%");
        }

        if (!empty($filters)) {
            if (isset($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }
            if (isset($filters['payment_method'])) {
                $query->where('payment_method', $filters['payment_method']);
            }
            if (isset($filters['start_date'])) {
                $query->whereDate('created_at', '>=', $filters['start_date']);
            }
            if (isset($filters['end_date'])) {
                $query->whereDate('created_at', '<=', $filters['end_date']);
            }
        }

        // Default sort
        $query->orderBy('created_at', 'desc');

        return $query->paginate(10)->withQueryString();
    }

    public function getSalesById($id)
    {
        $salesTransaction = SalesTransaction::with(['user', 'sales_items.product'])->find($id);

        if (!$salesTransaction)
            throw new SalesTransactionNotFoundException();

        return $salesTransaction;
    }

    public function voidTransaction($userId, $salesId)
    {
        return DB::transaction(function () use ($userId, $salesId) {
            $salesTransaction = SalesTransaction::with('sales_items')
                ->where([
                    'id' => $salesId,
                    'user_id' => $userId
                ])->first();

            if (!$salesTransaction) {
                throw new SalesTransactionNotFoundException();
            }

            if ($salesTransaction->status === 'voided') {
                return $salesTransaction;
            }

            if (in_array($salesTransaction->status, ['refunded', 'partially_refunded'], true)) {
                throw new InvalidRefundSalesTransactionException();
            }

            // Restore stock
            foreach ($salesTransaction->sales_items as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)->first();
                if ($inventory) {
                    $inventory->increment('quantity', $item->quantity);
                }
            }

            $salesTransaction->status = 'voided';
            $salesTransaction->save();

            $this->activityLogService->log(
                module: 'Sales',
                action: 'Void transaction',
                description: "Voided sales transaction #{$salesTransaction->transaction_no}",
                userId: $userId
            );

            return $salesTransaction;
        });
    }

    public function refundTransaction($userId, $salesId, $data)
    {
        return DB::transaction(function () use ($userId, $salesId, $data) {
            $salesTransaction = SalesTransaction::with('sales_items')
                ->where([
                    'id' => $salesId,
                    'user_id' => $userId
                ])
                ->first();

            if (!$salesTransaction) {
                throw new SalesTransactionNotFoundException();
            }

            if ($salesTransaction->status === 'voided') {
                throw new InvalidRefundSalesTransactionException('Cannot refund a voided transaction.');
            }

            if ($salesTransaction->status === 'refunded') {
                throw new InvalidRefundSalesTransactionException('The sales transaction is already fully refunded.');
            }

            $refundAmount = 0;
            $refundType = $data['refund_type'] ?? 'partial';
            $mainReason = $data['reason'] ?? null;

            if ($refundType === 'full') {
                foreach ($salesTransaction->sales_items as $item) {
                    $remainingQty = $item->quantity - $item->quantity_returned;
                    if ($remainingQty > 0) {
                        // Restore Inventory
                        $inventory = Inventory::where('product_id', $item->product_id)->first();
                        if ($inventory) {
                            $inventory->increment('quantity', $remainingQty);
                        }

                        // Create Stock Movement
                        StockMovement::create([
                            'product_id' => $item->product_id,
                            'user_id' => $userId,
                            'movement_type' => 'in',
                            'quantity' => $remainingQty,
                            'reference_type' => 'return',
                            'reference_id' => $salesTransaction->id,
                            'notes' => 'Full Refund: ' . $mainReason
                        ]);

                        $item->quantity_returned = $item->quantity;
                        $item->save();
                    }
                }
                $refundAmount = $salesTransaction->total_amount - $salesTransaction->refund_amount;
                // $salesTransaction->status = 'refunded';
            } else {
                // Partial
                foreach ($data['refund_items'] as $refundItem) {
                    $item = $salesTransaction->sales_items->where('id', $refundItem['sales_item_id'])->first();

                    if (!$item) {
                        throw new InvalidRefundSalesTransactionException("Invalid sales item ID: {$refundItem['sales_item_id']} does not belong to this transaction.");
                    }

                    $qtyToRefund = $refundItem['quantity'];
                    $currentReturned = $item->quantity_returned;

                    if ($qtyToRefund + $currentReturned > $item->quantity) {
                        throw new InvalidRefundSalesTransactionException("Cannot refund more than sold quantity for item {$item->product_id}");
                    }

                    // Restore Inventory
                    $inventory = Inventory::where('product_id', $item->product_id)->first();
                    if ($inventory) {
                        $inventory->increment('quantity', $qtyToRefund);
                    }

                    // Stock Movement
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'user_id' => $userId,
                        'movement_type' => 'in',
                        'quantity' => $qtyToRefund,
                        'reference_type' => 'return',
                        'reference_id' => $salesTransaction->id,
                        'notes' => 'Partial Refund: ' . ($refundItem['reason'] ?? $mainReason)
                    ]);

                    $item->quantity_returned += $qtyToRefund;
                    $item->save();

                    $refundAmount += $item->unit_price * $qtyToRefund;
                }
            }

            $salesTransaction->refund_amount += $refundAmount;
            $salesTransaction->refund_reason = $mainReason;
            $salesTransaction->refunded_at = now();

            if ($salesTransaction->refund_amount >= $salesTransaction->total_amount) {
                $salesTransaction->status = 'refunded';
            } else {
                $salesTransaction->status = 'partially_refunded';
            }

            $salesTransaction->save();

            $refundTypeDescription = ($refundType === 'full') ? 'Fully' : 'Partially';

            $this->activityLogService->log(
                module: 'Sales',
                action: 'Refund',
                description: "{$refundTypeDescription} refunded {$refundAmount} for sales transaction #{$salesTransaction->transaction_no}",
                userId: $userId
            );

            return $salesTransaction;
        });
    }

    public function getReceiptData($id)
    {
        $transaction = SalesTransaction::with(['user', 'sales_items.product'])->find($id);

        if (!$transaction) {
            throw new SalesTransactionNotFoundException();
        }

        // Fetch System Settings
        $settings = SystemSetting::whereIn('setting_key', [
            'business_name',
            'business_address',
            'contact_info',
            'logo_url',
            'tax_id',
            'footer_message',
            'return_policy'
        ])->pluck('setting_value', 'setting_key');

        return [
            'business_info' => [
                'name' => $settings['business_name'] ?? 'Pacadaworkz Moto Medic',
                // 'address' => $settings['business_address'] ?? '[Business Address Not Set - Please Configure]',
                'address' => $settings['business_address'] ?? '10A 5th St East Grace Park, Caloocan, Philippines',
                // 'contact_info' => $settings['contact_info'] ?? '[Contact No. Not Set]',
                'contact_info' => $settings['contact_info'] ?? 'pacadaworkz2021@gmail.com',
                'logo_url' => $settings['logo_url'] ?? null, // Keep null to avoid broken image links
                // 'tax_id' => $settings['tax_id'] ?? '[TIN/Tax ID Not Set]',
            ],
            'transaction_info' => [
                'reference_number' => $transaction->transaction_no,
                'date' => $transaction->created_at->format('Y-m-d'),
                'time' => $transaction->created_at->format('H:i:s'),
                'cashier_name' => $transaction->user ? $transaction->user->name : 'N/A',
                'station_id' => 'Main Counter',
            ],
            'items' => $transaction->sales_items->map(function ($item) {
                return [
                    'name' => $item->product->name,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->unit_price,
                    'subtotal' => (float) ($item->quantity * $item->unit_price),
                ];
            }),
            'totals' => [
                'subtotal' => (float) $transaction->subtotal,
                'discount_amount' => (float) $transaction->discount,
                'tax_amount' => (float) $transaction->tax,
                'grand_total' => (float) $transaction->total_amount,
            ],
            'payment' => [
                'payment_method' => $transaction->payment_method,
                'amount_tendered' => (float) $transaction->amount_tendered,
                'change_due' => (float) $transaction->change,
            ],
            'footer' => [
                'message' => $settings['footer_message'] ?? 'Thank you for your business!',
                'return_policy' => $settings['return_policy'] ?? 'No return, no exchange.',
            ]
        ];
    }
}
