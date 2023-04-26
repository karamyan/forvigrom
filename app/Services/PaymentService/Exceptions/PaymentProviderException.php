<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Exceptions;

use App\Transaction;
use Exception;
use Throwable;

/**
 * Class PaymentProviderExceptions.
 *
 * @package App\Services\PaymentService\Exceptions
 */
class PaymentProviderException extends Exception
{
    /**
     * PaymentProviderException constructor.
     *
     * @param Transaction $transaction
     * @param string $message
     * @param string $paymentErrorMessage
     * @param int $code
     * @param Throwable|null $previous
     * @param array $request
     * @param array $response
     */
    public function __construct(private Transaction $transaction, string $message = "", private string $paymentErrorMessage = '', int $code = 502, Throwable $previous = null, private array $request = [], private array $response = [])
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @param array $request
     */
    public function setRequest(array $request): void
    {
        $this->request = $request;
    }

    /**
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * @param array $response
     */
    public function setResponse(array $response): void
    {
        $this->response = $response;
    }

    /**
     * @return string
     */
    public function getPaymentErrorMessage(): string
    {
        return $this->paymentErrorMessage;
    }

    /**
     * @param string $paymentErrorMessage
     */
    public function setPaymentErrorMessage(string $paymentErrorMessage): void
    {
        $this->paymentErrorMessage = $paymentErrorMessage;
    }

    /**
     * @return Transaction
     */
    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    /**
     * @param Transaction $transaction
     */
    public function setTransaction(Transaction $transaction): void
    {
        $this->transaction = $transaction;
    }
}
