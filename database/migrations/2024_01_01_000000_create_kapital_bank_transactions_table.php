<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kapital_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('order_id')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('AZN');
            $table->string('status')->default('pending')->index();
            $table->string('payment_method')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->text('payment_url')->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kapital_bank_transactions');
    }
};
