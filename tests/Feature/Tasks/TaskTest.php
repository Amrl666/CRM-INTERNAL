<?php

use App\Domain\Tasks\Actions\CompleteTaskAction;
use App\Domain\Tasks\Actions\CreateTaskAction;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RoleAndPermissionSeeder::class)->run();
    config(['communications.email.default' => 'fake']);
});

it('allows admin to create and complete a task', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $task = app(CreateTaskAction::class)->handle([
        'title' => 'Follow up with PT Example',
        'priority' => 'high',
        'assigned_to' => $admin->id,
    ], $admin->id);

    expect(Task::count())->toBe(1)
        ->and($task->status)->toBe('pending');

    app(CompleteTaskAction::class)->handle($task->fresh(), $admin->id);

    expect($task->fresh()->status)->toBe('completed');
});

it('forbids user without tasks.manage from viewing tasks', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->get(route('tasks.index'))
        ->assertForbidden();
});
