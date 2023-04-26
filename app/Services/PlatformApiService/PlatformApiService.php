<?php

declare(strict_types=1);

namespace App\Services\PlatformApiService;

use App\Http\Resources\TransactionResponse;
use App\Jobs\BankAccountNotify;
use App\Jobs\NotifyPlatform;
use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Error;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Class PlatformApiService.
 *
 * @package App\Services\Notification
 */
class PlatformApiService
{
    /**
     * @var array|string[]
     */
    private array $endpoints = [
        'production' => 'http://172.31.34.56:8302',
        'development' => 'http://phpfmp:8001',
        'local' => 'http://3.64.224.244:8302',
    ];

    /**
     * @var array|string[]
     */
    private array $paths = [
        'change_status' => '/payment/deposit_callback',
        'check_user_status' => '/user/check_status',
        'create_payment' => '/payment/remote_payment',
        'change_payout_status' => '/payment/create_payout_callback',
        'bank_account_callback' => '/payment/create_bank_account_callback',
        'withdraw_callback' => '/payment/payout_callback',
    ];

    /**
     * @param string $key
     * @return string
     */
    private function getPath(string $key): string
    {
        return $this->paths[$key];
    }

    /**
     * Get platform uri by app env.
     *
     * @return string
     */
    private function getUri(): string
    {
        return $this->endpoints[config('app.env')];
    }

    public function depositCallback(Transaction $transaction, array $details = [], array $data = [], bool $queueable = false): array
    {
        $result = [];

        if ($queueable) {
            $data = [
                "transactionId" => $transaction->partner_transaction_id,
                "transaction" => (new TransactionResponse(collect($transaction)))->jsonSerialize(),
                "meta" => [
                    "card_info" => $details['card_info'] ?? [],
                    'details' => $details['details'] ?? []
                ]
            ];

            if ($transaction->status === TransactionStatus::APPROVED)
                $data["status"] = 'success';
            else
                $data["status"] = 'failure';

            dispatch(new NotifyPlatform(transaction: $transaction, data: $data, action: 'change_status'))->onQueue('change_status');
        } else {

            $result = $this->request(transaction: $transaction, data: $data, action: 'change_status', queueable: $queueable);

            $this->setIsNotified(transaction: $transaction);

            $transaction->save();
        }

        return $result;
    }

    public function withdrawCallback(Transaction $transaction, array $data = [], bool $queueable = false): array
    {
        $result = [];

        if ($queueable) {
            dispatch(new NotifyPlatform(transaction: $transaction, data: $data, action: 'change_payout_status'))->onQueue('change_payout_status');
        } else {
            $data = [
                "transactionId" => $transaction->partner_transaction_id,
                "transaction" => $transaction
            ];

            if (in_array($transaction->status, [TransactionStatus::APPROVED, TransactionStatus::PENDING]))
                $data["status"] = 'success';
            else
                $data["status"] = 'failure';

            $result = $this->request(transaction: $transaction, data: $data, action: 'change_payout_status', queueable: $queueable);

            $this->setIsNotified(transaction: $transaction);

            $transaction->save();
        }

        return $result;
    }

    /**
     * @param Transaction $transaction
     * @param array $data
     * @param array $details
     * @param bool $queueable
     * @param int $delay
     * @return array
     */
    public function payoutCallback(Transaction $transaction, array $data = [], array $details = [], bool $queueable = false, int $delay = 0): array
    {
        $result = [];

        if ($queueable) {
            dispatch(new NotifyPlatform(transaction: $transaction, data: $data, action: 'withdraw_callback'))->onQueue('withdraw_callback')->delay(now()->addMinutes($delay));
        } else {
            $info = collect($transaction);
            $info->put('details', $details);

            $data = [
                "transaction" => (new TransactionResponse(collect($info)))->jsonSerialize()
            ];

            $result = $this->request(transaction: $transaction, data: $data, action: 'withdraw_callback', queueable: $queueable);

            $this->setIsNotified(transaction: $transaction);

            $transaction->save();
        }

        return $result;
    }

