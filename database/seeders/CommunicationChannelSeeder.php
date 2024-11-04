<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommunicationChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table("communication_channels")->insert([
            [
                "name" => "USSD"
            ],
            [
                "name" => "SMS"
            ],
            [
                "name" => "IVR"
            ],
            [
                "name" => "WhatsApp"
            ]
        ]);
    }
}
