<?php

namespace App\Providers;

use App\Services\PlatformApiService\PlatformApiService;
use Illuminate\Support\ServiceProvider;

class PlatformApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->alias(PlatformApiService::class, 'platform-api');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
