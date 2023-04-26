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
            $partnerPaymentId = $app->request->route('partner_payment_id') ?? $app->request->route('payment_id') ?? request()->get('payment_id');
            $partnerId = $app->request->route('partner_id') ?? request()->get('partner_id');

            $payment = Payment::query()->where('partner_payment_id', $partnerPaymentId)->first();
            $partner = Partner::query()->where('external_partner_id', $partnerId)->first();

            $provider = "App\\Services\\PaymentService\\Payments\\" . ucfirst($payment->handler);

            if (!class_exists($provider)) {
                throw new InvalidTypeOfPaymentException("Payment handler with id: " . $partnerPaymentId . " does not found.");
            }

            $configs = PaymentConfigs::query()->where('payment_id', $payment->id)->where('partner_id', $partner->id)->first();

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
