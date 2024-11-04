<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PaymentChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('payment_channels')->insert([
            [
                "name" => "Airtel Money"
            ],
            [
                "name" => "MTN Money"
            ],
            [
                "name" => "Zamtel Money"
            ]
        ]);
    }
}