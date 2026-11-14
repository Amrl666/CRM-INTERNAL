<?php

namespace App\Policies;

use App\Domain\Leads\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->can('leads.view')
            && ($user->can('leads.assign') || $lead->owner_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can('leads.update')
            && ($user->can('leads.assign') || $lead->owner_id === $user->id);
    }

    public function assign(User $user): bool
    {
        return $user->can('leads.assign');
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->can('leads.delete');
    }

    public function import(User $user): bool
    {
        return $user->can('leads.import');
    }

    public function export(User $user): bool
    {
        return $user->can('leads.export');
    }
}
