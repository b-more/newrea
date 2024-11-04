<?php

// database/migrations/2024_01_01_000001_create_float_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

