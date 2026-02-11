<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'WELCOME_GOOGLE_DRIVER'],

            [
                'name' => 'رسالة ترحيب (تسجيل جوجل)',
                'subject' => '🎉 أهلاً بك في {{app_name}}',
                'body' => <<<MD
مرحبًا {{receiver_name}},

أهلاً وسهلاً بك في **{{app_name}}**! تم إنشاء حسابك كسائق بنجاح.  

**الخطوات التالية**
- استكمل رفع المستندات المطلوبة من داخل التطبيق  
- تأكد من إبقاء هاتفك متاحًا لتلقي الإشعارات  
- تواصل مع الدعم الفني في حال واجهت أي مشكلة  

**تفاصيل الحساب**
- الاسم: {{receiver_name}}  
- البريد الإلكتروني: {{receiver_email}}  

شكرًا لانضمامك إلينا،  
فريق عمل {{app_name}}  

📧 الدعم: {{support_email}}  
🌐 الموقع الإلكتروني: {{marketing_url}}  
MD
            ]
        );
    }
}
