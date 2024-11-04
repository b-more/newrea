<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ussd_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->string('session_id');
            $table->integer('case_no')->default(1);
            $table->integer('step_no')->default(1);
            $table->string('status')->nullable();
            $table->string('meter_no')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('language_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->decimal('merchant_amount', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            // New fields for SparkMeter integration
            $table->string('meter_number')->nullable();
            $table->string('customer_number')->nullable();
            $table->string('spark_id')->nullable();
            $table->decimal('sale_amount', 10, 2)->nullable();
            $table->decimal('float_amount', 10, 2)->nullable();
            $table->json('transaction_data')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('phone_number');
            $table->index('session_id');
            $table->index('customer_id');
            $table->index('agent_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ussd_sessions');
    }
};
