<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplaintStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table("complaint_statuses")->insert([
            [
                "name" => "Unopen",
            ],
            [
                "name" => "Open",
            ],
            [
                "name" => "Closed",
            ],
            [
                "name" => "Escalated",
            ]
        ]);
    }
}
