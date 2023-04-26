<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'terminals/easypay/*/check',
        'terminals/easypay/*/payment',
        'terminals/easypay_sport/*/check',
        'terminals/easypay_sport/*/payment',
        'terminals/easypay/withdraw/*/check',
        'terminals/easypay/withdraw/*/payment',
        'terminals/easypay_sport/withdraw/*/check',
        'terminals/easypay_sport/withdraw/*/payment',
        'terminals/mobidram/*/check',
        'terminals/mobidram/*/payment',
        'terminals/mobidram_sport/*/check',
        'terminals/mobidram_sport/*/payment',
        'terminals/idram/*/check',
        'terminals/idram/*/payment',
        'terminals/idram_sport/*/check',
        'terminals/idram_sport/*/payment',
        'terminals/mobidram2/*/check',
        'terminals/mobidram2/*/payment',
        'terminals/mobidram2_sport/*/check',
        'terminals/mobidram2_sport/*/payment',
        'arca/withdraw/check',
        'arca/withdraw/check_status',
        'arca/withdraw/payment',
        'arca/withdraw/details',
        'arca/deposit/payment',
        'arca/deposit/result',
        'arca/deposit/bindings',
        'idram/deposit/payment',
        'idram/deposit/result',
        'idram/deposit/success',
        'idram/deposit/fail',
        'idram/withdraw/payment',
        'easypay/withdraw/check_status',
        'easypay/withdraw/payment',
        'payment/create',
        'internal/arca',
        'payment/custom',
        'payment/notify_url',
        'payment/notify',
        'test',
        'qiwi/deposit/payment_request',
        'qiwi/deposit/payment_response',
        'telcell_wallet/withdraw/payment',
        'telcell_wallet/deposit/payment',
        'telcell_wallet/deposit/result',
        'account_transfer/deposit/payment',
        'partner/transactions/get',
        'partner/transactions/search',
    ];
}
