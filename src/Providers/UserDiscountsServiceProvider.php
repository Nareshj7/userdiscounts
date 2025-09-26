<?php

namespace Naresh\UserDiscounts\Providers;

use Naresh\UserDiscounts\Contracts\DiscountManager as DiscountManagerContract;
use Naresh\UserDiscounts\Services\DiscountManager;
use Illuminate\Support\ServiceProvider;

class UserDiscountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/user-discounts.php', 'user-discounts');

        $this->app->singleton(DiscountManagerContract::class, DiscountManager::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    private function registerPublishing(): void
    {
        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/../../config/user-discounts.php' => config_path('user-discounts.php'),
            ], 'user-discounts-config');
        }

        if (function_exists('database_path')) {
            $this->publishes([
                __DIR__ . '/../../database/migrations/' => database_path('migrations'),
            ], 'user-discounts-migrations');
        }
    }
}