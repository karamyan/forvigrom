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
use Error;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\ArrayShape;
use League\Flysystem\UnreadableFileException;

/**
 * Class Telcell.
 *
 * @package App\Services\Payment\Payments
 */
class Telcell extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    /**
     * Company email.
     *
     * @var string
     */
    private string $email = 'support@goodwin.gw';

    /**
     * Telcell endpoint.
     *
     * @var string
     */
    private string $endpoint = "https://telcellmoney.am/";

    protected bool $hasCallback = false;

    /**
     * @var string[]
     */
    private array $availableCurrencyCodes = [
        'AMD' => '051',
        'USD' => '840',
        'EUR' => '978',
        'RUB' => '643'
    ];

    /**
     * Handling telcell deposit.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws \Throwable
     */
    #[ArrayShape(['external_transaction_id' => "int|string"])]
    public function doDeposit(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        // This function support only armenian phone number code.
        $phone = $this->validatePhoneNumber($body['wallet_id']);

        $base64Description = base64_encode("Deposit");
        $base64PartnerTransactionId = base64_encode($body['partner_transaction_id']);

        $checksum = md5($configs['wallet']['deposit']['shop_key'] .
            $configs['wallet']['deposit']['shop_id'] .
            $phone .
            $this->availableCurrencyCodes[$body['currency']] .
            $body['amount'] .
            $base64Description .
            1 .
            $base64PartnerTransactionId);

        $data = [
            'bill:issuer' => $configs['wallet']['deposit']['shop_id'],
            'buyer' => $phone,
            'currency' => $this->availableCurrencyCodes[$body['currency']],
            'sum' => $body['amount'],
            'description' => $base64Description,
            'issuer_id' => $base64PartnerTransactionId,
            'valid_days' => 1,
            'checksum' => $checksum
        ];

        $transaction->request_data = json_encode($data);

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: 'https://telcellmoney.am/invoices',
            config: [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false
            ], options: ["form_params" => $data], data: $data);

        if (!is_numeric($content)) {
            throw new Error('Invalid response from payment provider.', 502);
        }

        $transaction->external_transaction_id = $content;

        $transaction->response_data = json_encode($content);
        $transaction->status = TransactionStatus::PENDING;

        return [
            'external_transaction_id' => $content
        ];
    }

    /**
     * Handling telcell deposit callback request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
        $configs = $this->getConfigs();

        $checksum = md5($configs['wallet']['deposit']['shop_key'] .
            $configs['wallet']['deposit']['shop_id'] .
            $body['invoice']);

        $data = [
            'check_bill:issuer' => $configs['wallet']['deposit']['shop_id'],
            'invoice' => $body['invoice'],
            'checksum' => $checksum
        ];

        $transaction->request_data = json_encode($data);

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: 'https://telcellmoney.am/invoices',
            config: [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false
            ], options: ["form_params" => $data], data: $data);

        foreach (explode("\n", trim($content)) as $str) {
            $values = explode("=", $str, 2);

            if (count($values) === 2) {
                $result[$values[0]] = $values[1];
            }
        }

        if ($result['status'] !== 'PAID') {
            $transaction->status = TransactionStatus::FAILED;
            throw new \Exception('Bad Gateway', 502);
        }

        $transaction->response_data = json_encode($result);
        $transaction->status = TransactionStatus::APPROVED;
    }

    /**
     * Handling telcell withdraw request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws UnreadableFileException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $keyData = [
            'command' => "deposit_check",
            'phone' => $body['wallet_id'],
            'value' => $body['amount'],
            'currency' => $body['currency'],
        ];

        // Generate telcell security key.
        $key = $this->getEncryptedKey($keyData);
        $data = 'FROM=' . $this->email . '&TO=system@telcellmoney.am&COMMAND=RMT_EXECUTE&CIPHER=PGP&MESSAGE=' . $key;

        // Saving request data to transaction.
        $transaction->request_data = json_encode($data);

        // Do withdraw pre-check request.
        $client = new HttpClientService(
            new Curl()
        );

        try {
            $content = $client->makeRequest(method: 'POST', url: $this->endpoint,
                config: [
                    'headers' => ['Content-Type' => 'application/json'],
                    'verify' => false
                ], options: [ "body" => $data, 'timeout' => 8], data: [$data]);
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

        // Transform response to array result.
        $result = $this->transformResultToArray($content);

        // Saving response data to transaction.
        $transaction->response_data = json_encode($result);

        if (empty($result)) {
            $transaction->status = TransactionStatus::FAILED;
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                request: [$data]
            );
        }

        if ($result['result'] === 'NAK' || $result['result'] !== 'ACK') {
            // Set transaction status failed if result status not valid for withdraw.
            $transaction->status = TransactionStatus::FAILED;
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                paymentErrorMessage: $result['result_details'],
                request: [$data],
                response: $result);
        }

        $keyData = [
            'command' => "deposit",
            'phone' => $body['wallet_id'],
            'value' => $body['amount'],
            'currency' => $body['currency'],
            'client_transaction_id' => $transaction->internal_transaction_id
        ];

        // Generate telcell security key.
        $key = $this->getEncryptedKey($keyData);
        $data = 'FROM=' . $this->email . '&TO=system@telcellmoney.am&COMMAND=RMT_EXECUTE&CIPHER=PGP&MESSAGE=' . $key;

        // saving request data to transaction.
        $transaction->request_data = json_encode($data);

        // Do withdraw request.
        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $this->endpoint,
            config: [
                'headers' => ['Content-Type' => 'application/json'],
                'verify' => false
            ], options: [ "body" => $data, 'timeout' => 50], data: [$data]);

        // Transform response to array result.
        $result = $this->transformResultToArray($content);

        // Saving response data to transaction.
        $transaction->response_data = json_encode($result);

        if ($result['result'] === 'NAK' || $result['result'] !== 'ACK') {
            // Set transaction status failed if result status not valid for withdraw.
            $transaction->status = TransactionStatus::FAILED;
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                paymentErrorMessage: $result['result_details'],
                request: [$data],
                response: $result);
        }

        // Set transaction external id from response an change status to approved.
        $transaction->external_transaction_id = $result['transaction_id'];
        $transaction->status = TransactionStatus::APPROVED;
        $transaction->save();

        return $result;
    }

    /**
     * Checking transaction status. This method handling from task scheduling.
     *
     * @param Transaction $transaction
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkTransactionStatus(Transaction $transaction)
    {
        $configs = $this->getConfigs();

        $checksum = md5($configs['wallet']['deposit']['shop_key'] .
            $configs['wallet']['deposit']['shop_id'] .
            $transaction->external_transaction_id);

        $data = [
            'check_bill:issuer' => $configs['wallet']['deposit']['shop_id'],
            'invoice' => $transaction->external_transaction_id,
            'checksum' => $checksum
        ];

        $transaction->request_data = json_encode($data);

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: 'https://telcellmoney.am/invoices',
            config: [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false
            ], options: [ "form_params" => $data], data: $data);

        $result = [];
        foreach (explode("\n", trim($content)) as $str) {
            $values = explode("=", $str, 2);

            if (count($values) === 2) {
                $result[$values[0]] = $values[1];
            }
        }

        $transaction->response_data = json_encode($result);

        if (!empty($result['status'])) {
            if ($result['status'] === 'PAID') {
                $transaction->status = TransactionStatus::APPROVED;
            } else if (in_array($result['status'], ['REJECTED', 'EXPIRED', 'CANCELED', 'CANCELED FOR REPEAT'])) {
                $transaction->status = TransactionStatus::FAILED;
            }

            $transaction->save();
        }
    }

    /**
     * Generate payment success response.
     *
     * @param array $body
     * @param array $platformResult
     * @return array
     * @throws FieldValidationException
     */
    public function doTerminalDepositCheck(array $body, array $platformResult): mixed
    {
        $xml = [
            'code'=> 0,
            'message'=> $platformResult['payload']['username'],
        ];

        return $this->getXml($xml, "checkUser");
    }

    /**
     * Generate payment success response.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     */
    public function doTerminalDepositPayment(array $body, Transaction $transaction)
    {
        $xml = [
            'code'=> 0
        ];

        $transaction->request_data = json_encode($xml);

        return $this->getXml($xml, "completePurchase");
    }

    private function getXml(array $data, $action)
    {
        //TODO remove omnipay package.
        $gateway = \Omnipay\Omnipay::create("Telcell");
        $req = $gateway->$action(
            $data
        );
        $res = $req->send();
        return $res->getXml();
    }

    public function checkTerminalToken(array $body)
    {
        return true;
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
            "external_transaction_id" => $body['invoice'],
        ];
    }

    /**
     * Generate security key.
     *
     * @param $data
     * @return string
     * @throws UnreadableFileException
     */
    private function getEncryptedKey($data)
    {
        putenv("GNUPGHOME=/tmp");
        $message = '';
        foreach ($data as $key => $value) {
            $message .= "{$key}={$value}\n";
        }

        $message = substr($message, 0, -1);

        $res = gnupg_init();

        gnupg_setarmor($res, 1);

        if (!is_readable(__DIR__ . '/../Keys/easypay.pgp')) {
            throw new UnreadableFileException();
        }

        $publicKey = file_get_contents(__DIR__ . '/../Keys/easypay.pgp');
        $pubKey = gnupg_import($res, $publicKey);
        $enc = gnupg_addencryptkey($res, $pubKey['fingerprint']);
        $enc = gnupg_encrypt($res, $message);
        return urlencode($enc);
    }

    /**
     * Transform payment response to array.
     *
     * @param string $result
     * @return array
     */
    private function transformResultToArray(string $result): array
    {
        preg_match_all('/(.*)=(.*)/', $result, $matches);

        return array_combine($matches[1], $matches[2]);
    }
}
