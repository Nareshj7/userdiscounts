<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('discount_id')->constrained('discounts')->cascadeOnDelete();
            $table->string('action', 50);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('original_amount', 12, 2)->nullable();
            $table->decimal('final_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action']);
            $table->index(['discount_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_audits');
    }
};