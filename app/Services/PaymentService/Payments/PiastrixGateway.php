<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;


use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\PaymentInterfaces\DepositInterface;
use App\Services\PaymentService\PaymentInterfaces\WithdrawInterface;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PiastrixGateway extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    public const PIASTRIX_DEPOSIT_STATUSES = [
        'Created' => 1,
        'Waiting' => 2,
        'PsCreatingError' => 3,
        'Success' => 4,
        'CallbackError' => 5,
        'PsRejected' => 6,
        'Refunded' => 7,
        'Hold' => 8,
        'PtxRefunded' => 9,
        'Captured' => 10
    ];

    public const PIASTRIX_WITHDRAW_STATUSES = [
        'Created' => 1,
        'WaitingManualConfirmation' => 2,
        'PsProcessing' => 3,
        'PsProcessingError' => 4,
        'Success' => 5,
        'Rejected' => 6,
        'ManualConfirmed' => 7,
        'PsNetworkError' => 9,
        'CanceledManually' => 10,
        'Refunded' => 11
    ];

    public function doDeposit(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $data = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyCode(),
            'payway' => 'piastrix_rub', //$body['settings']['payway'],
            'shop_id' => $configs['shop_id'],
            'shop_order_id' => $body['internal_transaction_id']
        ];

        ksort($data);

        $sign = hash('sha256', trim(implode(':', $data), ':').$configs['secret']);
        $data['sign'] = $sign;

        if ($body['description']) {
            $data['description'] = $body['description'];
        }

        // Do withdraw pre-check request.
        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: 'https://core.piastrix.com/invoice/create',
            config: [
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false
            ], options: [ "body" => json_encode($data)], data: [$data]);

        $result = json_decode($content, true);

        Log::info('piastrix ', [$result]);
        if ($result['error_code'] !== 0 || $result['message'] !== 'Ok') {
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from payment provider.', paymentErrorMessage: $result['message'], request: $data, response: $result);
        }

        // Set transaction external id and response data.
        $transaction->status = TransactionStatus::PENDING;
        $transaction->response_data = json_encode($result);
        $transaction->external_transaction_id = $result['data']['id'];

        return [
            'redirect_to' => $result['data']['url']
        ];
    }

    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
    }

    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $data = [
            'account' => $body['wallet_id'],
            'amount' => '1',
            'amount_type' => "ps_amount",
            'payway' => $body['settings']['payway'],
            'shop_currency' => $this->getCurrencyCode(),
            'shop_id' => $configs['shop_id'],
            'shop_payment_id' => $body['internal_transaction_id']
        ];

        ksort($data);
        $sign = hash('sha256', trim(implode(':', $data), ':').$configs['secret']);
        $data['sign'] = $sign;

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: 'https://core.piastrix.com/withdraw/create',
            config: [
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false
            ], options: [ "body" => json_encode($data)], data: [$data]);

        $result = json_decode($content, true);

        if($result['data']['status'] === self::PIASTRIX_WITHDRAW_STATUSES['Success']) {
            $transaction->status = TransactionStatus::APPROVED;
        } elseif (
            $result['data']['status'] === self::PIASTRIX_DEPOSIT_STATUSES['PsRejected'] ||
            $result['data']['status'] === self::PIASTRIX_DEPOSIT_STATUSES['CanceledManually'] ||
            $result['data']['status'] === self::PIASTRIX_DEPOSIT_STATUSES['Refunded']
        ) {
            $transaction->status = TransactionStatus::FAILED;
        } else {
            $transaction->status = TransactionStatus::PENDING;
        }
        $transaction->save();

        return $result;
    }

    public function checkTransactionStatus(Transaction $transaction)
    {
        $configs = $this->getConfigs();

        $data = [
            'now' => Carbon::now()->toDateTimeString(),
            'shop_id' => $configs['shop_id'],
            'shop_order_id' => strval($transaction->internal_transaction_id),
        ];

        ksort($data);
        $sign = hash('sha256', trim(implode(':', $data), ':').$configs['secret']);
        $data['sign'] = $sign;

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url:  'https://core.piastrix.com/invoice/check',
            config: [
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false
            ], options: [ "body" => json_encode($data)], data: [$data]);

        $transaction->response_data = $content;

        $result = json_decode($content, true);

        if($result['data']['status'] === self::PIASTRIX_DEPOSIT_STATUSES['Success']) {
            $transaction->status = TransactionStatus::APPROVED;
        } elseif (
            $result['data']['status'] === self::PIASTRIX_DEPOSIT_STATUSES['Rejected'] ||
            $result['data']['status'] === self::PIASTRIX_DEPOSIT_STATUSES['Refunded'] ||
            $result['data']['status'] === self::PIASTRIX_DEPOSIT_STATUSES['PtxRefunded']
        ) {
            $transaction->status = TransactionStatus::FAILED;
        } else {
            $transaction->status = TransactionStatus::PENDING;
        }

        return $result;
    }

    public function checkWithdrawTransactionStatus(Transaction $transaction) {

    }
}