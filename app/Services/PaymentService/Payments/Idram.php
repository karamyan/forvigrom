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
use Error;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Class Idram.
 *
 * @package App\Services\Payment\Payments
 */
class Idram extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
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
     * @return string
     * @throws FieldValidationException
     */
    public function doDepositCallback(array $body, Transaction $transaction): string
    {
        $configs = $this->getConfigs();

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
                        // Return `OK` message for idram to continue.
                        return 'OK';
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

        // Return `OK` message for idram.
        return 'OK';
    }

    /**
     * Handling idram withdraw request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();
        $amount = number_format($body['amount'], 2, '.', '');

        $data = [
            'EDP_SOURCE_ACCOUNT' => $configs['withdraw_account_id'],
            'EDP_DEST_ACCOUNT' => $body['wallet_id'],
            'EDP_AMOUNT' => $amount,
            'EDP_REQUEST' => $transaction->internal_transaction_id,
        ];

        // Create checksum hash.
        $checksum =
            $configs['withdraw_account_id'] . ':' .
            $amount . ':' .
            $configs['withdraw_secret_key'] . ':' .
            $body['wallet_id'] . ':' .
            ':' . // EDP_BILL_NO is empty
            $transaction->internal_transaction_id;

        $data['EDP_CHECKSUM'] = md5($checksum);

        // Saving request data to transaction.
        $transaction->request_data = json_encode($data);

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $configs['withdraw_url'], config: [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'verify' => false
        ], options: ["form_params" => $data, 'timeout' => 10], data: $data);

        // Save response data to transaction.
        $transaction->response_data = json_encode($content);

        list($k, $v) = explode('=', $content);
        $result[$k] = $v;

        if (!array_key_exists('EDP_TRANS_ID', $result)) {
            // Set transaction status failed if result status not valid for withdraw.
            $transaction->status = TransactionStatus::FAILED;
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                paymentErrorMessage: $result['EDP_ERROR'],
                request: [$data],
                response: $result);
        }

        $transaction->external_transaction_id = $result['EDP_TRANS_ID'];
        $transaction->status = TransactionStatus::APPROVED;
        $transaction->save();

        return $result;
    }

    public function doWithdrawCallback(array $body, Transaction $transaction)
    {
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
                    "value" => $platformResult['payload']['username']
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

        return redirect('https://goodwin.am/user/wallet/deposit?success=1&paymentId=19&amount='.$amount);
    }

    public function handleFail(array $body)
    {
        return redirect('https://goodwin.am/user/wallet/deposit?success=0&paymentId=19');
    }
}
