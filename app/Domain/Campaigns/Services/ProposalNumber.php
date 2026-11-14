<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\CampaignRecipient;
use Illuminate\Support\Str;

final class ProposalNumber
{
    public static function next(): string
    {
        do {
            $number = 'PRP-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (CampaignRecipient::query()->where('proposal_number', $number)->exists());

        return $number;
    }
}
