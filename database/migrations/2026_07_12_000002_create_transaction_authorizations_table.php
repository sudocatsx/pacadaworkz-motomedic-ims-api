<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained('sales_transactions')->cascadeOnDelete();
            $table->string('action', 20);
            $table->string('result', 20)->default('authorized');
            $table->foreignId('initiator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('initiator_snapshot');
            $table->jsonb('authorizer_snapshot');
            $table->jsonb('details');
            $table->timestamp('authorized_at');
            $table->timestamps();

            $table->index(['sales_transaction_id', 'authorized_at']);
            $table->index(['action', 'result']);
            $table->index('initiator_id');
            $table->index('authorizer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_authorizations');
    }
};
