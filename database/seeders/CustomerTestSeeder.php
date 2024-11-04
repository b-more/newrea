<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerTestSeeder extends Seeder
{
    public function run()
    {
        $customers = [
            [
                'name' => 'John Doe',
                'phone_number' => '260970000001',
                'meter_number' => 'MTR001',
                'customer_number' => 'CUST001',
                'is_active' => true,
            ],
            [
                'name' => 'Jane Smith',
                'phone_number' => '260970000002',
                'meter_number' => 'MTR002',
                'customer_number' => 'CUST002',
                'is_active' => true,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::updateOrCreate(
                ['meter_number' => $customerData['meter_number']],
                $customerData
            );
        }
    }
}
