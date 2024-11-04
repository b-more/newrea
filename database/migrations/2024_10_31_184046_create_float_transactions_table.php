<?php

// database/migrations/2024_01_01_000001_create_float_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('float_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['credit', 'debit']);
            $table->string('reference_number')->unique();
            $table->string('payment_method')->default('ussd');
            $table->string('status')->default('pending');
            $table->string('description')->nullable();
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents');
            $table->foreign('processed_by')->references('id')->on('users');
            $table->index(['agent_id', 'status']);
            $table->index('reference_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('float_transactions');
    }
};

// database/migrations/2024_01_01_000002_create_agent_activity_logs_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('agent_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('activity_type');
            $table->string('session_id')->nullable();
            $table->string('phone_number');
            $table->json('details')->nullable();
            $table->string('status');
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents');
            $table->index(['agent_id', 'activity_type']);
            $table->index('session_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_activity_logs');
    }
};

// database/migrations/2024_01_01_000003_create_agent_daily_summaries_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('agent_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->date('summary_date');
            $table->integer('total_transactions');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('total_commission', 15, 2);
            $table->json('transaction_breakdown')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents');
            $table->unique(['agent_id', 'summary_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_daily_summaries');
    }
};
