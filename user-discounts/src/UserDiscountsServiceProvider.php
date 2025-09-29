<?php

namespace UserDiscounts;

use Illuminate\Support\ServiceProvider;
use UserDiscounts\Services\DiscountManager;
use UserDiscounts\Events\DiscountAssigned;
use UserDiscounts\Events\DiscountRevoked;
use UserDiscounts\Events\DiscountApplied;

class UserDiscountsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        
        $this->publishes([
            __DIR__.'/../config/user-discounts.php' => config_path('user-discounts.php'),
        ], 'config');

        
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        
        $this->mergeConfigFrom(__DIR__.'/../config/user-discounts.php', 'user-discounts');

        
    }

    public function register()
    {
        $this->app->singleton(DiscountManager::class, function ($app) {
            return new DiscountManager(
                $app['config']['user-discounts']
            );
        });
    }
}