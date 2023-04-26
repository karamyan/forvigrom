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
        $hasAccess = PartnerPayments::query()->where('partner_id', $this->getPartner()->id)
            ->where('payment_id', $this->getPayment()->id)
            ->where('disabled', 0)
            ->exists();

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
     * Handling deposits callback request.
     *
     * @param array $body
     * @return mixed
     * @throws Throwable
     */
    public function doDepositCallback(array $body): mixed
    {
        $payment = $this->getPayment();
        if (config('app.env') === 'production') {
            $this->checkIfIpAllowed(paymentId: $payment->id);
        }

        // Check if payment has access to this method, if not return exception.
        $this->hasAvailableMethod($this->availableMethods['deposit'], $body);

        // Get transaction field and id from request body.
        $transactionId = $this->getPaymentProvider()->getTransactionId($body);

        $transaction = Transaction::query()->where($transactionId)->where('status', TransactionStatus::PENDING)->first();

        if (is_null($transaction)) {
            throw new NotFoundHttpException('Object not found');
        }

        $transaction->callback_response_data = json_encode($body);

        // Save transaction callback response data.
        $transaction->save();
        return Cache::lock('check_transaction_with_id_' . $transaction->internal_transaction_id, 8)->get(function () use ($body, $transactionId) {
            $transaction = Transaction::query()->where($transactionId)->where('status', TransactionStatus::PENDING)->first();

            if (is_null($transaction)) {
                throw new NotFoundHttpException('Object not found');
            }

            try {
                $depositCallback = $this->getPaymentProvider()->doDepositCallback(body: $body, transaction: $transaction);
            } catch (PaymentProviderException $paymentProviderException) {
                $transaction->status = TransactionStatus::FAILED;
                throw $paymentProviderException;
            } catch (Throwable $e) {

                Log::channel('errors')->critical(
                    $this->getPayment()->payment_name . ' - ' .
                    $e->getMessage(),
                    [
                        'payment_request_id' => request()->get('payment_request_id'),
                        'transaction' => $transaction
                    ]
                );

                $transaction->error_data = json_encode($e->getMessage());
                throw $e;
            } finally {
                // Set transaction status failed in error.
                $transaction->save();
            }

            $details = [];
            if (!$depositCallback instanceof Response) {
                if (!empty($depositCallback['card_info'])) {
                    $details['card_info'] = $depositCallback['card_info'];
                }
                if (!empty($depositCallback['details'])) {
                    $details['details'] = $depositCallback['details'];
                }
            }

            if ($transaction->status !== TransactionStatus::PENDING)
                app('platform-api')->depositCallback(transaction: $transaction, details: $details, queueable: true);

            return $depositCallback;
        });
    }

    /**
     * @param array $body
     * @return mixed
     */
    public function handleSuccess(array $body)
    {
        $provider = $this->getPaymentProvider();

        return $provider->handleSuccess($body);
    }

    /**
     * @param array $body
     * @return mixed
     */
    public function handleFail(array $body)
    {
        $provider = $this->getPaymentProvider();

        return $provider->handleFail($body);
    }

    /**
     * Handling payment withdraw requests.
     *
     * @param array $body
     * @return JsonResponse
     * @throws Throwable
     * @throws ValidationException
     */
    public function doWithdraw(array $body): JsonResponse
    {
        if (config('app.env') === 'development') {
            $clientId = $body['client_id'];

            if ($clientId != '15' || $body['amount'] > '2000') {
                throw new FieldValidationException('You dont have access to withdraw.', 400);
            }
        }

        $platformRequestId = $body['platform_request_id'] ?? '';

        // Check if payment has access to this method, if not return exception.
        $this->hasAvailableMethod($this->availableMethods['withdraw'], $body);

        $provider = $this->getPaymentProvider();
        $provider->validateWithdrawFields($body);
        $provider->setAmount($body['amount']);
        $provider->setCurrency($body['currency']);

        $transaction = Transaction::query()
            ->where('partner_transaction_id', $body['partner_transaction_id'])->first();

        if (is_null($transaction)) {
            $transaction = $this->createTransaction(provider: $provider, body: $body, type: 'withdraw');
        } else {
            if ($transaction->status !== TransactionStatus::CREATED) {
                throw new FieldValidationException('The given data was invalid.', 400, [
                    'partner_transaction_id' => ['The partner transaction id has already been taken.']
                ]);
            }
        }

        return Cache::lock('check_transaction_with_id_' . $transaction->internal_transaction_id, 60)->get(function () use (
            $provider,
            $body,
            $transaction,
            $platformRequestId
        ) {
            $payment = $this->getPayment();
            $partnerPaymentId = $payment->partner_payment_id;
            $redis = Redis::connection('platform');

            $status = $transaction->status;
            try {
                $withdraw = $provider->doWithdraw($body, $transaction);
            } catch (PaymentProviderException $paymentProviderException) {
                Log::channel('errors')->critical(
                    $this->getPayment()->payment_name . ' - ' .
                    $paymentProviderException->getPaymentErrorMessage(),
                    [
                        'payment_request_id' => request()->get('payment_request_id'),
                        'platform_request_id' => $platformRequestId,
                        'internal_transaction_id' => $transaction->internal_transaction_id,
                        'request' => $paymentProviderException->getRequest(),
                        'response' => $paymentProviderException->getResponse(),
                    ]);

                throw $paymentProviderException;
            } catch (ConnectException $e) {

                if (in_array($transaction->status, [TransactionStatus::NEW, TransactionStatus::CREATED])) {
                    $transaction->status = TransactionStatus::PENDING;
                }

                $redis->eval(<<<'LUA'
                    local counter = redis.call("incr", KEYS[1])
                    redis.call('EXPIRE', KEYS[1], 1200)

                    redis.call("incr", KEYS[2])
                    redis.call("EXPIRE", KEYS[2], 1200)

                    redis.call('SET', KEYS[3], redis.call('TIME')[1])
                    redis.call('EXPIRE', KEYS[3], 900)
                LUA, 3,
                    $partnerPaymentId . '_withdraw_timeout',
                    $partnerPaymentId . '_withdraw_timeout_count',
                    $partnerPaymentId . '_withdraw_timeout_timestamp'
                );

                $transaction->error_data = json_encode($e->getMessage());
                $transaction->save();

                $response = collect($transaction);
                $response->put('error_message', $e->getMessage());

                return (new TransactionResponse($response))->response();
            } catch (Throwable $e) {
                Log::channel('errors')->critical(
                    $this->getPayment()->payment_name . ' - ' .
                    $e->getMessage(),
                    [
                        'payment_request_id' => request()->get('payment_request_id'),
                        'platform_request_id' => $platformRequestId,
                        'transaction' => $transaction
                    ]
                );

                $transaction->error_data = json_encode($e->getMessage());
                $transaction->save();
                throw $e;
            }

            $transaction->save();

            $redis->del(
                $partnerPaymentId . '_withdraw_timeout_count',
                $partnerPaymentId . '_withdraw_timeout_timestamp'
            );

            $response = collect($transaction);
            $response->put('details', $withdraw);

            if ($transaction->status !== $status && $transaction->isCompleted())
                app('platform-api')->payoutCallback(transaction: $transaction, details: $withdraw, queueable: true, delay: 2);

            return (new TransactionResponse($response))->response();
        });
    }

    public function doWithdrawCallback(array $body)
    {
        $payment = $this->getPayment();
        if (config('app.env') === 'production') {
            $this->checkIfIpAllowed(paymentId: $payment->id);
        }

        // Check if payment has access to this method, if not return exception.
        $this->hasAvailableMethod($this->availableMethods['withdraw'], $body);

        // Get transaction field and id from request body.
        $transactionId = $this->getPaymentProvider()->getTransactionId($body);

        $transaction = Transaction::query()->where($transactionId)->whereIn('status', [TransactionStatus::NEW, TransactionStatus::CREATED, TransactionStatus::PENDING])->first();

        $status = $transaction->status;

        if (is_null($transaction)) {
            throw new NotFoundHttpException('Object not found');
        }

        $callbackResponse = $transaction->callback_response_data;

        $transaction->callback_response_data = json_encode([$body, $callbackResponse]);

        // Save transaction callback response data.
        $transaction->save();

        return Cache::lock('check_transaction_with_id_' . $transaction->internal_transaction_id, 8)->get(function () use ($body, $transactionId, $status) {
            $transaction = Transaction::query()->where($transactionId)->whereIn('status', [TransactionStatus::NEW, TransactionStatus::CREATED, TransactionStatus::PENDING])->first();

            try {
                $withdrawCallback = $this->getPaymentProvider()->doWithdrawCallback(body: $body, transaction: $transaction);
            } catch (PaymentProviderException $paymentProviderException) {
                $transaction->status = TransactionStatus::FAILED;
                throw $paymentProviderException;
            } catch (Throwable $e) {

                Log::channel('errors')->critical(
                    $this->getPayment()->payment_name . ' - ' .
                    $e->getMessage(),
                    [
                        'payment_request_id' => request()->get('payment_request_id'),
                        'transaction' => $transaction
                    ]
                );

                $transaction->error_data = json_encode($e->getMessage());
                throw $e;
            } finally {
                $transaction->save();
            }

            if ($transaction->status !== $status)
                app('platform-api')->payoutCallback(transaction: $transaction, details: $withdrawCallback, queueable: true);

            return $withdrawCallback;
        });
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
     * Handling terminal check request.
     * Checking user id in platform and return response with username if exists.
     *
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    private function doTerminalDepositCheck(array $body): mixed
    {
        $data = [
            'field' => 'id',
            'site_id' => $this->getPartner()->external_partner_id,
            'value' => $body['account_id']
        ];

        try {
            // Handling call to platform for check user id if exist.
            $platformResponse = app('platform-api')->checkUserStatus(data: $data);
        } catch (GuzzleException $e) {
            Log::channel('errors')->critical('Platform not responding.', [
                'payment_request_id' => request()->get('payment_request_id'),
                'request_data' => $body
            ]);

            throw new UnprocessableEntityHttpException('Platform not responding.');
        }

        if ($platformResponse['payload']['exists'] == false) {
            Log::channel('errors')->error('User not found with this id', ['payment_request_id' => request()->get('payment_request_id')]);
            if ($this->getPayment()->payment_name == 'evoca') {
                return [
                    'code' => 1,
                    'message' => "Մուտքագրված հաշվեհամարը սխալ է։",
                ];
            } else {
                return [
                    'ResponseCode' => 1,
                    'ResponseMessage' => "Մուտքագրված հաշվեհամարը սխալ է։",
                ];
            }
        }

        return $this->getPaymentProvider()->doTerminalDepositCheck($body, $platformResponse);
    }

    /**
     * Handling terminal payment request.
     *
     * @param array $body
     * @return mixed
     * @throws Throwable
     */
    private function doTerminalDepositPayment(array $body): mixed
    {
        $availablePartners = [
            5 => 'telcell',
            2 => 'easypay',
            3 => 'mobidram2'
        ];

        return Cache::lock('doTerminalDepositPayment_' . $body['external_transaction_id'], 8)->get(function () use ($body, $availablePartners) {
            $transaction = Transaction::query()
                ->where('payment_id', $this->getPayment()->id)
                ->where('external_transaction_id', $body['external_transaction_id'])
                ->first();

            if (!is_null($transaction)) {
                if ($transaction->status !== TransactionStatus::PENDING) {
                    Log::channel('errors')->info('This external_id already exists in our system.', [
                        'payment_request_id' => request()->get('payment_request_id'),
                        'payment_name' => $this->getPayment()->id,
                        'transaction_external_id' => $body['external_transaction_id'],
                        'data' => $body,
                        'ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? request()->ip()
                    ]);

                    if ($this->getPayment()->payment_name == 'evoca') {
                        return [
                            'code' => 101,
                            'message' => "Your receipt already exists in our system.",
                        ];
                    }

                    throw new UnprocessableEntityHttpException('Your receipt already exists in our system.');
                }
            } else {
                $this->getPaymentProvider()->checkTerminalToken($body);

                $internalTransactionId = $this->makeUniqueTransactionInternalId();
                $body['type'] = 'deposit';
                $body['payment_method'] = 'terminal';
                $body['description'] = 'Terminal deposit';
                $body['internal_transaction_id'] = $internalTransactionId;
                $body['payment_id'] = $this->getPayment()->id;
                $body['partner_id'] = $this->getPartner()->id;
                $body['client_id'] = $body['account_id'] ?? null;
                $body['response_data'] = json_encode($body);
                $body['status'] = TransactionStatus::PENDING;

                if (empty($body['currency'])) {
                    $body['currency'] = $this->defaultCurrency;
                }

                $transaction = (new Transaction())->fill($body);
                $transaction->save();
            }

            try {
                $terminalResponse = $this->getPaymentProvider()->doTerminalDepositPayment($body, $transaction);
            } catch (Throwable $e) {
                // Set transaction status failed in error.
                $transaction->status = TransactionStatus::PENDING;
                $transaction->error_data = json_encode($e->getMessage());
                throw $e;
            } finally {
                $transaction->save();
            }

            $transaction->status = TransactionStatus::APPROVED;
            $transaction->save();

            if (is_null($transaction->partner_transaction_id)) {
                $data = [
                    'client_by' => [
                        'field' => 'id',
                        'value' => $body['account_id'],
                        "site_id" => $this->getPartner()->external_partner_id
                    ],
                    'amount' => $body['amount'],
                    'currency' => $body['currency'] ?? $this->defaultCurrency,
                    'payment_name' => !empty($body['terminal_payment_name']) ? $availablePartners[$body['terminal_payment_name']] : $body['payment_name'],
                    'external_transaction_id' => $body['external_transaction_id'],
                    "account_type" => $body['platform'],
                ];

                try {
                    app('platform-api')->remotePayment(transaction: $transaction, data: $data, queueable: true);
                } catch (Throwable $e) {
                    $transaction->error_data = json_encode($e->getMessage());
                } finally {
                    $transaction->save();
                }
            }

            return $terminalResponse;
        });
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
     * Generate unique transaction internal id.
     *
     * @return int
     */
    private function makeUniqueTransactionInternalId(): int
    {
        $internalTransactionId = rand(1000000000000000, 9999999999999999);

        if (Transaction::query()->where('internal_transaction_id', sprintf("%s", "$internalTransactionId"))->exists()) {
            return $this->makeUniqueTransactionInternalId();
        }

        return $internalTransactionId;
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
                    app('platform-api')->payoutCallback(transaction: $transaction, queueable: true);
                }
            }

            // todo uncomment when exist withdrawCallback function
//            if ($transaction->status !== TransactionStatus::PENDING && $transaction->type == 'withdraw')
//                app('platform-api')->withdrawCallback(transaction: $transaction, queueable: true);
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

    private function initialize()
    {
        if (request()->get('amount')) {
            if (is_string(request()->get('amount'))) {
                $this->setAmount(request()->get('amount'));
            } else {
                $this->setAmount(sprintf("%.2f", request()->get('amount')));
            }
        }
    }

    private function createTransaction($provider, array &$body, string $type): Transaction
    {
        $from = $body['from'] ?? '';
        $to = $body['to'] ?? '';

        $description = [
            'deposit' => $this->getPartner()->name . ' ' . $type,
            'withdraw' => $this->getPartner()->name . ' ' . $type,
            'account_transfer' => 'Money transfer from ' . $from . ' to ' . $to,
        ];

        // Creating unique transaction internal id and saving this transaction in our system before payment api call.
        $internalTransactionId = $this->makeUniqueTransactionInternalId();

        $body['amount'] = $this->getAmount();
        $body['payment_id'] = $this->getPayment()->id;
        $body['partner_id'] = $this->getPartner()->id;
        $body['client_id'] = $body['client_id'] ?? $body['user_info']['client_id'] ?? null;
        $body['payment_method'] = $this->getPayment()->type;
        $body['type'] = $type;
        $body['description'] = $body['description'] ?? $description[$type];

        if (!empty($body['lang'])) {
            if ($body['lang'] == 'am') {
                $body['lang'] = 'hy';
            }
        }

        $body['internal_transaction_id'] = $internalTransactionId;
        $body['status'] = TransactionStatus::NEW;

        $transaction = (new Transaction())->fill($body);
        $transaction->save();

        return $transaction;
    }
}
