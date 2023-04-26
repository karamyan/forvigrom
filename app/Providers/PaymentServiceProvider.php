<?php

namespace App\Providers;

use App\Partner;
use App\Payment;
use App\PaymentConfigs;
use App\Services\PaymentService\Exceptions\InvalidTypeOfPaymentException;
use App\Services\PaymentService\PaymentService;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(PaymentService::class, function ($app) {
            // Get provider
            $provider = "App\\Services\\PaymentService\\Payments\\" . ucfirst($payment->handler);

            // get partner $partner $partner

            if (!class_exists($provider)) {
                throw new InvalidTypeOfPaymentException("Payment handler with id: " . $partnerPaymentId . " does not found.");
            }

            // Get configs $configs =

            if (!$configs) {
                throw new InvalidTypeOfPaymentException("Configs of payment type with id: \"{$partnerPaymentId}\" does not found.");
            }

            return new PaymentService(new $provider($payment, $configs->toArray(), $partner), $partner, $payment);
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
