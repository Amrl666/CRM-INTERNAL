<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        $environment = app()->environment();
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        $username = config("database.connections.{$connection}.username");

        if (
            $environment !== 'testing'
            || $connection !== 'pgsql'
            || $database !== 'crm_internal_test'
            || $username !== 'crm_test'
        ) {
            throw new RuntimeException(
                'Unsafe test database configuration. '
                ."Environment: {$environment}, "
                ."Connection: {$connection}, "
                ."Database: {$database}, "
                ."Username: {$username}"
            );
        }

        return parent::setUpTraits();
    }
}
