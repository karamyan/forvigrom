<?php

declare(strict_types=1);

namespace App\Services\PaymentService\PaymentInterfaces;

use App\Services\PaymentService\Exceptions\FieldValidationException;

/**
 * Interface BasePaymentInterface.
 *
 * @package App\Services\Payment\PaymentInterfaces
 */
interface BasePaymentInterface
{
    /**
     * Validate payment specific withdraw fields.
     *
     * @param array $body
     * @throws FieldValidationException
     */
    public function validateDepositFields(array $body): void;

    /**
     * Validate payment specific withdraw fields.
     *
     * @param array $body
     * @throws FieldValidationException
     */
    public function validateWithdrawFields(array $body): void;

    /**
     * Set amount.
     *
     * @param string $amount
     */
    public function setAmount(string $amount): void;

    /**
     * Get amount.
     *
     * @return float
     */
    public function getAmount(): float|int|string;

    /**
     * Set currency.
     *
     * @param string $currency
     */
    public function setCurrency(string $currency): void;
}
