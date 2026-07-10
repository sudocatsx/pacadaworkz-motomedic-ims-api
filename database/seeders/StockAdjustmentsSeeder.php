<?php

namespace Database\Seeders;

use App\Models\StockAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StockAdjustmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        if (User::count() == 0) {
            User::factory()->create();
        }
        $userId = User::first()->id;

        $adjustments = [
            [
                'reason' => 'damaged',
                'notes' => 'Items damaged during shipping.',
            ],
            [
                'reason' => 'refunded',
                'notes' => 'Customer returned items for a full refund.',
            ],
            [
                'reason' => 'damaged',
                'notes' => 'Found a box of expired items in warehouse section C.',
            ],
            [
                'reason' => 'refunded',
                'notes' => 'Product recall for batch #12345.',
            ],
            [
                'reason' => 'damaged',
                'notes' => 'Water damage from a leak in the roof.',
            ],
        ];

        foreach ($adjustments as $adjustment) {
            StockAdjustment::create([
                'user_id' => $userId,
                'reason' => $adjustment['reason'],
                'notes' => $adjustment['notes'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
