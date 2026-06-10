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
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20);
            $table->decimal('amount', 10, 2);
            $table->string('payment_type', 20);
            $table->string('checkout_request_id', 100)->nullable();
            $table->string('merchant_request_id', 100)->nullable();
            $table->string('mpesa_receipt_number', 50)->nullable();
            $table->timestamp('transaction_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('result_code')->nullable();
            $table->text('result_desc')->nullable();
            $table->string('account_reference', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
