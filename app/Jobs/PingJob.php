<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Health-check job: proves the queue worker processes jobs end to end.
 * ponytail: single marker in cache is enough; replace with real job when T1 import lands.
 */
class PingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $marker) {}

    public function handle(): void
    {
        Cache::put("queue.ping.{$this->marker}", now()->toIso8601String(), 3600);
    }
}
