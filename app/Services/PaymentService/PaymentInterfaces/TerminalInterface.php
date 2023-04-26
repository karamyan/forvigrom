<?php

declare(strict_types=1);

namespace App\Services\PaymentService\PaymentInterfaces;

use App\Transaction;

/**
 * Interface TerminalInterface.
 *
 * @package App\Services\Payment\PaymentInterfaces
 */
interface TerminalInterface extends BasePaymentInterface
{
    /**
     * Handling post request from payment to check user id.
     *
     * @param array $body
     * @param array $platformResult
     * @return mixed
     */
    public function doTerminalDepositCheck(array $body, array $platformResult): mixed;

    /**
     * Handling post request from payment to get transaction.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     */
    public function doTerminalDepositPayment(array $body, Transaction $transaction): mixed;
}
