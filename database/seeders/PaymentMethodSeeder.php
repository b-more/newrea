<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('payment_methods')->insert([
            [
                "name" => "cash"
            ],
            [
                "name" => "Mobile Money"
<<<<<<< HEAD
=======
            ],
            [
                "name" => "Agent Float"
>>>>>>> 9228d80d1543cee5bd5c9aca0add5f49c53f436b
            ]
        ]);
    }
}
