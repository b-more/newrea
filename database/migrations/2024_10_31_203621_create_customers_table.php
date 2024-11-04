<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone_number');
            $table->string('meter_number')->unique();
            $table->string('customer_number')->unique();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('id_number')->nullable();
            $table->string('id_type')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_purchase_date')->nullable();
            $table->decimal('account_balance', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('phone_number');
            $table->index('meter_number');
            $table->index('customer_number');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
};
