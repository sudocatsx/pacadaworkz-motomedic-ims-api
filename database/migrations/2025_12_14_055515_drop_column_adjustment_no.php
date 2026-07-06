<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropUnique('stock_adjustments_adjustment_no_unique');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn('adjustment_no');
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->string('adjustment_no', 50)->unique();
            $table->dropColumn('updated_at');
        });
    }
};
