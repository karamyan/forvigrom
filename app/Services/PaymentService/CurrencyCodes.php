<?php

declare(strict_types=1);

namespace App\Services\PaymentService;


class CurrencyCodes
{
    /**
     * Available currency codes.
     */
    public const AvailableCurrencyCodes = [
        'AMD' => '051',
        'USD' => '840',
        'EUR' => '978',
        'RUB' => '643',
        'UAH' => '980',
        'XOF' => '952',
        'CAD' => '124',
    ];
}
