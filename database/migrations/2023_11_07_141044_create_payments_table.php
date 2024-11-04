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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->nullable();
            $table->unsignedBigInteger('payment_channel_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->unsignedBigInteger('payment_route_id')->nullable()->default(1);
            $table->unsignedBigInteger('payment_status_id')->nullable();
            $table->unsignedBigInteger('transaction_type_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('meter_number')->nullable();
            $table->string('description')->nullable();
            $table->string('comments')->nullable();
            $table->string('payment_reference_number')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status')->default('processing');
            $table->string('amount_paid')->nullable();
            $table->string('spart_transaction_id')->nullable();
            $table->string('error_message')->nullable();
            $table->string('retry_count')->nullable();
            $table->timestamps();

            $table->index(['payment_reference_number', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
