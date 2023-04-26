<?php

declare(strict_types=1);

namespace App\Services\PaymentService\PaymentInterfaces;

use App\Transaction;

/**
 * Interface AccountTransferInterface.
 *
 * @package App\Services\Payment\PaymentInterfaces
 */
interface AccountTransferInterface extends BasePaymentInterface
{
    /**
     * Handling sport to casino or casino to sport money transfer.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     */
    public function doAccountTransfer(array $body, Transaction $transaction): array;
}
