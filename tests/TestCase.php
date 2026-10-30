<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $this->ensureSafeTestDatabase($app);
        return $app;
    }

    protected function setUpTheTestEnvironment(): void
    {
        $this->ensureSafeTestDatabase($this->app);
        parent::setUpTheTestEnvironment();
    }

    protected function ensureSafeTestDatabase($app = null): void
    {
        $app = $app ?? $this->app;
        if (! $app) {
            return;
        }

        $connection = $app['config']->get('database.default', 'mysql');
        $dbName = (string) $app['config']->get("database.connections.{$connection}.database");

        $blockedDatabases = ['laundryku', 'production'];
        if (in_array(strtolower($dbName), $blockedDatabases, true) || (!str_contains(strtolower($dbName), 'test') && $dbName !== ':memory:')) {
            throw new RuntimeException(
                "GUARD VIOLATION: Test suite attempted to run against unauthorized/main database '{$dbName}'. " .
                "Tests must run on a designated test database (e.g. 'laundryku_test' or ':memory:'). " .
                "Ensure config cache is cleared before running tests (e.g. run 'composer test')."
            );
        }
    }
}
