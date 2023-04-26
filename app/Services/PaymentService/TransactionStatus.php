<?php

declare(strict_types=1);

namespace App\Services\PaymentService;

/**
 * Class TransactionStatus.
 *
 * @package App\Services\Payment
 */
final class TransactionStatus
{
    public const NEW = '0';
    public const PENDING = "1";
    public const APPROVED = "2";
    public const CANCELED = "3";
    public const FAILED = '4';
    public const CREATED = '5';
    public const PROCESSING = '6';

    /**
     * Status names.
     */
    private const statuses = [
        0 => 'NEW',
        1 => 'PENDING',
        2 => 'APPROVED',
        3 => 'CANCELED',
        4 => 'FAILED',
        5 => 'CREATED',
        6 => 'PROCESSING'
    ];

    /**
     * Transaction completed statuses.
     */
    public const COMPLETED_STATUSES = [
        self::CANCELED,
        self::FAILED,
        self::APPROVED,
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
