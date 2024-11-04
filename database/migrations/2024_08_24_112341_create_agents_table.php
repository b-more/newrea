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
        Schema::create('agents', function (Blueprint $table) {
            // Existing fields
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->unsignedBigInteger('country_id')->default(1);
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->unsignedBigInteger('business_type_id')->nullable();
            $table->unsignedBigInteger('collection_commission_id')->nullable();
            $table->unsignedBigInteger('disbursement_commission_id')->nullable();
            $table->string('account_number')->nullable();
            $table->longText('certificate_of_incorporation')->nullable();
            $table->longText('tax_clearance')->nullable();
            $table->longText('director_nrc')->nullable();
            $table->longText('director_details')->nullable();
            $table->longText('pacra_printout')->nullable();
            $table->longText('supporting_documents')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_email')->nullable();
            $table->string('business_logo')->default("https://socialimpact.com/wp-content/uploads/2021/03/logo-placeholder.jpg");
            $table->string('business_address_line_1')->nullable();
            $table->string('business_phone_number')->nullable();
            $table->string('agent_phone_number')->nullable();
            $table->string('gender')->nullable();
            $table->string('village')->nullable();
            $table->string('chief')->nullable();
            $table->string('tribe')->nullable();
            $table->string('nrc')->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_relation')->nullable();
            $table->string('next_of_kin_address')->nullable();
            $table->string('next_of_kin_number')->nullable();
            $table->string('personal_phone_number')->nullable();
            $table->string('business_bank_account_number')->nullable();
            $table->string('merchant_code')->nullable();
            $table->string('business_bank_name')->nullable();
            $table->string('business_bank_account_name')->nullable();
            $table->string('business_bank_account_branch_name')->nullable();
            $table->string('business_bank_account_branch_code')->nullable();
            $table->string('business_bank_account_sort_code')->nullable();
            $table->string('business_bank_account_swift_code')->nullable();
            $table->string('business_tpin')->nullable();
            $table->string('business_reg_number')->nullable();
            $table->string('callback_url')->nullable();
            $table->string('payment_checkout')->nullable();
            $table->boolean('is_active')->default(1);
            $table->boolean('is_deleted')->default(0);

            // New USSD-specific fields
            $table->string('pin')->nullable(); // For USSD authentication
            $table->decimal('float_balance', 15, 2)->default(0.00); // Current float balance
            $table->decimal('float_limit', 15, 2)->default(10000.00); // Maximum float allowed
            $table->decimal('commission_rate', 5, 2)->default(2.50); // Commission percentage
            $table->timestamp('last_login_at')->nullable(); // Last USSD login
            $table->integer('login_attempts')->default(0); // Failed login attempts
            $table->boolean('is_locked')->default(false); // Account lock status
            $table->string('ussd_access_level')->default('basic'); // Access level for USSD menu
            $table->json('ussd_permissions')->nullable(); // Specific USSD permissions
            $table->decimal('minimum_transaction', 15, 2)->default(1.00); // Minimum transaction amount
            $table->decimal('maximum_transaction', 15, 2)->default(5000.00); // Maximum transaction amount
            $table->boolean('can_buy_float')->default(true); // Permission to buy float
            $table->boolean('can_sell_electricity')->default(true); // Permission to sell electricity
            $table->string('operation_status')->default('active'); // operational status
            $table->timestamp('last_transaction_at')->nullable(); // Last transaction timestamp
            $table->decimal('daily_transaction_limit', 15, 2)->default(50000.00); // Daily transaction limit
            $table->json('transaction_summary')->nullable(); // Daily/weekly/monthly summaries

            $table->timestamps();

            // Add indexes for frequently accessed fields
            $table->index('agent_phone_number');
            $table->index('merchant_code');
            $table->index('is_active');
            $table->index('operation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
