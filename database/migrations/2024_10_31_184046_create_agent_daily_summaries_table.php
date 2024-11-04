<?php

// database/migrations/2024_01_01_000001_create_float_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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


