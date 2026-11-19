<?php

namespace App\Jobs\Middleware;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Laravel's WithoutOverlapping middleware always resolves the default cache
 * repository. Gmail sync must use the same shared store as its running marker
 * and ShouldBeUnique lock, so this subclass selects an explicit store while
 * keeping Laravel's atomic lock and release semantics.
 */
final class WithoutOverlappingOnStore extends WithoutOverlapping
{
    public function __construct(
        $key = '',
        $releaseAfter = 0,
        $expiresAfter = 0,
        public readonly ?string $store = null,
    ) {
        parent::__construct($key, $releaseAfter, $expiresAfter);
    }

    public function handle($job, $next)
    {
        $lock = Container::getInstance()
            ->make(CacheFactory::class)
            ->store($this->store)
            ->lock($this->getLockKey($job), $this->expiresAfter);

        if ($lock->get()) {
            try {
                return $next($job);
            } finally {
                $lock->release();
            }
        }

        if (! is_null($this->releaseAfter)) {
            $job->release($this->releaseAfter);
        }
    }
}
