<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;

use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use App\Services\PaymentService\PaymentInterfaces\DepositInterface;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;

/**
 * Class SkyCrypto
 *
 * @package App\Services\Payment\Payments
 */
class SkyCrypto extends PaymentAbstract implements DepositInterface
{

    const SKY_CRYPTO_STATUSES = [
        TransactionStatus::PENDING,
        TransactionStatus::PENDING,
        TransactionStatus::APPROVED,
        TransactionStatus::FAILED,
    ];

    protected bool $hasCallback = false;

    /**
     * Handle sky-crypto deposit request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @param false $brokerId
     * @return array
     * @throws PaymentProviderException
     */
    public function doDeposit(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $data = [];
        $data["amount"] = $body['amount'];
        $data["label"] = $configs['token'];
        $data["symbol"] = "usdt";
        $data["is_currency_amount"] = true;
        $data["currency"] = strtolower($body['currency']);

        if (!empty($body['settings']["broker_id"])) {
            $data["broker_id"] = $body['settings']["broker_id"];
        }

        // Save request data to transaction.
        $transaction->request_data = json_encode($data);

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $configs['deposit']['url'], config: [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Token ' . $configs['token'],
            ],
        ], options: ["body" => json_encode($data)], data: $data);

        $result = json_decode($content, true);

        if (array_key_exists('error', $result) && $result['error']) {
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from payment provider.', paymentErrorMessage: $result['message'], request: $data, response: $result);
        }

        // Set transaction external id and response data.
        $transaction->status = self::SKY_CRYPTO_STATUSES[intval($result['status'])];
        $transaction->response_data = $result;
        $transaction->external_transaction_id = $result['payment_id'];
        $transaction->wallet_id = $result['label'];

        $transaction->save();

        return [
            'redirect_to' => $result['web_link']
        ];
    }

    public function checkTransactionStatus(Transaction $transaction)
    {
        $configs = $this->getConfigs();

        $paymentId = json_decode($transaction->response_data)->payment_id;

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'GET', url: $configs['deposit']['url'] . '/' . $paymentId, config: [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Token ' . $configs['token'],
            ],
        ], options: ["body" => json_encode([])], data: []);

        $result = json_decode($content, true);

        if (array_key_exists('error', $result) && $result['error']) {
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from payment provider.', paymentErrorMessage: $result['message'], request: [], response: $result);
        }

        $newStatus = self::SKY_CRYPTO_STATUSES[intval($result['status'])];
        if ($newStatus != $transaction->status) {
            $transaction->status = $newStatus;
        }

        $transaction->save();
    }

    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
        return [];
    }
}
