<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customer_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('feedback_number');
            $table->string('phone_number');
            $table->string('session_id')->nullable();
            $table->UnsignedBigInteger('communication_channel_id')->nullable();
            $table->text('description');
            $table->text('comment')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed', 'submitted'])
                ->default('pending');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->UnsignedBigInteger('resolved_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('feedback_number');
            $table->index('phone_number');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_feedbacks');
    }
};
