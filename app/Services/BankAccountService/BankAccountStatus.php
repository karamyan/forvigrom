<?php

declare(strict_types=1);

namespace App\Services\BankAccountService;

class BankAccountStatus
{
    public const NEW = '1';
    public const SUCCESS = "2";
    public const FAILED = "3";
    public const PROCESSING = '4';

    /**
     * Status names.
     */
    private const statuses = [
        '1' => 'NEW',
        '2' => 'SUCCESS',
        '3' => 'FAILED',
        '4' => 'PROCESSING'
    ];

    /**
     * Get name of status by index.
     *
     * @param int $index
     * @return string
     */
    public static function getName(int $index): string
    {
        return self::statuses[$index];
    }
}
