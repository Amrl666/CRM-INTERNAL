<?php

namespace App\Policies;

use App\Domain\Communications\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inbox.view');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->can('inbox.view');
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $user->can('inbox.manage');
    }
}
