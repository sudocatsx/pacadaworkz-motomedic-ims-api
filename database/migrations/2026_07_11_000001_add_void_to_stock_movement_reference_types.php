<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_reference_type_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reference_type_check CHECK (reference_type IN ('purchase', 'sale', 'adjustment', 'return', 'opening', 'void'))");

            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('reference_type', ['purchase', 'sale', 'adjustment', 'return', 'opening', 'void'])->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_movements')->where('reference_type', 'void')->update([
            'reference_type' => 'return',
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_reference_type_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reference_type_check CHECK (reference_type IN ('purchase', 'sale', 'adjustment', 'return', 'opening'))");

            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('reference_type', ['purchase', 'sale', 'adjustment', 'return', 'opening'])->change();
        });
    }
};
