<?php

use App\Jobs\PingJob;
use Illuminate\Support\Facades\Queue;

it('dispatches the queue health-check job', function () {
    Queue::fake();

    PingJob::dispatch('feature-test');

    Queue::assertPushed(PingJob::class, fn (PingJob $job) => $job->marker === 'feature-test');
});

it('processes the health-check job synchronously when queue is sync', function () {
    config()->set('queue.default', 'sync');
    Cache::flush();

    (new PingJob('sync-test'))->handle();

    expect(Cache::get('queue.ping.sync-test'))->not->toBeNull();
});
