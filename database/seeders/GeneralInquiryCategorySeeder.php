<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeneralInquiryCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table("general_inquiry_categories")->insert([
            [
                "name" => "How to access power?"
            ],
            [
                "name" => "How to buy units?"
            ],
            [
                "name" => "How to change the tariff plan?"
            ]
        ]);
    }
}
