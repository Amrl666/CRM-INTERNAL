<?php

namespace Database\Seeders;

use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Campaigns\Models\ProposalTemplate;
use Illuminate\Database\Seeder;

class ProposalSeeder extends Seeder
{
    public function run(): void
    {
        ProposalTemplate::firstOrCreate(
            ['slug' => 'website-school', 'version' => '1.0'],
            [
                'name' => 'Proposal Website Sekolah',
                'view_path' => 'proposals.templates.website-school.v1',
                'required_variables' => [
                    'company_name', 'contact_name', 'proposal_number', 'proposal_date',
                    'sender_company', 'sender_name', 'sender_email',
                ],
                'assets' => ['font' => 'resources/fonts/PlusJakartaSans'],
                'is_active' => true,
            ]
        );

        ProposalSenderSetting::firstOrCreate(
            ['is_active' => true],
            [
                'company_name' => 'Enigma Collective Studio',
                'sender_name' => 'Dimas Aulia',
                'sender_email' => 'hello@enigmative.web.id',
                'sender_phone' => null,
                'sender_website' => 'www.enigmative.web.id',
                'footer_text' => 'This document and all design assets contained herein are the intellectual property of Enigma Collective Studio®.',
            ]
        );

        MessageTemplate::firstOrCreate(
            ['name' => 'Proposal Website Email', 'channel' => 'EMAIL'],
            [
                'subject' => 'Proposal website sekolah untuk {{company_name}}',
                'body_html' => '<p>Halo {{contact_name}},</p><p>Kami dari {{sender_company}} menyiapkan proposal website untuk {{company_name}}. Proposal nomor {{proposal_number}} kami lampirkan dalam PDF.</p><p>Jika berkenan, kami dapat menjadwalkan diskusi singkat untuk membahas kebutuhan sekolah.</p><p>Salam,<br>{{sender_name}}<br>{{sender_email}} · {{sender_website}}</p>',
                'body_text' => "Halo {{contact_name}},\n\nKami dari {{sender_company}} menyiapkan proposal website untuk {{company_name}}. Proposal nomor {{proposal_number}} kami lampirkan dalam PDF.\n\nJika berkenan, kami dapat menjadwalkan diskusi singkat untuk membahas kebutuhan sekolah.\n\nSalam,\n{{sender_name}}\n{{sender_email}} · {{sender_website}}",
                'whatsapp_body' => 'Halo {{contact_name}}, kami dari {{sender_company}} mengirimkan proposal website untuk {{company_name}} ({{proposal_number}}). PDF proposal terlampir. Silakan dibaca, kami siap berdiskusi jika ada pertanyaan.',
                'is_active' => true,
            ]
        );

        MessageTemplate::firstOrCreate(
            ['name' => 'Proposal Website WhatsApp', 'channel' => 'WHATSAPP'],
            [
                'subject' => null,
                'whatsapp_body' => 'Halo {{contact_name}}, kami dari {{sender_company}} mengirimkan proposal website untuk {{company_name}} ({{proposal_number}}). PDF proposal terlampir. Silakan dibaca, kami siap berdiskusi jika ada pertanyaan.',
                'is_active' => true,
            ]
        );
    }
}
