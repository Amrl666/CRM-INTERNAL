<?php

use Tests\TestCase;

/*
 * Pest configuration for Laravel 13 / Livewire 4.
 *
 * Basis: AGENT.md §17.6 CI, §15.2 Test env. Testing uses PostgreSQL.
 * Guard: DB_DATABASE must end with '_test' before any database operation.
 */

uses(TestCase::class)->in('Feature', 'Unit');
