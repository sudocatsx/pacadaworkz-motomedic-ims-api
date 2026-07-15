<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorial_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tutorial_key');
            $table->unsignedInteger('content_version');
            $table->string('status');
            $table->unsignedInteger('current_step')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'tutorial_key', 'content_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorial_progress');
    }
};
