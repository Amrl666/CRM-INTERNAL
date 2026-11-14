<?php

namespace App\Policies;

use App\Domain\Deals\Models\Deal;
use App\Models\User;

class DealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('deals.view');
    }

    public function view(User $user, Deal $deal): bool
    {
        return $user->can('deals.view')
            && ($user->can('leads.assign') || $deal->owner_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('deals.create');
    }

    public function update(User $user, Deal $deal): bool
    {
        return $user->can('deals.update')
            && ($user->can('leads.assign') || $deal->owner_id === $user->id);
    }

    public function close(User $user): bool
    {
        return $user->can('deals.close');
    }
}
