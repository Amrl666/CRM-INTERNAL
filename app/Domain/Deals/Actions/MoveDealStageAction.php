<?php

namespace App\Domain\Deals\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Deals\Models\Deal;
use App\Domain\Deals\Models\DealStageHistory;
use App\Domain\Deals\Models\PipelineStage;

final class MoveDealStageAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Deal $deal, PipelineStage $stage, ?int $userId = null): Deal
    {
        if ($stage->pipeline_id !== $deal->pipeline_id) {
            throw new \InvalidArgumentException('Stage berasal dari pipeline berbeda.');
        }

        $from = $deal->stage_id;

        if ($from === $stage->id) {
            return $deal;
        }

        DealStageHistory::create([
            'deal_id' => $deal->id,
            'from_stage_id' => $from,
            'to_stage_id' => $stage->id,
            'changed_by' => $userId,
            'changed_at' => now(),
        ]);

        $status = match (true) {
            $stage->is_won => DealStatus::Won,
            $stage->is_lost => DealStatus::Lost,
            default => DealStatus::Open,
        };

        $deal->update([
            'stage_id' => $stage->id,
            'status' => $status,
            'closed_at' => $status === DealStatus::Open ? null : now(),
        ]);

        $this->audit->handle(user: $userId, action: 'deal.stage_changed', subject: $deal, before: [
            'stage_id' => $from,
        ], after: [
            'stage_id' => $stage->id,
            'status' => $status->value,
        ]);

        return $deal;
    }
}
