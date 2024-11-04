<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            [
                "name" => "REA",
                "email" => "rea@ontech.co.zm",
                "password" => Hash::make("Rea.1234")
            ],
            [
                "name" => "Admin",
                "email" => "admin@ontech.co.zm",
                "password" => Hash::make("Admin.1234")
            ]
        ]);
    }
}
