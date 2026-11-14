<?php

namespace Database\Seeders;

use App\Domain\Campaigns\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::firstOrCreate(['name' => 'Perkenalan Awal'], [
            'subject' => 'Kolaborasi dengan {{company_name}}',
            'body' => "Halo {{contact_name}},\n\nSaya ingin memperkenalkan solusi kami yang membantu tim di {{company_name}} meningkatkan efisiensi outreach.\n\nApakah Anda terbuka untuk diskusi singkat minggu ini?\n\nSalam,",
        ]);

        EmailTemplate::firstOrCreate(['name' => 'Follow-up'], [
            'subject' => 'Tindak lanjut untuk {{company_name}}',
            'body' => "Halo {{contact_name}},\n\nSaya ingin menindaklanjuti percakapan sebelumnya. Apakah ada waktu untuk membahas kebutuhan {{company_name}} lebih lanjut?\n\nTerima kasih,",
        ]);
    }
}
