<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;

use App\Services\PaymentService\Exceptions\FieldValidationException;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\PaymentInterfaces\TerminalInterface;
use App\Transaction;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Class Mobidram.
 *
 * @package App\Services\Payment\Payments
 */
class Mobidram extends PaymentAbstract implements TerminalInterface
{
    /**
     * Generate response for terminal deposit check request.
     *
     * @param array $body
     * @param array $platformResult
     * @return array
     * @throws FieldValidationException
     */
    #[ArrayShape(['ResponseCode' => "int", 'ResponseMessage' => "string", 'Checksum' => "string", 'PropertyList' => "array[]"])]
    public function doTerminalDepositCheck(array $body, array $platformResult): array
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

        if ($body['Checksum'] === $hash) {
            return true;
        }

        throw new FieldValidationException('The given data was invalid.', 400, [
            'The Checksum is invalid.'
        ]);
    }
}
