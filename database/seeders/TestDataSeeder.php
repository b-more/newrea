<?php
// database/seeders/TestDataSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        // Seed languages
        // DB::table('languages')->insert([
        //     ['name' => 'English', 'code' => 'en', 'is_active' => true],
        //     ['name' => 'Lunda', 'code' => 'lu', 'is_active' => true],
        // ]);

        // Seed customers


        // Seed payment methods
        // DB::table('payment_methods')->insert([
        //     ['name' => 'Mobile Money', 'code' => 'MOMO', 'is_active' => true],
        //     ['name' => 'Cash', 'code' => 'CASH', 'is_active' => true],
        // ]);

        // // Seed payment status
        // DB::table('payment_status')->insert([
        //     ['name' => 'Pending', 'code' => 'PEND'],
        //     ['name' => 'Completed', 'code' => 'COMP'],
        //     ['name' => 'Failed', 'code' => 'FAIL'],
        // ]);

        // Seed agents
        DB::table('agents')->insert([
            [
                'business_name' => 'Test Agent Shop',
                'merchant_code' => 'AGT001',
                'business_phone_number' => '260970000003',
                'agent_phone_number' => '260970000003',
                'pin' => '1234',
                'float_balance' => 5000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
