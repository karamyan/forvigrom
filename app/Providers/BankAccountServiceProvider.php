<?php

declare(strict_types=1);

namespace App\Providers;


use App\Services\BankAccountService\BankAccountService;
use Illuminate\Support\ServiceProvider;

class BankAccountServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(BankAccountService::class, function ($app) {
            $configs = config('services.evoca_accounts');

            return new BankAccountService($app->request, $configs);
        });
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
