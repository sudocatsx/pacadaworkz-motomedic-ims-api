<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->default(0);
        });

        DB::statement('UPDATE stock_adjustments SET unit_cost = COALESCE((SELECT cost_price FROM products WHERE products.id = stock_adjustments.product_id), 0)');
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
    }
};
