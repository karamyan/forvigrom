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
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Str;
use SimpleXMLElement;

class BlackRabbit extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    protected bool $hasCallback = false;

    private const TRANSACTION_STATUS_MAPS = [
        'FILLED' => TransactionStatus::PROCESSING,
        'PAID' => TransactionStatus::PROCESSING,
        'EXECUTED' => TransactionStatus::APPROVED,
        'REJECT' => TransactionStatus::FAILED,
        'CANCEL' => TransactionStatus::FAILED,
    ];

    public function doDeposit(array $body, Transaction $transaction): array
    {
        $config = $this->getConfigs();
        $payment = $this->getPayment();
        $partner = $this->getPartner();

        $lang = 'en';
        if (!empty($body['lang'])) {
            if ($body['lang'] == 'ru') {
                $lang = 'ru';
            }
        }

        $data = [
            'sp_outlet_id' => $config['sp_outlet_id'],
            'sp_amount' => $this->getAmount(),
            'sp_payment_system' => $config['sp_payment_system'],
            'sp_user_name' => 'test',
            'sp_user_phone' => '79111111111',
            'sp_user_contact_email' => $body['client_id'] . '@gmail.com',
            'sp_currency' => 'RUB',
            'sp_order_id' => $transaction->partner_transaction_id,
            'sp_salt' => Str::random(),
            'sp_can_reject' => '1',
            'sp_language' => $lang,
            'sp_description' => $body['description'] ?? $partner->display_name . ' deposit',
            'sp_success_url' => route('post_handle_in_success', [$partner->external_partner_id, $payment->partner_payment_id]),
            'sp_failure_url' => route('post_handle_in_fail', [$partner->external_partner_id, $payment->partner_payment_id]),
            'sp_result_url' => route('post_deposit_callback_url', [$partner->external_partner_id, $payment->partner_payment_id]),
        ];

        $sig = $this->createSig('init_payment', $data, $config['secret']);
        $data['sp_sig'] = $sig;

        $transaction->request_data = json_encode($data);
        $transaction->save();

        $client = new HttpClientService(
            new Curl()
        );

        $content = $client->makeRequest(method: 'GET', url: $config['url'] . '/init_payment?' . http_build_query($data), config: [
            'headers' => [
                'Content-Type' => 'application/json']
        ], options: [
            'timeout' => 15
        ], data: $data);

        $transaction->response_data = json_encode($content);
        $transaction->save();

        $content = str_replace('&','&amp;', $content);

        $result = json_decode(json_encode(simplexml_load_string($content)), true);

        if ($result['sp_status'] !== 'ok') {
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from:' . $payment->display_name . ' payment provider.', paymentErrorMessage: $result['sp_error_description'], request: $data, response: $result);
        }

        if ($result['sp_redirect_url_type'] === 'need data') {
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from:' . $payment->display_name . ' payment provider.', paymentErrorMessage: $result['sp_redirect_url_type'], request: $data, response: $result);
        }

        $transaction->external_transaction_id = $result['sp_payment_id'];
        $transaction->save();

        return [
            'redirect_to' => $result['sp_redirect_url']
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
        $payment = $this->getPayment();
        $transaction->callback_response_data = $body;
        $transaction->save();

        if (!$this->isValidSig(path: 'depositCallback', data: $body, secret: $this->getConfigs()['secret_result'], sig: $body['sp_sig'])) {
            $message = 'Invalid sig from:' . $payment->display_name . ' payment provider.';
            return response($this->getXmlResponse(status: 'error', message: $message), 200)->header('Content-Type', 'application/xml');
        }

        if ($transaction->amount !== $body['sp_amount']) {
            $message = 'Different amount field from:' . $payment->display_name . ' in callback.';
            return response($this->getXmlResponse(status: 'error', message: $message), 200)->header('Content-Type', 'application/xml');
        }

        $result = $this->checkDepositTransactionStatus($transaction);

        if (!$this->isValidSig(path: 'get_status', data: $result, secret: $this->getConfigs()['secret'], sig: $result['sp_sig'])) {
            $payment = $this->getPayment();
            $message = 'Invalid sig from:' . $payment->display_name . ' payment provider.';

            return response($this->getXmlResponse(status: 'error', message: $message), 200)->header('Content-Type', 'application/xml');
        }

        if ($result['sp_status'] !== 'ok') {
            $message = 'Different amount field from:' . $payment->display_name . ' in callback.';
            return response($this->getXmlResponse(status: 'error', message: $message), 200)->header('Content-Type', 'application/xml');
        }

        $transaction->status = self::TRANSACTION_STATUS_MAPS[$result['sp_transaction_status']];
        $transaction->save();

        return response($this->getXmlResponse(status: 'ok', message: null), 200)->header('Content-Type', 'application/xml');
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

        $data = [
            'sp_outlet_id' => $config['sp_outlet_id'],
            'sp_amount' => $this->getAmount(),
            'sp_destination_card' => $body['wallet_id'],
            'sp_order_id' => $transaction->partner_transaction_id,
            'sp_salt' => Str::random(),
        ];
        $sig = $this->createSig('transfer_to_card_rus', $data, $config['secret']);
        $data['sp_sig'] = $sig;

        $transaction->request_data = json_encode($data);
        $transaction->status = TransactionStatus::PROCESSING;
        $transaction->save();

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $config['url'] . '/transfer_to_card_rus', config: [
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ], options: [
            'form_params' => $data,
            'timeout' => 60
        ], data: $data);

        $transaction->response_data = json_encode($content);
        $transaction->save();

        $result = json_decode(json_encode(simplexml_load_string($content)), true);


        if (!$this->isValidSig(path: 'transfer_to_card_rus', data: $result, secret: $this->getConfigs()['secret'], sig: $result['sp_sig'])) {
            $payment = $this->getPayment();
            $message = 'Invalid sig from:' . $payment->display_name . ' payment provider.';

            throw new PaymentProviderException(
                transaction: $transaction,
                message: $message,
                paymentErrorMessage: $message
            );
        }

        if ($result['sp_status'] !== 'ok') {
            $transaction->status = TransactionStatus::FAILED;

            $transaction->error_data = json_encode($result['sp_error_message']);
            $transaction->save();
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from:' . $this->getPayment()->display_name . ' payment provider.', paymentErrorMessage: $result['sp_error_message'], request: $data, response: $result);
        }

        $transaction->external_transaction_id = $result['sp_transaction_id'];
        $transaction->save();

        return $result;
    }

    /**
     * @param Transaction $transaction
     * @return array
     * @throws PaymentProviderException
     */
    public function checkTransactionStatus(Transaction $transaction): void
    {
        if ($transaction->type === 'withdraw') {
            $this->checkWithdrawTransactionStatus($transaction);
        }
    }

    private function checkDepositTransactionStatus(Transaction $transaction): array
    {
        $config = $this->getConfigs();

        $data = [
            'sp_outlet_id' => $config['sp_outlet_id'],
            'sp_order_id' => $transaction->partner_transaction_id,
            'sp_salt' => Str::random(),
        ];
        $sig = $this->createSig('get_status', $data, $config['secret']);
        $data['sp_sig'] = $sig;

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $config['url'] . '/get_status', config: [
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ], options: [
            'form_params' => $data,
            'timeout' => 30
        ], data: $data);

        return json_decode(json_encode(simplexml_load_string($content)), true);
    }

    private function checkWithdrawTransactionStatus(Transaction $transaction)
    {
        $config = $this->getConfigs();

        $data = [
            'sp_outlet_id' => $config['sp_outlet_id'],
            'sp_order_id' => $transaction->partner_transaction_id,
            'sp_payout_id' => $transaction->external_transaction_id,
            'sp_salt' => Str::random(),
        ];
        $sig = $this->createSig('get_payout_status', $data, $config['secret']);
        $data['sp_sig'] = $sig;

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $config['url'] . '/get_payout_status', config: [
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ], options: [
            'form_params' => $data,
            'timeout' => 60
        ], data: $data);

        $transaction->callback_response_data = json_encode($content);
        $transaction->save();

        $result = json_decode(json_encode(simplexml_load_string($content)), true);

        if (!$this->isValidSig(path: 'get_payout_status', data: $result, secret: $this->getConfigs()['secret'], sig: $result['sp_sig'])) {
            $payment = $this->getPayment();
            $message = 'Invalid sig from:' . $payment->display_name . ' payment provider.';
            throw new PaymentProviderException(
                transaction: $transaction,
                message: $message,
                paymentErrorMessage: $message
            );
        }

        if ($result['sp_status'] === 'ok') {
            $transaction->status = self::TRANSACTION_STATUS_MAPS[$result['sp_payout_status']];
            $transaction->save();

            return $result;
        }
    }

    /**
     * @param string $status
     * @param string|null $message
     * @return array
     */
    private function getXmlResponse(string $status, string|null $message): string
    {
        $response['response'] = [
            'sp_status' => $status,
            'sp_salt' => Str::random(),
        ];

        if (!is_null($message)) {
            $response['response']['sp_description'] = $message;
        }

        $response['response']['sp_sig'] = $this->createSig('init_payment', $response['response'], $this->getConfigs()['secret']);

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response></response>');

        foreach ($response['response'] as $key => $value) {
            $xml->addChild($key, $value);
        }

        return $xml->asXML();
    }

    /**
     * @param array $body
     * @return array
     */
    public function getTransactionId(array $body): array
    {
        return [
            "partner_transaction_id" => $body['sp_order_id'],
        ];
    }

    /**
     * @param string $path
     * @param array $data
     * @param string $secret
     * @return string
     */
    private function createSig(string $path, array $data, string $secret): string
    {
        ksort($data);

        $hashString = $path . ';' . implode(';', $data) . ';' . $secret;

        return md5($hashString);
    }

    private function isValidSig(string $path, array $data, string $secret, $sig): bool
    {
        unset($data['payment_request_id']);
        unset($data['sp_sig']);
        ksort($data);

        $hash = md5($path . ';' . $this->implodeRecursive(separator: ';', array: $data) . ';' . $secret);

        return $hash === $sig;
    }

    private function implodeRecursive(string $separator, array $array): string
    {
        $result = array();
        foreach ($array as $value) {
            if (is_array($value)) {
                sort($value);
                $result[] = $this->implodeRecursive($separator, $value);
            } else {
                $result[] = $value;
            }
        }
        return implode($separator, $result);
    }

    public function handleSuccess(array $body): RedirectResponse|Redirector
    {
        $amount = Transaction::query()->where('partner_transaction_id', $body['sp_order_id'])->pluck('amount')->first();

        return redirect($this->getPartner()->return_url . '/user/wallet/deposit?success=1&paymentId=' . request()->route('payment_id') . '&amount=' . $amount);
    }

    public function handleFail(array $body): RedirectResponse|Redirector
    {
        return redirect($this->getPartner()->return_url . '/user/wallet/deposit?success=0&paymentId=' . request()->route('payment_id'));
    }
}
