<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\District;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ApiDataSeeder::class);

        $this->call(UserSeeder::class);

        $this->call(DistrictSeeder::class);
        $this->call(BankNameSeeder::class);
        $this->call(ProvinceSeeder::class);
        $this->call(BankBranchSeeder::class);
        $this->call(BusinessTypeSeeder::class);
        $this->call(PaymentRouteSeeder::class);
        $this->call(PaymentStatusSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(TransactionTypeSeeder::class);
        $this->call(LanguageSeeder::class);
        $this->call(ComplaintCategorySeeder::class);
        $this->call(ComplaintStatusSeeder::class);
        $this->call(GeneralInquiryCategorySeeder::class);
        $this->call(UserGroupSeeder::class);
        $this->call(AgentSeeder::class);
        $this->call(PaymentChannelSeeder::class);
        $this->call(TestDataSeeder::class);
        $this->call(CustomerSeeder::class);
    }
}
