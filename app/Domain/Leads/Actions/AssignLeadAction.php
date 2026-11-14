<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Activity;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAssignment;

final class AssignLeadAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Lead $lead, int $assignTo, ?int $assignedBy = null): Lead
    {
        $previousOwner = $lead->owner_id;

        LeadAssignment::where('lead_id', $lead->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignTo,
            'assigned_by' => $assignedBy,
            'assigned_at' => now(),
        ]);

        $lead->update([
            'owner_id' => $assignTo,
            'status' => $lead->status === LeadStatus::Qualified ? LeadStatus::Assigned : $lead->status,
        ]);

        Activity::create([
            'lead_id' => $lead->id,
            'user_id' => $assignedBy,
            'type' => 'lead.assigned',
            'description' => 'Lead ditugaskan ke user #'.$assignTo.'.',
            'occurred_at' => now(),
        ]);

        $this->audit->handle(user: $assignedBy, action: 'lead.assigned', subject: $lead, before: [
            'owner_id' => $previousOwner,
        ], after: [
            'owner_id' => $assignTo,
        ]);

        return $lead;
    }
}
