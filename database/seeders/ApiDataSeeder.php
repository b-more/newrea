<?php

namespace Database\Seeders;

use App\Models\ApiData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApiDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Insert data into the api_data table
         DB::table('api_data')->insert([
           [
            'type' => 'Production',
            'base_url' => 'http://reademo system-staging.spk.io:5010',
            'token' => 'ufgknvsdfAIBMBeeAfJxuuAa4nyWB_0X0Iy0jxii57HXaNUKhpTIziVYSP-nRVyCQpGrhGZu9vE7c3sAKxn9YIJeT_fFxNO.ZU-0fg.9FQ_6C4Tcv9ue2Tmda_8l3zvqnA',
  
           ],
           [
            'type' => 'Test',
            'base_url' => 'http://sparkapp-staging.spk.io:5010',
            'token' => '.eJwNw8kNwDAIBMBeeAfJxuuAa4nyWB_0X0Iy0jxii57HXaNUKhpTIziVYSP-nRVyCQpGrhGZu9vE7c3sAKxn9YIJeT_fFxNO.ZU-0fg.9FQ_6C4Tcv9ue2Tmda_8l3zvqnA',
   
           ]       
                 ]);
    }
}
