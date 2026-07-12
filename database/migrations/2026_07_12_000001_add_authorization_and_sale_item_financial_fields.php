<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('authorization_pin')->nullable()->after('password');
        });
        Schema::table('sales_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('allocated_discount', 10, 2)->default(0);
            $table->decimal('net_line_total', 10, 2)->default(0);
            $table->decimal('refunded_line_amount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sales_items', fn (Blueprint $table) => $table->dropColumn([
            'unit_cost', 'allocated_discount', 'net_line_total', 'refunded_line_amount',
        ]));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('authorization_pin'));
    }
};
