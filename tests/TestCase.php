<?php

namespace Codex\UserDiscounts\Tests;

use Codex\UserDiscounts\Providers\UserDiscountsServiceProvider;
use Codex\UserDiscounts\Tests\Support\TestUser;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [UserDiscountsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('user-discounts.models.user', TestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }
}