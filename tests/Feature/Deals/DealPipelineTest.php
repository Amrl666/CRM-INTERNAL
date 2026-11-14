<?php

use App\Domain\Companies\Models\Company;
use App\Domain\Deals\Actions\MoveDealStageAction;
use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Deals\Models\Deal;
use App\Domain\Deals\Models\Pipeline;
use App\Domain\Deals\Models\PipelineStage;
use App\Models\User;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(PipelineSeeder::class);
});

it('creates a default pipeline with won and lost stages', function () {
    $pipeline = Pipeline::where('is_default', true)->first();

    expect($pipeline)->not->toBeNull()
        ->and($pipeline->stages)->toHaveCount(6)
        ->and($pipeline->stages->where('is_won', true))->toHaveCount(1)
        ->and($pipeline->stages->where('is_lost', true))->toHaveCount(1);
});

it('moves a deal through stages and records history and status', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $pipeline = Pipeline::where('is_default', true)->with('stages')->first();
    $deal = Deal::create([
        'company_id' => Company::factory()->create()->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $pipeline->stages->first()->id,
        'owner_id' => $user->id,
        'title' => 'Kontrak Tahunan',
        'value' => 5000000,
    ]);

    $negotiation = $pipeline->stages->firstWhere('name', 'Negosiasi');
    $won = $pipeline->stages->firstWhere('is_won', true);

    app(MoveDealStageAction::class)->handle($deal, $negotiation, $user->id);
    app(MoveDealStageAction::class)->handle($deal->fresh(), $won, $user->id);

    expect($deal->fresh()->status)->toBe(DealStatus::Won)
        ->and($deal->fresh()->closed_at)->not->toBeNull()
        ->and($deal->histories()->count())->toBe(2);
});

it('rejects moving a deal to a stage from another pipeline', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $pipeline = Pipeline::where('is_default', true)->with('stages')->first();
    $stage = $pipeline->stages->first();

    $other = Pipeline::create(['name' => 'Pipeline Lain']);
    $otherStage = PipelineStage::create(['pipeline_id' => $other->id, 'name' => 'X', 'position' => 1]);

    $deal = Deal::create([
        'company_id' => Company::factory()->create()->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'title' => 'Deal',
        'value' => 1,
    ]);

    expect(fn () => app(MoveDealStageAction::class)->handle($deal, $otherStage))
        ->toThrow(InvalidArgumentException::class);
});