    public function remotePayment(Transaction $transaction, array $data, bool $queueable = false): array
    {
        $result = [];

        if ($queueable) {
            dispatch(new NotifyPlatform(transaction: $transaction, data: $data, action: 'create_payment'))->onQueue('create_payment');
        } else {
            $result = $this->request(transaction: $transaction, data: $data, action: 'create_payment', queueable: $queueable);

            $this->setPartnerId(result: $result, transaction: $transaction);

            $this->setIsNotified(transaction: $transaction);

            $transaction->save();
        }

        return $result;
    }

    public function bankAccountCallback(array $details = [], array $data = [], bool $queueable = false): array
    {
        $result = [];

        if ($queueable) {
            $sendData = [
                'site_id' => $data['partner_id'],
                'client_id' => $data['client_id'],
                'bank' => $data['bank_slug'],
                'partner_account_id' => $data['partner_account_id'],
                'account' => $data['bank_account_id'],
                'status' => $data['status'],
                "meta" => $details
            ];

            dispatch(new BankAccountNotify(data: $sendData, action: 'bank_account_callback'))->onQueue('bank_account_callback');
        } else {
            $client = new HttpClientService(
                new Curl()
            );

            try {
                $content = $client->makeRequest(
                    method: 'POST',
                    url: $this->getUri() . $this->getPath('bank_account_callback'),
                    config: ['headers' => ['Content-Type' => 'application/json']],
                    options: ["body" => json_encode($data), 'timeout' => 60],
                    data: $data
                );
            } catch (Throwable $e) {
                if ($queueable) {
                    dispatch(new BankAccountNotify(data: $data, action: 'bank_account_callback'))->onQueue('bank_account_callback');
                }

                throw new Error($e->getMessage(), 504);
            }
        }

        return $result;
    }

    public function checkUserStatus(array $data): array
    {
        $client = new HttpClientService(
            new Curl()
        );

        try {
            $content = $client->makeRequest(
                method: 'POST',
                url: $this->getUri() . $this->getPath('check_user_status'),
                config: ['headers' => ['Content-Type' => 'application/json']],
                options: ["body" => json_encode($data), 'timeout' => 60],
                data: $data
            );
        } catch (Throwable $e) {
            throw new Error($e->getMessage(), 504);
        }

        return json_decode($content, true);
    }

    public function request(Transaction $transaction, array $data, string $action, bool $queueable = false): array
    {
        return Cache::lock('remotePayment_' . $transaction->internal_transaction_id)->get(function () use ($transaction, $data, $action, $queueable) {
            $client = new HttpClientService(
                new Curl()
            );

            try {
                $content = $client->makeRequest(
                    method: 'POST',
                    url: $this->getUri() . $this->getPath($action),
                    config: ['headers' => ['Content-Type' => 'application/json']],
                    options: ["body" => json_encode($data), 'timeout' => 60],
                    data: $data
                );
            } catch (Throwable $e) {
                if ($queueable) {
                    dispatch(new NotifyPlatform(transaction: $transaction, data: $data, action: $action))->onQueue($action);
                }

                throw new Error($e->getMessage(), 504);
            }

            $result = json_decode($content, true);
            if ($result['status'] !== 1) {
                if (!in_array($result['payload']['code'], [1702, 1014])) {
                    if ($queueable) {
                        dispatch(new NotifyPlatform(transaction: $transaction, data: $data, action: $action))->onQueue($action);
                    } else {
                        throw new Error('Unprocessable Entity', 422);
                    }
                }
            }

            return $result;
        });
    }

    private function setIsNotified(Transaction $transaction): void
    {
        $transaction->is_notified = true;
    }

    private function setPartnerId(array $result, Transaction $transaction): void
    {
        $transaction->partner_transaction_id = $result['payload']['transaction_id'];
    }
}
