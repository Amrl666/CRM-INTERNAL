<?php

namespace App\Domain\Campaigns\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sending = 'sending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
