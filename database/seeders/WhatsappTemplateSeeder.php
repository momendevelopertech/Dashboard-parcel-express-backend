<?php

namespace Database\Seeders;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WhatsappTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $templetes=config('whatsapp_templates');
       foreach($templetes as $templete){
        WhatsAppTemplate::updateOrCreate(
            ['name'=>$templete['name']],
            ['message'=>$templete['message']]
        );
       }
    }
}
