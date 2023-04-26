<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;


use App\Services\HttpCaller\MakesHttpRequest;
use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\PaymentInterfaces\DepositInterface;
use App\Services\PaymentService\PaymentInterfaces\WithdrawInterface;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;

class Paymetrust extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    /**
     * Available currency codes.
     *
     * @var array|string[]
     */
    private array $availableCurrencyCodes = [
        'XOF' => '952'
    ];

    public function doDeposit(array $body, Transaction $transaction): array
    {
        $config = $this->getConfigs();
        $payment = $this->getPayment();
        $partner = $this->getPartner();

        $accessToken = $this->getAccessToken();

        $data = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrency(),
            "merchant_transaction_id" => sprintf("%s", $body['internal_transaction_id']),
            "success_url" => 'https://321pariez.com',
            "failed_url" => 'https://321pariez.com',
            "notify_url" => url('') . '/api/v1/payments/transactions/depositCallback?payment_id=' . $payment->partner_payment_id . '&partner_id=' . $partner->external_partner_id,
            "lang" => 'fr',
            "designation" => $body['description'],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'AUTHORIZATION' => $accessToken
        ];

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $config['url'] . '/v1/payment', config: ['headers' => $headers], options: [
            "body" => json_encode($data)
        ], data: $data);

        $response = json_decode($content, true);

        if ($response['code'] !== 200 || $response['status'] !== "OK") {
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from ' . $this->getPayment()->display_name . ' payment provider.', paymentErrorMessage: $response['description'], request: $data, response: $result);
        }

        $transaction->response_data = json_encode($response);

        return [
            'redirect_to' => $response['payment_url']
        ];
    }

    /**
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     * @throws PaymentProviderException
     */
    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
        return $this->checkTransactionStatus($transaction);
    }

    /**
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws PaymentProviderException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $config = $this->getConfigs();

        $accessToken = $this->getAccessToken();

        $data = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrency(),
            "merchant_transaction_id" => sprintf("%s", $body['internal_transaction_id']),
            "phone_number" => $body['wallet_id'],
            "reason" => $body['description']
        ];

        $headers = [
            'headers' => [
                'Content-Type' => 'application/json',
                'AUTHORIZATION' => $accessToken
            ]
        ];

        $options = ["body" => json_encode($data)];

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $config['url'] . '/v1/transfer', config: $headers, options: $options, data: $data);

        $result = json_decode($content, true);

        if ($result['code'] !== 100 || $result['status'] !== "SUCCESS") {
            $transaction->status = TransactionStatus::FAILED;
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from ' . $this->getPayment()->display_name . ' payment provider.', paymentErrorMessage: $result['description'], request: $data, response: $result);
        }

        $transaction->status = TransactionStatus::APPROVED;
        $transaction->response_data = $content;
        $transaction->external_transaction_id = $result['transaction_id'];
        $transaction->save();

        return $result;
    }

    /**
     * @param array $body
     * @return array
     */
    public function getTransactionId(array $body): array
    {
        return [
            "response_data->notify_token" => $body['notify_token'],
        ];
    }

    /**
     * @param Transaction $transaction
     * @return array
     * @throws PaymentProviderException
     */
    public function checkTransactionStatus(Transaction $transaction): array
    {
        $responseData = json_decode($transaction->response_data, true);

        $config = $this->getConfigs();

        $accessToken = $this->getAccessToken();

        $headers = [
            'Content-Type' => 'application/json',
            'AUTHORIZATION' => $accessToken
        ];

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'GET', url: $config['url'] . '/v1/payment/' . $responseData['payment_token'], config: ['headers' => $headers]);

        $result = json_decode($content, true);

        $transaction->response_data = $content;
        $transaction->external_transaction_id = $result['transaction_id'];

        if ($result['status'] == "SUCCESS") {
            $transaction->status = TransactionStatus::APPROVED;
        } else if ($result['status'] == 'PENDING') {
            $transaction->status = TransactionStatus::PENDING;
        } else {
            $transaction->status = TransactionStatus::FAILED;
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from ' . $this->getPayment()->display_name . ' payment provider.', paymentErrorMessage: $result['description'], request: $data, response: $result);
        }

        return $result;
    }

    /**
     * @return string
     */
    private function getAccessToken(): string
    {
        $config = $this->getConfigs();

        $data = [
            'api_key' => $config['api_key'],
            'api_password' => $config['api_password']
        ];

        $headers = ['headers' => ['Content-Type' => 'application/json']];
        $options = ["body" => json_encode($data)];


        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $config['url'] . '/v1/oauth/login', config: $headers, options: $options, data: $data);

        $result = json_decode($content, true);

        return 'Bearer ' . $result['access_token'];
    }

//    /**
//     * @param array $body
//     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
//     */
//    public function handleSuccess(array $body)
//    {
////        $amount = Transaction::query()->where('internal_transaction_id', $body['EDP_BILL_NO'])->pluck('amount')->first();
//
//        return redirect('https://321pariez.com/user/wallet/deposit?success=1&paymentId='.$body['payment_id'].'&amount='.$body['amount']);
//    }
//
//    /**
//     * @param array $body
//     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
//     */
//    public function handleFail(array $body)
//    {
//        return redirect('https://321pariez.com/user/wallet/deposit?success=0&paymentId=22');
//    }
}
