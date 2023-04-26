<?php

declare(strict_types=1);

namespace App\Services\PaymentService\PaymentInterfaces;

use App\Transaction;

/**
 * Interface WithdrawInterface
 *
 * @package App\Services\Payment\PaymentInterfaces
 */
interface WithdrawInterface extends BasePaymentInterface
{
    /**
     * Handling withdraw payment request.
     * Checking the status of the transaction and creating the correct response.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     */
    public function doWithdraw(array $body, Transaction $transaction): array;
}
