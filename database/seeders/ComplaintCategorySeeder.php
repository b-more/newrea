<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplaintCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table("complaint_categories")->insert([
            [
                "name" => "Power Outage",
            ],
            [
                "name" => "Billing",
            ],
            [
                "name" => "Other",
            ]
        ]);
    }
}
