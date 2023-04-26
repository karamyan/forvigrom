<?php

declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Http\Resources\TransactionResponse;
use App\Jobs\AccountTransfer;
use App\Partner;
use App\PartnerPayments;
use App\Payment;
use App\Services\PaymentService\Exceptions\FieldValidationException;
use App\Services\PaymentService\Exceptions\ForbiddenResponseException;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use App\Services\PaymentService\PaymentInterfaces\BasePaymentInterface;
use App\Transaction;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;


/**
 * Class PaymentService.
 *
 * @package App\Services\Payment
 */
class PaymentService
{
    private float $amount;

    private string $defaultCurrency = 'AMD';

    private array $availableMethods = [
        'deposit' => 'hasDeposit',
        'withdraw' => 'hasWithdraw',
        'terminal' => 'hasTerminal',
        'mobileApp' => 'hasMobileApp',
    ];

    public function __construct(private BasePaymentInterface $paymentProvider, private Partner $partner, private Payment $payment)
    {
        $this->checkAccessToPayment();

        $this->initialize();
    }

    public function getPaymentProvider(): BasePaymentInterface
    {
        return $this->paymentProvider;
    }

    public function getPartner(): Partner
    {
        return $this->partner;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    private function setAmount(string|int $amount): void
    {
        $this->amount = floatval(number_format(intval($amount), 2, '.', ''));
    }

    private function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Check if partner have access to payment.
     *
     */
    private function checkAccessToPayment(): void
    {
        // here check access
        $hasAccess = true;

        if (!$hasAccess) {
            throw new ForbiddenResponseException("Payment with id: " . request()->get('payment_id') . " does not found for this partner.");
        }
    }

    /**
     * Handling deposits request.
     *
     * @param array $body
     * @return JsonResponse
     * @throws Throwable
     * @throws ValidationException
     */
    public function doDeposit(array $body): JsonResponse
    {
        // Check if payment has access to this method, if not return exception.
        $this->hasAvailableMethod($this->availableMethods['deposit'], $body);

        $provider = $this->getPaymentProvider();
        $provider->validateDepositFields($body);
        $provider->setAmount($body['amount']);
        $provider->setCurrency($body['currency']);

        $transaction = $this->createTransaction(provider: $provider, body: $body, type: 'deposit');
        $transaction->status = TransactionStatus::PENDING;

        try {
            // Do deposit api call to selected payment.
            $deposit = $provider->doDeposit($body, $transaction);
        } catch (PaymentProviderException $paymentProviderException) {
            $transaction->status = TransactionStatus::FAILED;

            throw $paymentProviderException;
        } catch (Throwable $e) {
            $transaction->status = TransactionStatus::FAILED;

            // Set transaction status failed in error.
            $transaction->error_data = json_encode($e->getMessage());
            throw $e;
        } finally {
            // Save transaction.
            $transaction->save();
        }

        $response = collect($transaction);
        $response->put('details', $deposit);

        if ($transaction->status !== TransactionStatus::PENDING)
            app('platform-api')->depositCallback(transaction: $transaction, queueable: true);

        return (new TransactionResponse($response))->response();
    }

    /**
     * @param array $body
     * @return mixed
     */
    public function handleSuccess(array $body): mixed
    {
        $provider = $this->getPaymentProvider();

        return $provider->handleSuccess($body);
    }

    /**
     * @param array $body
     * @return mixed
     */
    public function handleFail(array $body): mixed
    {
        $provider = $this->getPaymentProvider();

        return $provider->handleFail($body);
    }

    /**
     * Handle mobile app or terminal deposit request.
     *
     * @param array $body
     * @return mixed
     */
    public function doAppDeposit(array $body): mixed
    {
        $payment = $this->getPayment();
        if (config('app.env') === 'production') {
            $this->checkIfIpAllowed(paymentId: $payment->id);
        }

        // Validating mobile app or terminal request fields.
        if ($this->getPayment()->hasTerminal()) {
            $this->getPaymentProvider()->validateTerminalDepositFields($body);
            $data = $this->getPaymentProvider()->mappingTerminalDepositFields($body);
        } elseif ($this->getPayment()->hasMobileApp()) {
            $this->getPaymentProvider()->validateMobileAppDepositFields($body);
            $data = $this->getPaymentProvider()->mappingMobileAppDepositFields($body);
        } else {
            Log::channel('errors')->error('Unavailable method for this payment.', [
                'payment_request_id' => request()->get('payment_request_id'),
                "request_uri" => request()->route()->uri(),
                "request_body" => $body
            ]);
            throw new UnprocessableEntityHttpException('Unavailable method for this payment.');
        }

        // Call doTerminalDepositCheck() or doTerminalDepositPayment() method.
        $action = "doTerminalDeposit" . ucfirst($body['action']);
        return $this->$action($data);
    }

    /**
     * Handling account transfer request.
     *
     * @param array $body
     * @return JsonResponse
     * @throws Throwable
     * @throws ValidationException
     */
    public function doAccountTransfer(array $body): JsonResponse
    {
        if ($body['from'] === 'casino') {
            throw new \Error('You cannot transfer money from casino to sport.');
        }

        $payment = $this->getPayment();
        if (config('app.env') === 'production') {
            $this->checkIfIpAllowed(paymentId: $payment->id);
        }

        $provider = $this->getPaymentProvider();
        $provider->setAmount($body['amount']);
        $provider->setCurrency($body['currency']);

        // Creating unique transaction internal id and saving this transaction in our system before payment api call.
        $internalTransactionId = $this->makeUniqueTransactionInternalId();
        $body['amount'] = $provider->getAmount();
        $body['payment_method'] = 'wallet';
        $body['payment_id'] = $this->getPayment()->id;
        $body['type'] = 'account_transfer';
        $body['description'] = 'Money transfer from ' . $body['from'] . ' to ' . $body['to'];
        $body['internal_transaction_id'] = $internalTransactionId;
        $body['status'] = TransactionStatus::NEW;

        // Creating transaction with status new.
        $transaction = (new Transaction())->fill($body);
        $transaction->save();

        try {
            $transfer = $this->getPaymentProvider()->doAccountTransfer(body: $body, transaction: $transaction);
        } catch (Throwable $e) {
            // Set transaction status failed in error.
            $transaction->error_data = json_encode($e->getMessage());

            dispatch(new AccountTransfer(body: $body, transaction: $transaction))->onQueue('account_transfer');
        }

        $transaction->status = TransactionStatus::APPROVED;
        $transaction->save();

        $response = collect($transaction);
        $response->put('details', $transfer ?? null);

        return (new TransactionResponse($response))->response();
    }

    /**
     * Check all transactions that have a pending status and it run from task scheduling.
     * Еach handler should implement this method if the payment system does not send a callback to update the status of the transaction.
     *
     * @param Transaction $transaction
     * @return false|mixed
     */
    public function checkTransactionStatus(Transaction $transaction)
    {
        $provider = $this->getPaymentProvider();

        if (!method_exists($provider, 'checkTransactionStatus'))
            return false;

        if ($provider->isHasCallback())
            return false;

        return Cache::lock('check_transaction_with_id_' . $transaction->internal_transaction_id, 8)->get(function () use ($transaction) {
            $transaction = Transaction::query()->where('id', $transaction->id)->whereIn('status', [TransactionStatus::PENDING, TransactionStatus::PROCESSING])->first();

            if (is_null($transaction)) {
                throw new NotFoundHttpException('Object not found');
            }

            try {
                $this->getPaymentProvider()->checkTransactionStatus($transaction);
            } catch (Throwable $e) {
                Log::channel('errors')->critical('Platform not responding.', [
                    'payment_request_id' => request()->get('payment_request_id'),
                    'transaction' => $transaction
                ]);

                $transaction->error_data = json_encode($e->getMessage());
                throw $e;
            } finally {
                $transaction->save();
            }

            if ($transaction->status !== TransactionStatus::PENDING) {
                if ($transaction->type == 'deposit') {
                    app('platform-api')->depositCallback(transaction: $transaction, queueable: true);
                } else if ($transaction->type == 'withdraw') {
                    app('platform-api')->withdrawCallback(transaction: $transaction, queueable: true);
                }
            }
        });
    }

    /**
     * Check if available method for this payment.
     *
     * @param string $method
     * @param array $body
     */
    private function hasAvailableMethod(string $method, array $body): void
    {
        if (!$this->getPayment()->$method()) {
            Log::channel('errors')->error('Unavailable method for this payment.', [
                'payment_request_id' => request()->get('payment_request_id'),
                "ip" => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? request()->ip(),
                "request_uri" => request()->route()->uri(),
                "request_body" => $body
            ]);
            throw new UnprocessableEntityHttpException('Unavailable method for this payment.');
        }
    }

    /**
     * Check if request ip whitelisted.
     *
     * @param int $paymentId
     * @throws ForbiddenResponseException
     */
    private function checkIfIpAllowed(int $paymentId): void
    {
        if (!app()->runningInConsole()) {
            $currentIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? request()->ip();

            $availableIps = json_decode(Cache::get('IPWhitelist') ?? [], true);

            if (array_key_exists($paymentId, $availableIps)) {
                $whitelist = $availableIps[$paymentId];

                if (!in_array($currentIp, $whitelist)) {
                    Log::channel('errors')->error('IP address is not allowed.', [
                        'payment_request_id' => request()->get('payment_request_id'),
                        "ip" => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? request()->ip(),
                        "request_uri" => request()->route()->uri(),
                        "request_body" => request()->all()
                    ]);

                    throw new ForbiddenResponseException('IP address is not allowed.', 403);
                }
            }
        }
    }

    private function createTransaction($provider, array &$body, string $type): Transaction
    {
        // create transaction
    }
}
