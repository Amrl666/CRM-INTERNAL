<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Leads\Models\Activity;
use App\Domain\Leads\Models\Lead;

final class ArchiveLeadAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Lead $lead, ?int $userId = null): Lead
    {
        $lead->update(['archived_at' => now()]);

        Activity::create([
            'lead_id' => $lead->id,
            'user_id' => $userId,
            'type' => 'lead.archived',
            'description' => 'Lead diarsipkan.',
            'occurred_at' => now(),
        ]);

        $this->audit->handle(user: $userId, action: 'lead.archived', subject: $lead);

        return $lead;
    }
}
