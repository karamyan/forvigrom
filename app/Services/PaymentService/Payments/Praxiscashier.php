<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;


use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\PaymentInterfaces\DepositInterface;
use App\Services\PaymentService\PaymentInterfaces\WithdrawInterface;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;

class Praxiscashier extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    public function doDeposit(array $body, Transaction $transaction): array
    {
        $payment = $this->getPayment();
        $partner = $this->getPartner();
        $configs = $this->getConfigs();

        $notificationUrl = url('') . "/api/v1/payments/transactions/depositCallback?payment_id=$payment->partner_payment_id&partner_id=$partner->external_partner_id";
        $returnUrl = $partner->return_url;

        $time = time();
        $clientId = $body['client_id'];
        $data = [
            'merchant_id' => $configs['merchant_id'],
            'application_key' => $configs['application_key'],
            'timestamp' => $time,
            'intent' => 'payment',
            'cid' => "$clientId",
            'order_id' => "$transaction->internal_transaction_id"
        ];

        $signature = $this->getSignature($data, $configs['secret_key']);

        $data['amount'] = $this->getAmount();
        $data['currency'] = $this->getCurrency();
        $data['notification_url'] = $notificationUrl;
        $data['return_url'] = $returnUrl;
        $data['gateway'] = $body['settings']['gateway'] ?? null;

        if (!empty($body['settings']['payment_method'])) {
            $data['payment_method'] = $body['settings']['payment_method'];
        }

        $data['locale'] = 'en-GB';
        $data['version'] = '1.3';

        $json = json_encode($data);
        $transaction->request_data = $json;

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $configs['url'] . '/cashier/cashier', config: [
            'headers' => [
                'Content-Type' => 'application/json',
                'GT-Authentication' => $signature,
                'charset' => 'utf-8',
            ]
        ], options: [
            "body" => $json
        ], data: $data);

        $result = json_decode($content, true);

        // Set transaction external id and response data.
        $transaction->status = TransactionStatus::PENDING;
        $transaction->response_data = $content;

        return [
            'redirect_to' => $result['redirect_url']
        ];
    }

    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
        $result = $this->checkTransactionStatus(transaction: $transaction);

        $this->changeDepositStatus(result: $result, transaction: $transaction);

        return $result;
    }

    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $payment = $this->getPayment();
        $partner = $this->getPartner();
        $configs = $this->getConfigs();

