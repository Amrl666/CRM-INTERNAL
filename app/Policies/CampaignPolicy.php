<?php

namespace App\Policies;

use App\Domain\Campaigns\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.create');
    }

    public function approve(User $user): bool
    {
        return $user->can('campaigns.approve');
    }

    public function send(User $user): bool
    {
        return $user->can('campaigns.send');
    }
}
