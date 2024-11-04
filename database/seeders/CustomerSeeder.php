<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        Customer::create([
            'name' => 'John Doe',
            'phone_number' => '260970000001',
            'meter_number' => 'MTR001',
            'customer_number' => 'CUST001',
            'address' => '123 Main Street',
            'city' => 'Lusaka',
            'province' => 'Lusaka',
            'email' => 'john@example.com',
            'id_number' => 'ID123456',
            'id_type' => 'National ID',
            'account_balance' => 0,
            'is_active' => true
        ]);

        Customer::create([
            'name' => 'Jane Smith',
            'phone_number' => '260970000002',
            'meter_number' => 'MTR002',
            'customer_number' => 'CUST002',
            'address' => '456 Park Avenue',
            'city' => 'Kitwe',
            'province' => 'Copperbelt',
            'email' => 'jane@example.com',
            'id_number' => 'ID789012',
            'id_type' => 'National ID',
            'account_balance' => 100,
            'is_active' => true
        ]);
    }
}

