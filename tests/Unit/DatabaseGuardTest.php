<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase as BasePhpUnitTestCase;
use Tests\TestCase;

class DatabaseGuardTest extends BasePhpUnitTestCase
{
    public function test_allowlist_permits_designated_test_database(): void
    {
        $this->assertTrue(TestCase::isAllowedTestDatabase('laundryku_test'));
        $this->assertTrue(TestCase::isAllowedTestDatabase('LAUNDRYKU_TEST'));
        $this->assertTrue(TestCase::isAllowedTestDatabase(' laundryku_test '));
    }

    public function test_allowlist_permits_in_memory_sqlite(): void
    {
        $this->assertTrue(TestCase::isAllowedTestDatabase(':memory:'));
    }

    public function test_allowlist_rejects_main_database(): void
    {
        $this->assertFalse(TestCase::isAllowedTestDatabase('laundryku'));
        $this->assertFalse(TestCase::isAllowedTestDatabase('LAUNDRYKU'));
    }

    public function test_allowlist_rejects_arbitrary_or_production_databases(): void
    {
        $this->assertFalse(TestCase::isAllowedTestDatabase('production'));
        $this->assertFalse(TestCase::isAllowedTestDatabase('laundryku_prod'));
        $this->assertFalse(TestCase::isAllowedTestDatabase('other_test_db'));
        $this->assertFalse(TestCase::isAllowedTestDatabase(''));
    }
}
