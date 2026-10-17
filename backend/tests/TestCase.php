<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Http::preventStrayRequests();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            if (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction('acos', 'acos', 1);
                $pdo->sqliteCreateFunction('cos', 'cos', 1);
                $pdo->sqliteCreateFunction('sin', 'sin', 1);
                $pdo->sqliteCreateFunction('radians', 'deg2rad', 1);
            }
        }
    }
}
