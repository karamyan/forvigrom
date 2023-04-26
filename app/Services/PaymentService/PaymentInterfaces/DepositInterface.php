<?php

declare(strict_types=1);

namespace App\Services\PaymentService\PaymentInterfaces;

use App\Transaction;

/**
 * Interface DepositInterface
 *
 * @package App\Services\Payment\PaymentInterfaces
 */
interface DepositInterface extends BasePaymentInterface
{
    /**
     * Handling payment deposit request.
     * Changing the status of the transaction and creating the correct response.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     */
    public function doDeposit(array $body, Transaction $transaction): array;

    /**
     * Тhis method checks on the server side the truth and status of the transaction, generates the correct response and then updating transaction.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     */
    public function doDepositCallback(array $body, Transaction $transaction): mixed;
}