//        $notificationUrl = url('') . "/api/v1/payments/transactions/withdrawCallback?payment_id=$payment->partner_payment_id&partner_id=$partner->external_partner_id";
        $notificationUrl = "https://payment.smartbet.live/api/v1/payments/transactions/withdrawCallback?payment_id=$payment->partner_payment_id&partner_id=$partner->external_partner_id";
        $returnUrl = $partner->return_url;

        if ($transaction->status === TransactionStatus::NEW) {
            $time = time();
            $clientId = $body['client_id'];
            $data = [
                'merchant_id' => $configs['merchant_id'],
                'application_key' => $configs['application_key'],
                'timestamp' => $time,
                'intent' => 'withdrawal',
                'cid' => "$clientId",
                'order_id' => "$transaction->internal_transaction_id"
            ];

            $signature = $this->getSignature($data, $configs['secret_key']);

            $data['amount'] = $this->getAmount();
            $data['currency'] = $this->getCurrency();
            $data['notification_url'] = $notificationUrl;
            $data['return_url'] = $returnUrl;
            $data['gateway'] = $body['settings']['gateway'] ?? null;

            if (!empty($body['settings']['payment_method'])) {
                $data['payment_method'] = $body['settings']['payment_method'];
            }

            $data['locale'] = 'en-GB';
            $data['version'] = '1.3';

            $json = json_encode($data);
            $transaction->request_data = $json;

            $client = new HttpClientService(
                new Curl()
            );
            $content = $client->makeRequest(method: 'POST', url: $configs['url'] . '/cashier/cashier', config: [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'GT-Authentication' => $signature,
                    'charset' => 'utf-8',
                ]
            ], options: [
                "body" => $json
            ], data: $data);

            $result = json_decode($content, true);

            $transaction->response_data = $content;
            $transaction->save();

            return [
                'redirect_to' => $result['redirect_url']
            ];
        } elseif (in_array($transaction->status, [TransactionStatus::PENDING, TransactionStatus::CREATED])) {
            $time = time();
            $data = [
                'merchant_id' => $configs['merchant_id'],
                'application_key' => $configs['application_key'],
                'timestamp' => $time,
                'intent' => 'complete-withdrawal-request',
                'withdrawal_request_id' => "$transaction->external_transaction_id"
            ];

            $signature = $this->getSignature($data, $configs['secret_key']);

            $data['gateway'] = "$transaction->wallet_id";
            $data['version'] = '1.3';

            $json = json_encode($data);

            $client = new HttpClientService(
                new Curl()
            );
            $client->makeRequest(method: 'POST', url: $configs['url'] . '/agent/manage-withdrawal-request', config: [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'GT-Authentication' => $signature,
                    'charset' => 'utf-8',
                ]
            ], options: [
                "body" => $json
            ], data: $data);

            $result = $this->checkTransactionStatus($transaction);

            $this->changeWithdrawStatus(result: $result, transaction: $transaction);

            return $result;
        }
    }

    public function doWithdrawCallback(array $body, Transaction $transaction)
    {
        $result = $this->checkTransactionStatus(transaction: $transaction);

        $this->changeWithdrawStatus(result: $result, transaction: $transaction);

        return $result;
    }

    /**
     * Get transaction id from request body.
     *
     * @param array $body
     * @return array
     */
    public function getTransactionId(array $body): array
    {
        return [
            "internal_transaction_id" => $body['session']['order_id'],
        ];
    }

    public function checkTransactionStatus(Transaction $transaction)
    {
        $configs = $this->getConfigs();

        $data = [
            'merchant_id' => $configs['merchant_id'],
            'application_key' => $configs['application_key'],
            'timestamp' => time(),
            'order_id' => $transaction->internal_transaction_id
        ];

        $signature = $this->getSignature($data, $configs['secret_key']);

        $data['version'] = '1.3';

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $configs['url'] . '/agent/find-order', config: [
            'headers' => [
                'Content-Type' => 'application/json',
                'GT-Authentication' => $signature,
                'charset' => 'utf-8',
            ]
        ], options: [
            "body" => json_encode($data)
        ], data: $data);

        $transaction->response_data = $content;

        $result = json_decode($content, true);

        if (app()->runningInConsole()) {
            if ($transaction->type === 'deposit') {
                $this->changeDepositStatus(result: $result, transaction: $transaction);
            } else {
                $this->changeWithdrawStatus(result: $result, transaction: $transaction);
            }
        }

        return $result;
    }

    private function getSignature(array $data, string $secret): string
    {
        return hash('sha384', implode($data) . $secret);
    }

    /**
     * @param string $amount
     */
    public function setAmount($amount): void
    {
        parent::setAmount($amount * 100);
    }

    private function changeDepositStatus(array $result, Transaction $transaction): void
    {
        if (array_key_exists('transaction', $result)) {
            if (array_key_exists('transaction_status', $result['transaction'])) {
                $transaction->external_transaction_id = $result['transaction']['tid'];

                if ($result['transaction']['transaction_status'] === "approved") {
                    $transaction->status = TransactionStatus::APPROVED;
                } elseif (in_array($result['transaction']['transaction_status'], ['rejected', 'cancelled', 'error'])) {
                    $transaction->status = TransactionStatus::FAILED;
                    $transaction->error_data = json_encode($result['transaction']['status_details']);
                }
            }
        }

        $transaction->save();
    }

    private function changeWithdrawStatus(array $result, Transaction $transaction): void
    {
        if (array_key_exists('transaction', $result)) {
            if (array_key_exists('transaction_status', $result['transaction'])) {
                $transaction->external_transaction_id = $result['transaction']['tid'];

                if ($result['transaction']['transaction_status'] === "approved") {
                    $transaction->status = TransactionStatus::APPROVED;
                } elseif (in_array($result['transaction']['transaction_status'], ['cancelled', 'error'])) {
                    $transaction->status = TransactionStatus::FAILED;
                    $transaction->error_data = json_encode($result['transaction']['status_details']);
                } elseif (in_array($result['transaction']['transaction_status'], ['initialized', 'requested', 'split_partial', 'split'])) {
                    $transaction->status = TransactionStatus::CREATED;
                }

                if (array_key_exists('gateway', $result['transaction'])) {
                    $transaction->wallet_id = $result['transaction']['gateway'];
                }
            }
        }

        $transaction->save();
    }
}
