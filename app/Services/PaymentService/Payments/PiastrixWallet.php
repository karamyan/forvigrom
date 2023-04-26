<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;


use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\CurrencyCodes;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\PaymentInterfaces\DepositInterface;
use App\Services\PaymentService\PaymentInterfaces\WithdrawInterface;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Error;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PiastrixWallet extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    /**
     * @var string
     */
    private string $payerCurrency = 'RUB';

    /**
     * @var string
     */
    private string $payerCurrencyCode = '';

    protected bool $hasCallback = true;

    private static array $depositStatuses = [
        'Waiting' => 1,
        'Paid' => 2,
        'Canceled' => 3,
        'Expired' => 4,
    ];

    /**
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doDeposit(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $data = [
            'shop_amount' => $this->getAmount(),
            'shop_currency' => $this->getCurrencyCode(),
            'payer_currency' => $this->getCurrencyCode(),// $this->getPayerCurrencyCode(),
            'shop_id' => $configs['shop_id'],
            'shop_order_id' => $body['internal_transaction_id']
        ];

        $data['sign'] = $this->createSign($data, $configs['secret']);

        if ($body['description']) {
            $data['description'] = $body['description'];
        }

        $client = new Client([
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => false
        ]);

        $response = $client->request('POST', 'https://core.piastrix.com/bill/create', [
            'body' => json_encode($data)
        ]);

        $content = $response->getBody()->getContents();

        $result = json_decode($content, true);

        // Set transaction external id and response data.
        $transaction->status = TransactionStatus::PENDING;
        $transaction->response_data = $content;
        $transaction->external_transaction_id = $result['data']['id'];

        return [
            'redirect_to' => $result['data']['url']
        ];
    }

    /**
     * Process piastrix callback.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     */
    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
        Log::info('Piastrix wallet  sdfd------------', [$transaction]);
        return $this->checkTransactionStatus($transaction);
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
        $sign = hash('sha256', trim(implode(':', $data), ':') . $configs['secret']);
        $data['sign'] = $sign;

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: 'https://core.piastrix.com/bill/shop_order_status',
            config: [
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false
            ], options: ["body" => json_encode($data)], data: [$data]);

        $transaction->response_data = $content;
        $transaction->save();

        $result = json_decode($content, true);

        if (array_key_exists('data', $result)) {
            if (array_key_exists('status', $result['data'])) {
                if ($result['data']['status'] == self::$depositStatuses['Paid']) {
                    $transaction->status = TransactionStatus::APPROVED;
                } else if ($result['data']['status'] == self::$depositStatuses['Waiting']) {
                    $transaction->status = TransactionStatus::PENDING;
                } else if (
                    $result['data']['status'] == self::$depositStatuses['Canceled'] ||
                    $result['data']['status'] == self::$depositStatuses['Expired']
                ) {
                    $transaction->status = TransactionStatus::CANCELED;
                }

                $transaction->save();
            }
        }

        return 'OK';
    }

    /**
     * Piastrix do withdraw request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $data = [
            'amount' => $this->getAmount(),
            'amount_type' => "receive_amount",
            'payee_account' => $body['wallet_id'],
            'payee_currency' => $this->getCurrencyCode(), //$this->getPayerCurrencyCode(),
            'shop_currency' => $this->getCurrencyCode(),
            'shop_id' => $configs['shop_id'],
            'shop_payment_id' => $body['internal_transaction_id']
        ];

        $data['sign'] = $this->createSign($data, $configs['secret']);

        // Saving request data to transaction.
        $transaction->request_data = json_encode($data);

        $client = new Client([
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => false
        ]);

        $response = $client->request('POST', 'https://core.piastrix.com/transfer/create', [
            'body' => json_encode($data)
        ]);

        $content = $response->getBody()->getContents();

        // Save response data to transaction.
        $transaction->response_data = $content;

        $result = json_decode($content, true);

        if ($result['error_code'] == 0 && $result['message'] == 'Ok' && $result['result'] == true) {
            $transaction->external_transaction_id = $result['data']['id'];
            $transaction->status = TransactionStatus::APPROVED;

            return $result;
        }
        $transaction->save();

        throw new Error($result['message'], 502);
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
            "external_transaction_id" => $body['payment_id'],
        ];
    }

    /**
     * @param array $body
     * @throws \App\Services\PaymentService\Exceptions\FieldValidationException
     */
    public function validateDepositFields(array $body): void
    {
        parent::validateDepositFields($body);

        $this->setPayerCurrency($body['payer_currency'] ?? $this->payerCurrency);
    }

    /**
     * @param array $body
     * @throws \App\Services\PaymentService\Exceptions\FieldValidationException
     */
    public function validateWithdrawFields(array $body): void
    {
        parent::validateWithdrawFields($body);

        $this->setPayerCurrency($body['payer_currency'] ?? $this->payerCurrency);
    }

    /**
     * @return string
     */
    private function getPayerCurrencyCode(): string
    {
        return $this->payerCurrencyCode;
    }

    /**
     * @param $payerCurrency
     */
    private function setPayerCurrency($payerCurrency): void
    {
        $this->payerCurrency = $payerCurrency;

        $this->payerCurrencyCode = CurrencyCodes::AvailableCurrencyCodes[$payerCurrency];
    }

    /**
     * Verify sign token is valid.
     *
     * @param $data
     * @param $secret
     * @return bool
     */
    private function verifySign($data, $secret): bool
    {
        $sign = $data['sign'];
        unset($data['sign']);

        return $this->createSign($data, $secret) === $sign;
    }

    /**
     * Create sign hash string.
     *
     * @param $data
     * @param $secret
     * @return string
     */
    private function createSign($data, $secret): string
    {
        ksort($data);

        return hash('sha256', trim(implode(':', $data), ':') . $secret);
    }
}
