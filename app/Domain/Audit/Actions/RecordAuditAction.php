<?php

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model as EloquentModel;

final class RecordAuditAction
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(
        int|string|null $user = null,
        string $action = '',
        ?EloquentModel $subject = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        AuditLog::create([
            'user_id' => $user,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data_before' => $before,
            'data_after' => $after,
            'logged_at' => now(),
        ]);
    }
}
