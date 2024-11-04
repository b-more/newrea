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
        Schema::create('general_inquiries', function (Blueprint $table) {
            $table->id();            
            $table->string('inquiry_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('session_id')->nullable();
            $table->unsignedBigInteger('communication_channel_id')->nullable();
            $table->unsignedBigInteger('general_inquiry_category_id')->nullable();
            $table->longText('comments')->nullable();
            $table->longText('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_inquiries');
    }
};
