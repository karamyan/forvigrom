<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;

use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\Exceptions\FieldValidationException;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\PaymentInterfaces\DepositInterface;
use App\Services\PaymentService\PaymentInterfaces\WithdrawInterface;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;


/**
 * Class Freekassa.
 *
 * @package App\Services\PaymentService\Payments
 */
class Freekassa extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    const FREEKASSA_STATUSES = [
        0 => TransactionStatus::PENDING,
        1 => TransactionStatus::APPROVED,
        8 => TransactionStatus::FAILED,
        9 => TransactionStatus::FAILED
    ];

    protected bool $hasCallback = false;

    /**
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws PaymentProviderException
     */
    public function doDeposit(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();
        $payment = $this->getPayment();
        $partner = $this->getPartner();

        $data = [
            'shopId' => $configs['shopId'],
            'nonce' => $this->getNonce(),
            'paymentId' => $transaction->internal_transaction_id,
            'i' => $body['settings']['payment_system'],
            'email' => $body['client_id'] . '@gmail.com',
            'ip' => $_SERVER['HTTP_X_REAL_IP'] ?? request()->ip(),
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrency(),
            'success_url' => route('post_handle_in_success', [$partner->external_partner_id, $payment->partner_payment_id]),
            'failure_url' => route('post_handle_in_fail', [$partner->external_partner_id, $payment->partner_payment_id]),
            'notification_url' => route('post_deposit_callback_url', [$partner->external_partner_id, $payment->partner_payment_id])
        ];

        $signature = $this->getSignature($data);
        $data['signature'] = $signature;

        $transaction->request_data = json_encode($data);
        $transaction->save();

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $configs['url'] . '/orders/create', config: [
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ], options: [
            "body" => json_encode($data)
        ], data: $data);

        $result = json_decode($content, true);

        if ($result['type'] !== 'success') {
            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                paymentErrorMessage: $result['message'],
                request: $data,
                response: $result
            );
        }

        $transaction->external_transaction_id = $result['orderId'];
        $transaction->response_data = $content;
        $transaction->status = TransactionStatus::PENDING;

        $result['redirect_to'] = $result['location'];
        $transaction->wallet_id = $configs['api_key'];

        $transaction->save();

        return $result;
    }

    /**
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     * @throws FieldValidationException
     */
    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
        $this->validateSignature($body);

        $transaction->callback_response_data = json_encode($body);
        $transaction->status = TransactionStatus::APPROVED;

        $transaction->save();

        return $body;
    }

    /**
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws PaymentProviderException
     */
    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $data = [
            'wallet_id' => 'F111092153',
            'purse' => $body['wallet_id'],
            'amount' => $this->getAmount(),
            'desc' => $transaction->internal_transaction_id,
            'currency' => $body['settings']['payment_system'],
            'order_id' => $transaction->internal_transaction_id,
            'action' => 'cashout'
        ];

        $signature = md5('F111092153' . $body['settings']['payment_system'] . $this->getAmount() . $body['wallet_id'] . $configs['withdraw']['api_key']);
        $data['sign'] = $signature;

        $client = new HttpClientService(
            new Curl()
        );

        $transaction->status = TransactionStatus::PENDING;
        $transaction->request_data = json_encode($data);
        $transaction->save();

        $content = $client->makeRequest(method: 'POST', url: $configs['withdraw']['url'] . '/api_v1.php', config: [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
        ], options: ["form_params" => $data], data: $data);

        $result = json_decode($content, true);

        $transaction->response_data = $content;
        $transaction->save();

        if ($result['status'] == 'error') {
            $transaction->status = TransactionStatus::FAILED;
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                paymentErrorMessage: '',
                request: $data,
                response: $result
            );
        }

        $transaction->external_transaction_id = $result['data']['payment_id'] ?? null;
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
            "internal_transaction_id" => $body['MERCHANT_ORDER_ID'],
        ];
    }

    /**
     * @param array $data
     * @return string
     */
    private function getSignature(array $data): string
    {
        $configs = $this->getConfigs();

        ksort($data);
        return hash_hmac('sha256', implode('|', $data), $configs['api_key']);
    }

    /**
     * @param array $data
     * @throws FieldValidationException
     */
    private function validateSignature(array $data): void
    {
        $configs = $this->getConfigs();

        $sign = md5($data['MERCHANT_ID'] . ':' . $data['AMOUNT'] . ':' . $configs['secret_2'] . ':' . $data['MERCHANT_ORDER_ID']);

        if ($sign !== $data['SIGN']) {
            throw new FieldValidationException('The given data was invalid.', 400, [
                'The Checksum is invalid.'
            ]);
        }
    }

    /**
     * @param Transaction $transaction
     */
    public function checkTransactionStatus(Transaction $transaction)
    {
        if ($transaction->type === 'withdraw') {
            $this->checkWithdrawTransactionStatus($transaction);
        } else {
            $this->checkDepositTransactionStatus($transaction);
        }
    }

    private function checkDepositTransactionStatus(Transaction $transaction)
    {
    }

    private function checkWithdrawTransactionStatus(Transaction $transaction)
    {
        $configs = $this->getConfigs();

        $data = [
            'wallet_id' => 'F111092153',
            'payment_id' => $transaction->external_transaction_id,
            'action' => 'get_payment_status',
        ];

        $signature = md5('F111092153' . $transaction->external_transaction_id . $configs['withdraw']['api_key']);
        $data['sign'] = $signature;

        $client = new HttpClientService(
            new Curl()
        );

        $requestData = $data;
        $transaction->request_data = json_encode($requestData);
        $transaction->save();

        $content = $client->makeRequest(method: 'POST', url: $configs['withdraw']['url'] . '/api_v1.php', config: [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
        ], options: ["form_params" => $data], data: $data);

        $result = json_decode($content, true);

        $responseData = $data;
        $transaction->response_data = json_encode($responseData);
        $transaction->save();

        if (array_key_exists('status', $result)) {
            if ($result['status'] === 'info') {
                if (array_key_exists('data', $result)) {
                    if (array_key_exists('status', $result['data'])) {
                        if ($result['data']['status'] === 'Completed') {
                            $transaction->status = TransactionStatus::APPROVED;
                        } elseif ($result['data']['status'] === 'New' || $result['data']['status'] === 'In process') {
                            $transaction->status = TransactionStatus::PENDING;
                        } elseif ($result['data']['status'] === 'Canceled') {
                            $transaction->status = TransactionStatus::CANCELED;
                        }

                        $transaction->save();
                    }
                }
            }
        }
    }

    /**
     * @return int
     */
    private function getNonce(): int
    {
        $milliseconds = floor(microtime(true) * 1000);
        $nonce = strval($milliseconds) . strval(rand(10000, 99999));
        return intval($nonce);
    }

    public function handleSuccess(array $body)
    {
        $amount = Transaction::query()->where('internal_transaction_id', $body['MERCHANT_ORDER_ID'])->pluck('amount')->first();
        $paymentId = $this->getPayment()->partner_payment_id;
        $partnerHost = $this->getPartner()->notify_url;

        return redirect($partnerHost . '/user/wallet/deposit?success=1&paymentId=' . $paymentId . '&amount=' . $amount);
    }

    public function handleFail(array $body)
    {
        $paymentId = $this->getPayment()->partner_payment_id;
        $partnerHost = $this->getPartner()->notify_url;

        return redirect($partnerHost . '/user/wallet/deposit?success=0&paymentId=' . $paymentId);
    }
}
