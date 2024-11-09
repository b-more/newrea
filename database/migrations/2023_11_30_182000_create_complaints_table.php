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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('session_id')->nullable();
            $table->unsignedBigInteger('communication_channel_id')->nullable();
            $table->unsignedBigInteger('complaint_category_id')->nullable();
            $table->unsignedBigInteger('complaint_status_id')->nullable();
            $table->string('meter_number')->nullable();
            $table->text('description')->nullable();
            $table->longText('comments')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
