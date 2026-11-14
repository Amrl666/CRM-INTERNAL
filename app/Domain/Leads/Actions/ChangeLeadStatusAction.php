<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Activity;
use App\Domain\Leads\Models\Lead;

final class ChangeLeadStatusAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Lead $lead, LeadStatus $target, ?int $userId = null): Lead
    {
        $from = $lead->status;

        if ($from === $target) {
            return $lead;
        }

        if (! $from->canTransitionTo($target)) {
            throw new \InvalidArgumentException(
                "Transisi status {$from->value} -> {$target->value} tidak sah."
            );
        }

        $lead->update([
            'status' => $target,
            'qualified_at' => $target === LeadStatus::Qualified ? now() : $lead->qualified_at,
        ]);

        Activity::create([
            'lead_id' => $lead->id,
            'user_id' => $userId,
            'type' => 'lead.status_changed',
            'description' => "{$from->label()} -> {$target->label()}",
            'occurred_at' => now(),
        ]);

        $this->audit->handle(user: $userId, action: 'lead.status_changed', subject: $lead, before: [
            'status' => $from->value,
        ], after: [
            'status' => $target->value,
        ]);

        return $lead;
    }
}
