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

    /**
     * Strict allowlist of designated test databases.
     * Sourced from phpunit.xml and .env.testing (DB_DATABASE=laundryku_test).
     */
    public static function isAllowedTestDatabase(string $dbName): bool
    {
        $allowed = [
            'laundryku_test',
            ':memory:',
        ];

        return in_array(strtolower(trim($dbName)), $allowed, true);
    }

    protected function ensureSafeTestDatabase($app = null): void
    {
        $app = $app ?? $this->app;
        if (! $app) {
            return;
        }

        $connection = $app['config']->get('database.default', 'mysql');
        $dbName = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! static::isAllowedTestDatabase($dbName)) {
            throw new RuntimeException(
                "GUARD VIOLATION: Test suite attempted to run against unauthorized database '{$dbName}'. " .
                "Only allowlisted test databases ('laundryku_test', ':memory:') are permitted. " .
                "Ensure config cache is cleared before running tests (e.g. run 'composer test')."
            );
        }
    }
}
