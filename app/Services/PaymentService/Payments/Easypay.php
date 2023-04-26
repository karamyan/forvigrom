<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;

use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\Exceptions\FieldValidationException;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\PaymentInterfaces\AccountTransferInterface;
use App\Services\PaymentService\PaymentInterfaces\WithdrawInterface;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Class Easypay.
 *
 * @package App\Services\Payment\Payments
 */
class Easypay extends PaymentAbstract implements WithdrawInterface, AccountTransferInterface
{
    /**
     * Handling Easypay withdraw request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $checksum = md5($configs['providerId'] . $body['wallet_id'] . $configs['withdrawToken']);
        $data = [
            'CheckSum' => $checksum,
            'Password' => $configs['password'],
            'UserName' => $configs['username'],
            'InputValues' => [$body['wallet_id']],
            'ProviderId' => $configs['providerId']
        ];

        $transaction->request_data = json_encode(['check' => $data]);

        $client = new HttpClientService(
            new Curl()
        );

        try {
            $content = $client->makeRequest(method: 'POST', url: $configs['check_url'], config: [
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false
            ], options: [
                'body' => json_encode($data),
                'timeout' => 5
            ], data: $data);
        } catch (ConnectException $e) {
            $transaction->status = TransactionStatus::FAILED;
            $transaction->error_data = json_encode($e->getMessage());

            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Payment provider does not respond.',
                paymentErrorMessage: $e->getMessage(),
                request: [$data]
            );
        }

        $result = json_decode($content, true);

        $transaction->response_data = json_encode(['check' => $result]);

        if ($result['ResponseCode'] !== 'OK') {
            if (
                $result['ResponseDescription'] === 'Service return bad data errorԴրամապանակը լիցքավորելու համար անհրաժեշտ է հավաստագրվել։' ||
                $result['ResponseDescription'] === 'Service return bad data errorՏվյալները բացակայում են'
            ) {
                $transaction->status = TransactionStatus::CANCELED;
            } else {
                $transaction->status = TransactionStatus::FAILED;
            }
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                paymentErrorMessage: $result['ResponseDescription'],
                request: $data,
                response: $result);
        }

        $amount = floatval($body['amount']);

        $dtTime = time();
        $tz = 'Asia/Yerevan';
        $gmt = '0400';
        $dateTimeTZ = new \DateTimeZone($tz);
        $date = new \DateTime('now', $dateTimeTZ);
        $date->setTimestamp($dtTime);
        $dateFormatted = $date->format('YmdHi');

        // Create checksum.
        $checksum = md5($configs['providerId'] . $dateFormatted . $body['wallet_id'] . $amount . $configs['withdrawToken']);

        $data = [
            'CheckSum' => $checksum,
            'Password' => $configs['password'],
            'UserName' => $configs['username'],
            'Amount' => $amount,
            'Commission' => 0,
            'CurrencyISO' => $body['currency'],
            'Inputs' => [$body['wallet_id']],
            'ProviderID' => $configs['providerId'],
            'RangeID' => "0",
            'SessionID' => $transaction->partner_transaction_id,
            'SystemTime' => "/Date({$dtTime}000+{$gmt})/",
        ];

        // Saving request data to transaction.
        $transaction->request_data = json_encode(['payment' => $data]);

        $content = $client->makeRequest(method: 'POST', url: $configs['payment_url'], config: [
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => false
        ], options: [
            'body' => json_encode($data),
            'timeout' => 30
        ], data: $data);

        $result = json_decode($content, true);

        // Saving response data to transaction.
        $transaction->response_data = json_encode(['payment' => $result]);

        if ($result['ResponseCode'] !== 'OK') {
            // Set transaction status failed if result status not valid for withdraw.
            $transaction->status = TransactionStatus::FAILED;
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                paymentErrorMessage: $result['ResponseDescription'],
                request: $data,
                response: $result);
        }

        // Set transaction internal id and change status to approved.
        $transaction->external_transaction_id = $result['PaymentSystemID'];
        $transaction->status = TransactionStatus::APPROVED;
        $transaction->save();

        return $result;
    }

    /**
     * Generate payment success response.
     *
     * @param array $body
     * @param array $platformResult
     * @return array
     * @throws FieldValidationException
     */
    public function doTerminalDepositCheck(array $body, array $platformResult)
    {
        $configs = $this->getConfigs();

        $hash = md5($configs['terminal']['token'] . $body['account_id'] . $body['lang']);

        if ($body['Checksum'] !== $hash) {
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
            'Debt' => 0
        ];
    }

    /**
     * Generate payment success response.
     *
     * @param array $body
     * @param array $platformResult
     * @param Transaction $transaction
     * @return array
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
     * Make account transfer request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doAccountTransfer(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $this->setAmount($body['amount']);

        if ($body['from'] === 'sport') {
            $sourceAccount = $configs['sport_account']['provider'];
            $destinationAccount = $configs['casino_account']['provider'];
        } elseif ($body['from'] === 'casino') {
            $sourceAccount = $configs['casino_account']['provider'];
            $destinationAccount = $configs['sport_account']['provider'];
        }

        $signature = md5(
            $sourceAccount .
            $destinationAccount .
            $this->getAmount() .
            $body['partner_transaction_id'] .
            $configs['token']
        );

        $data = [
            'amount' => $this->getAmount(),
            'source_account' => $sourceAccount,
            'destination_account' => $destinationAccount,
            'signature' => $signature,
            'transaction_id' => $body['partner_transaction_id'],
        ];

        //Saving request data to transaction.
        $transaction->request_data = json_encode($data);

        // Do account transfer request.
        $client = new Client([
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => false,
            'http_errors' => false
        ]);

        $response = $client->request('POST', $configs['payment_url'], [
            'body' => json_encode($data),
            'timeout' => 20
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
        $transaction->response_data = json_encode($result);

        if ($result['ResponseCode'] !== 0) {
            // Set transaction status failed if result status not valid for transfer.
            $transaction->status = TransactionStatus::FAILED;
            throw new UnprocessableEntityHttpException($result['ResponseMessage']);
        }

        // Set transaction internal id and change status to approved.
        $transaction->external_transaction_id = $result['utrno'];
        $transaction->status = TransactionStatus::APPROVED;

        return $result;
    }

    /**
     * Validate the check request.
     *
     * @param array $body
     * @return bool
     * @throws FieldValidationException
     */
    public function checkTerminalToken(array $body): bool
    {
        $configs = $this->getConfigs();

        $hash = md5($configs['terminal']['token'] . $body['account_id'] . $body['amount'] . $body['external_transaction_id']);

        if ($body['Checksum'] === $hash) {
            return true;
        }

        throw new FieldValidationException('The given data was invalid.', 400, [
            'The Checksum is invalid.'
        ]);
    }

    /**
     * @param $amount
     */
    public function setAmount($amount): void
    {
        $this->amount = number_format(floatval($amount), 1, '.', '');
    }
}
