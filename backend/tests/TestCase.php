<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->forceEnvironment('APP_ENV', 'testing');
        $this->forceEnvironment('DB_CONNECTION', 'sqlite');
        $this->forceEnvironment('DB_DATABASE', ':memory:');
        $this->forceEnvironment('DB_URL', '');
        $this->forceEnvironment('QUEUE_CONNECTION', 'sync');
        $this->forceEnvironment('CACHE_STORE', 'array');

        return parent::createApplication();
    }

    private function forceEnvironment(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
