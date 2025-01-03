<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AgentSeeder extends Seeder
{
    public function run()
    {
        $agents = [
            [
                'user_id' => 1,
                'approved_by' => 1,
                'province_id' => 1,
                'district_id' => 1,
                'country_id' => 1,
                'business_category_id' => 1,
                'business_type_id' => 1,
                'collection_commission_id' => 1,
                'disbursement_commission_id' => 1,
                'business_name' => 'Bless Investment',
                'business_email' => 'bless@gmail.com',
                'business_phone_number' => '0975020473',
                'agent_phone_number' => '0975020473',
                'personal_phone_number' => '0975020473',
                'business_address_line_1' => 'Woodlands',
                'gender' => 'male',
                'village' => 'Woodlands',
                'chief' => 'Chief Woodlands',
                'tribe' => 'Bemba',
                'nrc' => '123456/78/1',
                'merchant_code' => 'REA001',
                'business_tpin' => 'TPIN123456',
                'business_reg_number' => 'REG123456',
                'business_bank_name' => 'ZANACO',
                'business_bank_account_number' => '0123456789',
                'business_bank_account_name' => 'Bless Investment',
                'business_bank_account_branch_name' => 'Lusaka Main',
                'next_of_kin_name' => 'John Doe',
                'next_of_kin_relation' => 'Brother',
                'next_of_kin_address' => 'Plot 456, Lusaka',
                'next_of_kin_number' => '+260977333444',
                'pin' => '1234',
                'float_balance' => 5000.00,
                'float_limit' => 10000.00,
                'commission_rate' => 2.50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'user_id' => 2,
                'approved_by' => 1,
                'province_id' => 1,
                'district_id' => 1,
                'country_id' => 1,
                'business_category_id' => 1,
                'business_type_id' => 1,
                'collection_commission_id' => 1,
                'disbursement_commission_id' => 1,
                'business_name' => 'Dennis Investments',
                'business_email' => 'dennis@gmail.com',
                'business_phone_number' => '0979669350',
                'agent_phone_number' => '0979669350',
                'personal_phone_number' => '0979669350',
                'business_address_line_1' => 'Chelston',
                'gender' => 'male',
                'village' => 'Chelston',
                'chief' => 'Chief Chelston',
                'tribe' => 'Ngoni',
                'nrc' => '123457/78/1',
                'merchant_code' => 'REA002',
                'business_tpin' => 'TPIN123457',
                'business_reg_number' => 'REG123457',
                'business_bank_name' => 'ZANACO',
                'business_bank_account_number' => '0123456790',
                'business_bank_account_name' => 'Dennis Investments',
                'business_bank_account_branch_name' => 'Chelston Branch',
                'next_of_kin_name' => 'Jane Doe',
                'next_of_kin_relation' => 'Sister',
                'next_of_kin_address' => 'Plot 457, Lusaka',
                'next_of_kin_number' => '+260977333445',
                'pin' => '1234',
                'float_balance' => 5000.00,
                'float_limit' => 10000.00,
                'commission_rate' => 2.50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'user_id' => 5,
                'approved_by' => 1,
                'province_id' => 1,
                'district_id' => 1,
                'country_id' => 1,
                'business_category_id' => 1,
                'business_type_id' => 1,
                'collection_commission_id' => 1,
                'disbursement_commission_id' => 1,
                'business_name' => 'MoMils Supplier Limited',
                'business_email' => 'momils@gmail.com',
                'business_phone_number' => '+260973890787',
                'agent_phone_number' => '+260973890787',
                'personal_phone_number' => '+260973890787',
                'business_address_line_1' => 'Plot 123, Great East Road',
                'gender' => 'male',
                'village' => 'Chilanga',
                'chief' => 'Chief Chilanga',
                'tribe' => 'Lozi',
                'nrc' => '123456/78/1',
                'merchant_code' => 'REA003',
                'business_tpin' => 'TPIN123456',
                'business_reg_number' => 'REG123456',
                'business_bank_name' => 'ZANACO',
                'business_bank_account_number' => '0123456789',
                'business_bank_account_name' => 'Seed Master Limited',
                'business_bank_account_branch_name' => 'Lusaka Main',
                'next_of_kin_name' => 'John Doe',
                'next_of_kin_relation' => 'Brother',
                'next_of_kin_address' => 'Plot 456, Lusaka',
                'next_of_kin_number' => '+260977333444',
                'pin' => '1234',
                'float_balance' => 5000.00,
                'float_limit' => 10000.00,
                'commission_rate' => 2.50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'user_id' => 5,
                'approved_by' => 1,
                'province_id' => 1,
                'district_id' => 1,
                'country_id' => 1,
                'business_category_id' => 1,
                'business_type_id' => 1,
                'collection_commission_id' => 1,
                'disbursement_commission_id' => 1,
                'business_name' => 'Eneya Grocery Store',
                'business_email' => 'eneya@gmail.com',
                'business_phone_number' => '+260977608663',
                'agent_phone_number' => '+260977608663',
                'personal_phone_number' => '+260977608663',
                'business_address_line_1' => 'Plot 123, Great East Road',
                'gender' => 'male',
                'village' => 'Chilanga',
                'chief' => 'Chief Chilanga',
                'tribe' => 'Chewa',
                'nrc' => '123456/78/1',
                'merchant_code' => 'REA004',
                'business_tpin' => 'TPIN123456',
                'business_reg_number' => 'REG123456',
                'business_bank_name' => 'ZANACO',
                'business_bank_account_number' => '0123456789',
                'business_bank_account_name' => 'Seed Master Limited',
                'business_bank_account_branch_name' => 'Lusaka Main',
                'next_of_kin_name' => 'John Doe',
                'next_of_kin_relation' => 'Brother',
                'next_of_kin_address' => 'Plot 456, Lusaka',
                'next_of_kin_number' => '+260977333444',
                'pin' => '1234',
                'float_balance' => 5000.00,
                'float_limit' => 10000.00,
                'commission_rate' => 2.50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'user_id' => 5,
                'approved_by' => 1,
                'province_id' => 1,
                'district_id' => 1,
                'country_id' => 1,
                'business_category_id' => 1,
                'business_type_id' => 1,
                'collection_commission_id' => 1,
                'disbursement_commission_id' => 1,
                'business_name' => 'Bright Chifulo',
                'business_email' => 'bc@grea.co.zm',
                'business_phone_number' => '+260953087870',
                'agent_phone_number' => '+260953087870',
                'personal_phone_number' => '+260953087870',
                'business_address_line_1' => 'Plot 123, Great East Road',
                'gender' => 'male',
                'village' => 'Chilanga',
                'chief' => 'Chief Chilanga',
                'tribe' => 'Chewa',
                'nrc' => '123456/78/1',
                'merchant_code' => 'REA005',
                'business_tpin' => 'TPIN123456',
                'business_reg_number' => 'REG123456',
                'business_bank_name' => 'ZANACO',
                'business_bank_account_number' => '0123456789',
                'business_bank_account_name' => 'Bright Chifulo',
                'business_bank_account_branch_name' => 'Lusaka Main',
                'next_of_kin_name' => 'John Doe',
                'next_of_kin_relation' => 'Brother',
                'next_of_kin_address' => 'Plot 456, Lusaka',
                'next_of_kin_number' => '+260977333444',
                'pin' => '1234',
                'float_balance' => 5000.00,
                'float_limit' => 10000.00,
                'commission_rate' => 2.50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]

        ];

        DB::table('agents')->insert($agents);
    }
}
