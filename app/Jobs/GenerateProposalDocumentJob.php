<?php

namespace App\Jobs;

use App\Domain\Campaigns\Actions\GenerateProposalDocumentAction;
use App\Domain\Campaigns\Models\CampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateProposalDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    /** @var int[] */
    public array $backoff = [30, 120];

    public function __construct(public readonly string $recipientId)
    {
        $this->onQueue('documents');
    }

    public function handle(GenerateProposalDocumentAction $action): void
    {
        $recipient = CampaignRecipient::find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        $action->handle($recipient);
    }
}
