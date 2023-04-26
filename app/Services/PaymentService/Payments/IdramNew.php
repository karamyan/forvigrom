<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;

use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\Exceptions\FieldValidationException;
use App\Services\PaymentService\Exceptions\ForbiddenResponseException;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\PaymentInterfaces\DepositInterface;
use App\Services\PaymentService\PaymentInterfaces\WithdrawInterface;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Class Idram.
 *
 * @package App\Services\Payment\Payments
 */
class IdramNew extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    protected bool $hasCallback = false;

    /**
     * Idram wallet deposit.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws \Throwable
     */
    public function doDeposit(array $body, Transaction $transaction): array
    {
        // Validate payment deposit fields.
        $this->validateDepositFields($body);

        $configs = $this->getConfigs();

        $amount = floatval($body['amount']);

        $data = [
            'EDP_REC_ACCOUNT' => $configs['account_id'],
            'EDP_AMOUNT' => $amount,
            'EDP_BILL_NO' => $body['internal_transaction_id'],
            'EDP_DESCRIPTION' => $body['description'],
            'EDP_LANGUAGE' => $body['lang'],
        ];

        $transaction->request_data = json_encode($data);

        return [
            'redirect_to' => view('idram/form')->with('data', $data)->with('actionUrl', $configs['deposit_url'])->render()
        ];
    }

    /**
     * Idram callback.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     * @throws FieldValidationException
     */
    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
        $configs = $this->getConfigs();

        $data = [
            'field' => 'id',
            'site_id' => $this->getPartner()->external_partner_id,
            'value' => $transaction->client_id
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
            return [
                'ResponseCode' => 1,
                'ResponseMessage' => "Մուտքագրված հաշվեհամարը սխալ է։",
            ];
        }

        if ($body['EDP_AMOUNT'] != $transaction->amount) {
            $transaction->status = TransactionStatus::FAILED;
            $transaction->save();
            throw new ForbiddenResponseException('Invalid callback amount', 403);
        }

        if (isset($body['EDP_PRECHECK'])) {
            if ($body['EDP_PRECHECK'] == "YES") {
                if (isset($body['EDP_REC_ACCOUNT'])) {
                    if ($body['EDP_REC_ACCOUNT'] == $configs['account_id']) {
                        $transaction->status = TransactionStatus::PENDING;
                        $transaction->response_data = json_encode($body);
                        $transaction->save();

                        return response()
                            ->json(json_decode('{"ErrorCode" : 0,"ErrorDescription" : "SUCCESS","Params" :{"DocType" : 8,"DocNumber" :' . $platformResponse['payload']['social_number'] . '}}', true))
                            ->header('Content-Type', 'application/json')
                            ->header('X-API-Version', '2.0');
                    }
                }
            }
        }

        // Validate deposit callback fields.
        $this->validateDepositCallbackFields($body);

        if (!$this->isValidChecksum($body, $configs)) {
            throw new ForbiddenResponseException('Invalid checksum', 403);
        }

        $transaction->external_transaction_id = $body['EDP_TRANS_ID'];
        $transaction->response_data = json_encode($body);
        $transaction->status = TransactionStatus::APPROVED;

        if ($body['EDP_AMOUNT'] != $transaction->amount) {
            $transaction->status = TransactionStatus::FAILED;
            $transaction->save();
            throw new ForbiddenResponseException('Invalid callback amount', 403);
        }

        return response()->json(json_decode('{"ErrorCode" : 0,"ErrorDescription" : "SUCCESS"}', true))
            ->header('Content-Type', 'application/json')
            ->header('X-API-Version', '2.0');
    }

    /**
     * Handling idram withdraw request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws Exception
     */
    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();
        $amount = number_format($body['amount'], 2, '.', '');

        $token = $this->getToken(configs: $configs, transaction: $transaction);

        $client = new HttpClientService(
            new Curl()
        );

        $data = [
            'destinationAccount' => $body['wallet_id'],
            'amount' => $amount,
            'docType' => 8,
            'docNumber' => $body['user_info']['social_number'],
            'refId' => $transaction->internal_transaction_id,
        ];

        $validationUrl = $configs['withdraw_url'] . '/wallet/withdrawals/validation';
        $withdrawUrl = $configs['withdraw_url'] . '/wallet/withdrawals';

        try {
            $this->withdrawRequest(client: $client, url: $validationUrl, token: $token, data: $data, transaction: $transaction);
        } catch (Exception $exception) {
            $transaction->status = TransactionStatus::CANCELED;
            $transaction->error_data = json_encode($exception->getMessage());

            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: $exception->getMessage(),
                paymentErrorMessage: $exception->getMessage()
            );
        }

        $transaction->status = TransactionStatus::PENDING;
        $transaction->save();

        try {
            $this->withdrawRequest(client: $client, url: $withdrawUrl, token: $token, data: $data, transaction: $transaction);
        } catch (Exception $exception) {
            if (in_array($exception->getCode(), [400, 401, 404, 406, 422])) {
                $transaction->status = TransactionStatus::FAILED;
            } else {
                $transaction->status = TransactionStatus::PROCESSING;
            }

            $transaction->error_data = json_encode($exception->getMessage());

            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: $exception->getMessage(),
                paymentErrorMessage: $exception->getMessage()
            );
        }

        $transaction->request_data = $data;
        $transaction->status = TransactionStatus::PROCESSING;
        $transaction->save();

        return [];
    }

    /**
     * @throws PaymentProviderException
     */
    public function checkTransactionStatus(Transaction $transaction): void
    {
        if ($transaction->type === 'withdraw') {
            $this->checkWithdrawTransactionStatus($transaction);
        }
    }

    private function checkWithdrawTransactionStatus(Transaction $transaction): void
    {
        $configs = $this->getConfigs();

        $client = new HttpClientService(new Curl());

        $token = $this->getToken(configs: $configs, transaction: $transaction);

        try {
            $content = $client->makeRequest(method: 'GET', url: $configs['withdraw_url'] . '/wallet/withdrawals/byref/' . $transaction->internal_transaction_id, config: [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                'verify' => false
            ], options: [
                'timeout' => 30
            ], data: []);
        } catch (Exception $exception) {
            if ($exception->getCode() >= 500 && $exception->getCode() < 600) {
                $transaction->status = TransactionStatus::PROCESSING;
            } elseif ($exception->getCode() == 404) {
                $transaction->status = TransactionStatus::CANCELED;
            }

            $error = ['check_status' => $exception->getMessage()];

            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: json_encode($error),
                paymentErrorMessage: json_encode($error)
            );
        }

        $transaction->response_data = $content;
        $result = json_decode($content, true);

        $this->updateStatus(result: $result, transaction: $transaction);
    }

    private function responseToArray($content): array
    {
        $xml = simplexml_load_string($content, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $result = json_decode($json, true);

        return $result;
    }

    private function updateStatus(array $result, Transaction $transaction): void
    {
        $status = $result['status'] ?? null;
        $transaction->external_transaction_id = $result['transactionId'] ?? null;

        if ($status === 2) {
            $transaction->status = TransactionStatus::APPROVED;
        } else if ($status === 1 || is_null($status)) {
            $transaction->status = TransactionStatus::PROCESSING;
        } else if ($status === 3) {
            $transaction->status = TransactionStatus::CANCELED;
            $transaction->error_data = json_encode($result);
        }

        $transaction->save();
    }

    /**
     * Generate response for terminal deposit check request.
     *
     * @param array $body
     * @param array $platformResult
     * @return array
     * @throws FieldValidationException
     */
    public function doTerminalDepositCheck(array $body, array $platformResult)
    {
        $configs = $this->getConfigs();

        $hash = md5($configs['terminal']['token'] . $body['account_id'] . $body['lang'] ?? '');
        $hash2 = md5($configs['terminal']['token2'] . $body['account_id'] . $body['lang'] ?? '');

        if (
            $body['Checksum'] !== $hash &&
            $body['Checksum'] !== $hash2
        ) {
            throw new FieldValidationException('The given data was invalid.', 400, [
                'The Checksum is invalid.'
            ]);
        }

        $propertyList = [["key" => "Բաժանորդ", "value" => $platformResult['payload']['username']]];

        $md5Hash = md5($configs['terminal']['token'] .
            json_encode($propertyList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'ResponseCode' => 0,
            'ResponseMessage' => $this->checkSuccessMessage,
            'Checksum' => $md5Hash,
            'PropertyList' => [
                [
                    "key" => "Բաժանորդ",
                    "value" => $platformResult['payload']['username'],
                    "DocNumber" => $platformResult['payload']['social_number'],
                    "DocType" => 8,
                ]
            ],
        ];
    }

    /**
     * Generate payment success response.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     */
    #[ArrayShape(['ResponseCode' => "int", 'ResponseMessage' => "string", 'DtTime' => "mixed", 'PropertyList' => "array[]", 'Checksum' => "string"])]
    public function doTerminalDepositPayment(array $body, Transaction $transaction): mixed
    {
        $configs = $this->getConfigs();

        $data = [
            'ResponseCode' => 0,
            'ResponseMessage' => $this->paymentSuccessMessage,
            'DtTime' => $body['DtTime'],
            'PropertyList' => [["key" => "Վճարման կոդ", 'value' => $transaction->platform_transaction_id]],
            'Checksum' => md5($configs['terminal']['token'] . $body['DtTime']),
        ];

        $transaction->request_data = json_encode($data);

        return $data;
    }

    /**
     * Get transaction id from request body.
     *
     * @param array $body
     * @return mixed
     */
    public function getTransactionId(array $body)
    {
        return [
            "internal_transaction_id" => $body['EDP_BILL_NO'],
        ];
    }

    /**
     * Validate make hash  from callback  and return boolean if is valid.
     *
     * @param array $body
     * @param array $configs
     * @return bool
     */
    private function isValidChecksum(array $body, array $configs): bool
    {
        $txtToHash = $configs['account_id'] . ":" . $body['EDP_AMOUNT'] . ":" . $configs['secret_key'] . ":" . $body['EDP_BILL_NO'] . ":" . $body['EDP_PAYER_ACCOUNT'] . ":" . $body['EDP_TRANS_ID'] . ":" . $body['EDP_TRANS_DATE'];

        return strtoupper($body['EDP_CHECKSUM']) === strtoupper(md5($txtToHash));
    }

    /**
     * Validate the check request
     *
     * @param array $body
     * @return bool
     * @throws FieldValidationException
     */
    public function checkTerminalToken(array $body)
    {
        $configs = $this->getConfigs();

        $hash = md5($configs['terminal']['token'] . $body['account_id'] . $body['amount'] . $body['external_transaction_id']);
        $hash2 = md5($configs['terminal']['token2'] . $body['account_id'] . $body['amount'] . $body['external_transaction_id']);

        if ($body['Checksum'] === $hash) {
            return true;
        }

        if ($body['Checksum'] === $hash2) {
            return true;
        }

        throw new FieldValidationException('The given data was invalid.', 400, [
            'The Checksum is invalid.'
        ]);
    }

    public function handleSuccess(array $body)
    {
        $amount = Transaction::query()->where('internal_transaction_id', $body['EDP_BILL_NO'])->pluck('amount')->first();

        return redirect($this->getPartner()->notify_url . '/user/wallet/deposit?success=1&paymentId=19&amount=' . $amount);
    }

    public function handleFail(array $body)
    {
        return redirect($this->getPartner()->notify_url . '/user/wallet/deposit?success=0&paymentId=19');
    }

    private function getToken(array $configs, Transaction $transaction): string
    {
        $access = Cache::pull('idbank_access_token');

        if (!is_null($access)) {
            return $access;
        }

        $autData = [
            'grant_type' => $configs['withdraw_grant_type'],
            'client_id' => $configs['withdraw_account_id'],
            'client_secret' => $configs['withdraw_secret_key'],
            'scope' => $configs['withdraw_scope'],
        ];

        $client = new HttpClientService(
            new Curl()
        );
        try {
            $content = $client->makeRequest(method: 'POST', url: $configs['auth_url'], config: [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false
            ], options: [
                'form_params' => $autData,
                'timeout' => 30
            ], data: $autData);
        } catch (ConnectException $connectException) {
            $transaction->status = TransactionStatus::FAILED;
            $transaction->error_data = json_encode($connectException->getMessage());

            $transaction->save();

            throw $connectException;
        }

        $result = json_decode($content, true);

        Cache::put('idbank_access_token', $result['access_token'], Carbon::now()->addSeconds($result['expires_in'] - 600));

        return $result['access_token'];
    }

    /**
     * @param HttpClientService $client
     * @param string $url
     * @param string $token
     * @param array $data
     * @param Transaction $transaction
     * @return void
     * @throws PaymentProviderException
     */
    private function withdrawRequest(HttpClientService $client, string $url, string $token, array $data, Transaction $transaction): void
    {
        $client->makeRequest(method: 'POST', url: $url, config: [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'verify' => false
        ], options: [
            'body' => json_encode($data),
            'timeout' => 30
        ], data: $data);

//        try {
//            $client->makeRequest(method: 'POST', url: $url, config: [
//                'headers' => [
//                    'Authorization' => 'Bearer ' . $token,
//                    'Content-Type' => 'application/json'
//                ],
//                'verify' => false
//            ], options: [
//                'body' => json_encode($data),
//                'timeout' => 30
//            ], data: $data);
//        } catch (Exception $exception) {
//            dd($exception->getMessage(), $exception->getCode());
//            $transaction->status = TransactionStatus::FAILED;
//            $transaction->error_data = json_encode($exception->getMessage());
//
//            $transaction->save();
//
//            throw new PaymentProviderException(
//                transaction: $transaction,
//                message: $exception->getMessage(),
//                paymentErrorMessage: $exception->getMessage()
//            );
//        }
    }
}
