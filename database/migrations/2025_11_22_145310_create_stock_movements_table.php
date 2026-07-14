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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('movement_type', ['in', 'out']);

            // quantity
            $table->integer('quantity');
            $table->enum('reference_type', ['purchase', 'sale', 'adjustment']);
            $table->unsignedBigInteger('reference_id');

            // Index para mabilis ang search (Best Practice)
            $table->index(['reference_type', 'reference_id']);

            // notes
            $table->text('notes')->nullable();

            // created_at ONLY
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
