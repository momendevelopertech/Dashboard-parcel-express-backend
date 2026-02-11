<?php

namespace Database\Seeders;

use App\Models\Consignee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConsigneeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Consignee::create([
            "name" => "Abuzaid",
            "email" => "abuzaid@gmail.com",
            "cellphone" => "334343",
            "alternatePhone" => "3434343",
            "district" => "",
            "country_id" => "165",
            "state_id" => "1",
            "city_id" => "1",
            "zipcode" => "3434",
            "streetAddress" => "Alkhabura",
            "identify" => "",
            "taxNumber" => "",
            "longitude" => "",
            "latitude" => "",
            'owner_id' => 1,
            'owner_type' => "App\Models\Hub",
            "location" => "H93R+P5 Muscat, Oman"
        ]);

     
    }
}
