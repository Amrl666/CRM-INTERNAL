<?php

namespace App\Domain\Campaigns\Enums;

enum CampaignStatus: string
{
    // Legacy email-draft campaign flow.
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sending = 'sending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    // Proposal outreach campaign flow (§6.8).
    case Review = 'review';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Menunggu approval',
            self::Approved => 'Disetujui',
            self::Sending => 'Mengirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::Review => 'Review',
            self::Scheduled => 'Terjadwal',
            self::Running => 'Berjalan',
            self::Paused => 'Dijeda',
            self::Failed => 'Gagal',
        };
    }
}
