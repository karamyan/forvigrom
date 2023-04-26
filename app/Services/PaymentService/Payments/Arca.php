<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Payments;

use App\CardBindings;
use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use App\Services\PaymentService\PaymentInterfaces\DepositInterface;
use App\Services\PaymentService\PaymentInterfaces\WithdrawInterface;
use App\Services\PaymentService\PaymentAbstract;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;

/**
 * Class Evoca
 *
 * @package App\Services\Payment\Payments
 */
class Arca extends PaymentAbstract implements DepositInterface, WithdrawInterface
{
    private static array $errorCodes = [
        "320" => "Card number is incorrect.",
        "321" => "There is no card with the given number.",
        "322" => "There is no card with the given number.",
        "224" => "Transaction amount can't be smaller than 100.",
        "426" => "Not allowed transfer"
    ];

    /**
     * Available currency codes.
     *
     * @var array|string[]
     */
    private array $availableCurrencyCodes = [
        'AMD' => '051',
        'USD' => '840',
        'EUR' => '978',
        'RUB' => '643'
    ];

    private array $recipient;

    private string $cardNumber;

    private string $withdrawAmount;

    private array $payoutRequest;

    private array $payoutResponse;

    protected bool $hasCallback = false;

    public function getCardNumber(): string
    {
        return $this->cardNumber;
    }

    public function setCardNumber(string $cardNumber): void
    {
        $this->cardNumber = $cardNumber;
    }

    private function setRecipient($userInfo): void
    {
        $this->recipient = [
            'city' => $userInfo['city'],
            'country' => $userInfo['country'],
            'name' => 'name surname',
            'postalcode' => $userInfo['postal_code'],
            'street' => $userInfo['street'],
        ];
    }

    private function getRecipient(): array
    {
        return $this->recipient;
    }

    /**
     * Handle arca deposit request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws PaymentProviderException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doDeposit(array $body, Transaction $transaction): array
    {
        $payment = $this->getPayment();
        $partner = $this->getPartner();
        $configs = $this->getConfigs();

        $data = [];

        $data['amount'] = $this->getAmount();
        $data['currency'] = $this->availableCurrencyCodes[$body['currency']];
        $data['orderNumber'] = $body['internal_transaction_id'];
        $data['pageView'] = $body['page_view'] ?? 'DESKTOP';
        $data['description'] = $body['description'];
        $data['language'] = $body['lang'];

        if (!empty($body['client_id'])) {
            $data['clientId'] = $this->getPartner()->external_partner_id . ":" . $body['client_id'];
        }

        if (!empty($body['binding_id'])) {
            $data['userName'] = $configs['deposit']['binding_login'];
            $data['password'] = $configs['deposit']['binding_password'];
            $data['returnUrl'] = url('') . "/api/v1/payments/transactions/depositCallback?payment_id=$payment->partner_payment_id&partner_id=$partner->external_partner_id";
        } else {
            $data['userName'] = $configs['deposit']['login'];
            $data['password'] = $configs['deposit']['password'];
            $data['returnUrl'] = url('') . "/api/v1/payments/transactions/depositCallback?payment_id=$payment->partner_payment_id&partner_id=$partner->external_partner_id";
        }

        // Save request data to transaction.
        $transaction->request_data = json_encode($data);

        $client = new HttpClientService(
            new Curl()
        );

        try {
            $content = $client->makeRequest(method: 'POST', url: $configs['deposit']['register_url'], config: [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
            ], options: ["form_params" => $data, 'timeout' => 28], data: $data);
        } catch (ConnectException $e) {
            $transaction->status = TransactionStatus::FAILED;
            $transaction->error_data = json_encode($e->getMessage());

            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Payment provider does not respond.',
                paymentErrorMessage: $e->getMessage()
            );
        }

        $result = json_decode($content, true);

        if ($result['errorCode'] != 0 || $result['error'] == true) {
            throw new PaymentProviderException(transaction: $transaction, message: 'Unexpected error from payment provider.', paymentErrorMessage: $result['errorMessage'], request: $data, response: $result);
        }

        // Set transaction external id and response data.
        $transaction->status = TransactionStatus::PENDING;
        $transaction->response_data = $content;
        $transaction->external_transaction_id = $result['orderId'];

        if (!empty($body['binding_id'])) {
            return $this->bindingPayment($body, $result, $transaction);
        }

        return [
            'redirect_to' => $result['formUrl']
        ];
    }

    /**
     * Handle arca deposit callback request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doDepositCallback(array $body, Transaction $transaction): mixed
    {
        $configs = $this->getConfigs();

        $data = [
            'userName' => request()->input('binding') == '1' ? $configs['deposit']['binding_login'] : $configs['deposit']['login'],
            'password' => $configs['deposit']['password'],
        ];

        $data['orderId'] = $transaction->external_transaction_id;

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $configs['deposit']['order_status_url'], config: [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'verify' => false
        ], options: ["form_params" => $data], data: $data);

        $result = json_decode($content, true);
        $transaction->response_data = $content;
        $transaction->save();

        if (!array_key_exists('errorCode', $result)) {
            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                paymentErrorMessage: $result['errorMessage'],
                request: $data,
                response: $result
            );
        }

        $result['details'] = $result;

        if (($result['errorCode'] == "0") && $result['orderStatus'] == 2) {
            $transaction->status = TransactionStatus::APPROVED;
            $redirectTo = $this->getPartner()->return_url . "/user/wallet/deposit?success=1&paymentId=4&amount=$transaction->amount";

            $result['card_info'] = [];
            if (array_key_exists('bindingInfo', $result)) {
                $cardExists = CardBindings::withTrashed()
                    ->whereJsonContains('card_info', [
                        'pan' => $result['cardAuthInfo']['pan'],
                        'expiration' => $result['cardAuthInfo']['expiration']
                    ])
                    ->exists();

                $result['card_info'] = [
                    'is_new_bind' => !$cardExists,
                    'token' => $result['bindingInfo']['bindingId'],
                    'pan' => $result['cardAuthInfo']['pan'],
                    'expiration' => $result['cardAuthInfo']['expiration'],
                    'cardholderName' => $result['cardAuthInfo']['cardholderName'],
                ];
            }

            if (array_key_exists('cardAuthInfo', $result)) {
                CardBindings::withoutTrashed()->updateOrInsert([
                    'client_id' => $transaction->partner_id . ':' . $transaction->client_id,
                    'binding_id' => $result['bindingInfo']['bindingId'] ?? null
                ], [
                    'client_id' => $transaction->partner_id . ':' . $transaction->client_id,
                    'payment_id' => $this->getPayment()->id,
                    'binding_id' => $result['bindingInfo']['bindingId'] ?? null,
                    'card_info' => json_encode($result['cardAuthInfo']),
                    'created_at' => Carbon::now(),
                ]);
            }

        } else {
            $cardExists = CardBindings::withTrashed()
                ->whereJsonContains('card_info', [
                    'pan' => $result['cardAuthInfo']['pan'],
                    'expiration' => $result['cardAuthInfo']['expiration']
                ])
                ->where('client_id', $transaction->partner_id . ':' . $transaction->client_id)
                ->where('payment_id', $this->getPayment()->id)
                ->exists();

            if (!$cardExists) {
                if (array_key_exists('cardAuthInfo', $result)) {
                    CardBindings::withoutTrashed()->insert([
                        'client_id' => $transaction->partner_id . ':' . $transaction->client_id,
                        'payment_id' => $this->getPayment()->id,
                        'binding_id' => $result['bindingInfo']['bindingId'] ?? null,
                        'card_info' => json_encode($result['cardAuthInfo']),
                        'created_at' => Carbon::now(),
                    ]);
                }
            }

            $transaction->status = TransactionStatus::FAILED;
            $transaction->error_data = json_encode($result['errorMessage']);
            $redirectTo = $this->getPartner()->return_url . "/user/wallet/deposit?success=0&paymentId=4";
        }

        $transaction->save();

        header("Location: $redirectTo");

        return $result;
    }

    /**
     * Handle arca withdraw request.
     *
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws PaymentProviderException
     */
    public function doWithdraw(array $body, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $this->setRecipient($body['user_info']);
        $this->setWithdrawAmount($body['amount']);
        $this->setCardNumber($body['wallet_id']);

        $date = gmdate('D, d M Y H:i:s \G\M\T', time());

        $this->checkCardNumber(transaction: $transaction, configs: $configs, date: $date);

        $paymentId = $this->transferToCard(transaction: $transaction, configs: $configs, date: $date);

        return $this->transferToCardAccept(transaction: $transaction, configs: $configs, date: $date, paymentId: $paymentId);
    }

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
        $configs = $this->getConfigs();

        $data = [
            'userName' => request()->input('binding') == '1' ? $configs['deposit']['binding_login'] : $configs['deposit']['login'],
            'password' => $configs['deposit']['password'],
        ];

        $data['orderId'] = $transaction->external_transaction_id;

        $client = new HttpClientService(
            new Curl()
        );

        try {
            $content = $client->makeRequest(method: 'POST', url: $configs['deposit']['order_status_url'], config: [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'verify' => false
            ], options: ["form_params" => $data, 'timeout' => 50], data: $data);
        } catch (ConnectException $e) {
            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Payment provider does not respond.',
                paymentErrorMessage: $e->getMessage()
            );
        }

        $transaction->response_data = $content;
        $transaction->save();

        $result = json_decode($content, true);

        if (array_key_exists('errorCode', $result) && array_key_exists('orderStatus', $result)) {
            if (($result['errorCode'] == "0") && $result['orderStatus'] == 2) {
                $transaction->status = TransactionStatus::APPROVED;
            } else if (($result['errorCode'] == "0") && ($result['orderStatus'] == 0 || $result['orderStatus'] == 5)) {
                $transaction->status = TransactionStatus::PENDING;
                $transaction->error_data = json_encode($result['actionCodeDescription']);
            } else {
                $transaction->status = TransactionStatus::FAILED;
                $transaction->error_data = json_encode($result['errorMessage']);
            }

            $transaction->save();
        }
    }

    private function checkWithdrawTransactionStatus(Transaction $transaction)
    {
//        $x = json_decode($transaction->request_data, true)['https://is.evocabank.am/Is/ASBankIntegrationService.svc/Partners/G0005/TransferToCard/5685759'];
//        $xml = simplexml_load_string($x, "SimpleXMLElement", LIBXML_NOCDATA);
//        $json = json_encode($xml);
//        $array = json_decode($json, true);
//
//        $paymentId = $array['PaymentID'];
//
//        $configs = $this->getConfigs();
//        $recipients = $array['Recipient'];
//        $recipient = [];
//
//        foreach ($recipients as $key => $value) {
//            if (empty($value)) {
//                $recipient[strtolower($key)] = '';
//            } else {
//                $recipient[strtolower($key)] = $value;
//            }
//        }
//
//        dump($recipient);
//        $today = Carbon::today()->format('Y-m-d');
//        $path = '/Is/ASBankIntegrationService.svc/Partners/' . $configs['withdraw']['partner_id'] . '/TransferToCard';
//        $amount = $array['Amount'];
//        $currency = $array['Currency'];
//        $date = gmdate('D, d M Y H:i:s \G\M\T', time());
//
//        $signatureBaseString = "POST\r\n$date\r\n" . strtolower($path . '/' . $paymentId);
//        $authorization = base64_encode(hex2bin(hash_hmac('sha256', $signatureBaseString, $configs['withdraw']['goodwin_key'])));
//
//        $dataToSignArray = [
//            'amount' => $amount,
//            'cardnumber' => $array['CardNumber'],
//            'currency' => $currency,
//            'partnerid' => $configs['withdraw']['partner_id'],
//            'paydate' => $today,
//            'paymentid' => $paymentId,
//            'recipient' => $recipient,
//            'transactionid' => $transaction->id,
//        ];
//
//        $dataToSignString = $this->getDataSignString($dataToSignArray);
//        $signature = base64_encode(hex2bin(hash_hmac('sha256', $dataToSignString, $configs['withdraw']['goodwin_key'])));
//
//        $data = '<ConfirmTransferToCard xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://schemas.datacontract.org/2004/07/ArmenianSoftware.Bank.IntegrationService.Common">
//                  <Amount>' . $amount . '</Amount>
//                  <CardNumber>' . $array['CardNumber'] . '</CardNumber>
//                  <Currency>' . $currency . '</Currency>
//                  <PartnerID>' . $configs['withdraw']['partner_id'] . '</PartnerID>
//                  <PayDate>' . $today . '</PayDate>
//                  <PaymentID>' . $paymentId . '</PaymentID>
//                  <Recipient>
//                    <City>' . $recipient['city'] . '</City>
//                    <Country>' . $recipient['country'] . '</Country>
//                    <Name>' . $recipient['name'] . '</Name>
//                    <PostalCode>' . $recipient['postalcode'] . '</PostalCode>
//                    <Street>' . $recipient['street'] . '</Street>
//                  </Recipient>
//                  <Signature>' . $signature . '</Signature>
//                  <TransactionID>' . $transaction->id . '</TransactionID>
//                </ConfirmTransferToCard>';
//
////        $this->payoutRequest[$configs['withdraw']['url'] . $path . '/' . $paymentId] = $data;
////        $transaction->request_data = json_encode($this->payoutRequest);
//
//        $client = new HttpClientService(
//            new Curl()
//        );
//
//        $content = $client->makeRequest(method: 'POST', url: $configs['withdraw']['url'] . $path . '/' . $paymentId, config: [
//            'headers' => [
//                'Content-Type' => 'application/xml',
//                'TimeStamp' => $date,
//                'Authorization' => 'AS-WS ' . $configs['withdraw']['partner_id'] . ':' . $authorization,
//            ],
//            'verify' => false
//        ], options: [
//            'body' => $data,
//            'timeout' => 35
//        ], data: []);
//
//        $result = json_decode(json_encode(simplexml_load_string($content)), true);
//        dump(json_encode($result));
//        return 46545;
    }

    /**
     * Handle arca withdraw callback request.
     *
     * @param array $body
     * @param Transaction $transaction
     */
    public function doWithdrawCallback(array $body, Transaction $transaction)
    {
    }

    /**
     * Check transaction status.
     *
     * @param Transaction $transaction
     * @return Transaction
     */
    public function getTransactionStatus(Transaction $transaction): Transaction
    {
        $transaction->status = 'approved';

        return $transaction;
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
            "external_transaction_id" => $body['orderId'],
        ];
    }

    /**
     * @param array $body
     * @param $order
     * @param Transaction $transaction
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function bindingPayment(array $body, $order, Transaction $transaction): array
    {
        $configs = $this->getConfigs();

        $data = [
            'userName' => $configs['deposit']['binding_login'],
            'password' => $configs['deposit']['binding_password'],
            'mdOrder' => $order['orderId'],
            'bindingId' => $body['binding_id']
        ];

        if (!empty($body['lang'])) {
            $data['language'] = $body['lang'];
        }

        $client = new HttpClientService(
            new Curl()
        );
        $content = $client->makeRequest(method: 'POST', url: $configs['deposit']['binding_payment_url'], config: [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'verify' => false
        ], options: ["form_params" => $data], data: $data);

        $result = json_decode($content, true);
        $order['_binding_payment_response'] = $result;

        $data = [
            'userName' => $configs['deposit']['binding_login'],
            'password' => $configs['deposit']['binding_password'],
            'orderId' => $order['orderId']
        ];

        $content = $client->makeRequest(method: 'POST', url: $configs['deposit']['order_status_url'], config: [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'verify' => false
        ], options: ["form_params" => $data], data: $data);

        $statusResult = json_decode($content, true);

        $transaction->response_data = $content;

        if ((!array_key_exists('errorCode', $statusResult) || $statusResult['errorCode'] == 0) && $statusResult['orderStatus'] == 2) {
            $resCode = '';
            $transaction->status = TransactionStatus::APPROVED;
        } else {
            $resCode = 1;
            $transaction->status = TransactionStatus::FAILED;
        }

        $statusResult['redirect_to'] = $resCode;
        return $statusResult;
    }

    /**
     * @param string $amount
     */
    public function setAmount($amount): void
    {
        parent::setAmount($amount * 100);
    }

    public function getWithdrawAmount(): string
    {
        return $this->withdrawAmount;
    }

    public function setWithdrawAmount(float $amount): void
    {
        $this->withdrawAmount = number_format(intval($amount), 2, '.', '');
    }

    private function arrayToXml($data, $xml_data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item' . $key; //dealing with <0/>..<n/> issues
                }
                $subnode = $xml_data->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml_data->addChild("$key", htmlspecialchars("$value"));
            }
        }

        return $xml_data;
    }

    private function getDataSignString(array $dataToSignArray): string
    {
        $dataToSignString = '';
        $length = count($dataToSignArray);
        $i = 0;
        foreach ($dataToSignArray as $key => $value) {
            if (is_array($value)) {
                $s = $key . '=with { ';
                $len = count($value);
                $y = 0;
                foreach ($value as $k => $v) {
                    if ($y == $len - 1) {
                        $s .= $k . '=' . $v;
                    } else {
                        $s .= $k . '=' . $v . "\r\n";
                    }

                    $y++;
                }
                $s .= "}\r\n";
                $dataToSignString .= $s;
            } else {
                if ($i == $length - 1) {
                    $dataToSignString .= $key . '=' . $value;
                } else {
                    $dataToSignString .= $key . '=' . $value . "\r\n";
                }


            }

            $i++;
        }

        return $dataToSignString;
    }

    private function checkCardNumber(Transaction $transaction, array $configs, string $date): void
    {
        $path = '/Is/ASBankIntegrationService.svc/Partners/' . $configs['withdraw']['partner_id'] . '/Cards?CardNumber=' . $this->getCardNumber() . '&EmbossedName=';
        $signatureBaseString = "GET\r\n$date\r\n" . strtolower($path);
        $authorization = base64_encode(hex2bin(hash_hmac('sha256', $signatureBaseString, $configs['withdraw']['goodwin_key'])));

        $this->payoutRequest[$configs['withdraw']['url'] . $path] = [];
        $transaction->request_data = json_encode($this->payoutRequest);

        $client = new HttpClientService(
            new Curl()
        );

        try {
            $content = $client->makeRequest(method: 'GET', url: $configs['withdraw']['url'] . $path, config: [
                'headers' => [
                    'Content-Type' => 'application/xml',
                    'TimeStamp' => $date,
                    'Authorization' => 'AS-WS ' . $configs['withdraw']['partner_id'] . ':' . $authorization,
                ],
                'verify' => false
            ], options: [
                'timeout' => 50
            ], data: []);
        } catch (ConnectException $connectException) {
            $transaction->status = TransactionStatus::FAILED;
            $transaction->error_data = json_encode($connectException->getMessage());

            $transaction->save();

            throw $connectException;
        }

        $result = json_decode(json_encode(simplexml_load_string($content)), true);
        $this->payoutResponse[$configs['withdraw']['url'] . $path] = $result;
        $transaction->response_data = json_encode($this->payoutResponse);
        $transaction->save();


        if ($result['Status'] !== '0' || !empty($result['ErrorDescription'])) {
            if (array_key_exists($result['Status'], self::$errorCodes)) {
                $transaction->status = TransactionStatus::CANCELED;
                $message = self::$errorCodes[$result['Status']];
            } else {
                $transaction->status = TransactionStatus::FAILED;
                $message = $result['ErrorDescription'];
            }

            $transaction->error_data = json_encode($result['ErrorDescription']);

            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Invalid card number',
                paymentErrorMessage: $message,
                request: [$path],
                response: $result);
        }
    }

    private function transferToCard(Transaction $transaction, array $configs, string $date): int
    {
        $path = '/Is/ASBankIntegrationService.svc/Partners/' . $configs['withdraw']['partner_id'] . '/TransferToCard';

        $signatureBaseString = "POST\r\n$date\r\n" . strtolower($path);
        $authorization = base64_encode(hex2bin(hash_hmac('sha256', $signatureBaseString, $configs['withdraw']['goodwin_key'])));

        $amount = $this->getWithdrawAmount();
        $currency = $this->getCurrency();

        $today = Carbon::today()->format('Y-m-d');

        $recipient = $this->getRecipient();

        $dataToSignArray = [
            'amount' => $amount,
            'cardnumber' => $this->getCardNumber(),
            'currency' => $currency,
            'partnerid' => $configs['withdraw']['partner_id'],
            'paydate' => $today,
            'recipient' => $recipient,
            'transactionid' => $transaction->id,
        ];
        $dataToSignString = $this->getDataSignString($dataToSignArray);

        $signature = base64_encode(hex2bin(hash_hmac('sha256', $dataToSignString, $configs['withdraw']['goodwin_key'])));

        $data = '<MakeTransferToCard xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://schemas.datacontract.org/2004/07/ArmenianSoftware.Bank.IntegrationService.Common">
                  <Amount>' . $amount . '</Amount>
                  <CardNumber>' . $this->getCardNumber() . '</CardNumber>
                  <Currency>' . $currency . '</Currency>
                  <PartnerID>' . $configs['withdraw']['partner_id'] . '</PartnerID>
                  <PayDate>' . $today . '</PayDate>
                  <Recipient>
                    <City>' . $recipient['city'] . '</City>
                    <Country>' . $recipient['country'] . '</Country>
                    <Name>' . $recipient['name'] . '</Name>
                    <PostalCode>' . $recipient['postalcode'] . '</PostalCode>
                    <Street>' . $recipient['street'] . '</Street>
                  </Recipient>
                  <Signature>' . $signature . '</Signature>
                  <TransactionID>' . $transaction->id . '</TransactionID>
                </MakeTransferToCard>';

        $this->payoutRequest[$configs['withdraw']['url'] . $path] = $data;
        $transaction->request_data = json_encode($this->payoutRequest);

        $client = new HttpClientService(
            new Curl()
        );

        try {
            $content = $client->makeRequest(method: 'POST', url: $configs['withdraw']['url'] . $path, config: [
                'headers' => [
                    'Content-Type' => 'application/xml',
                    'TimeStamp' => $date,
                    'Authorization' => 'AS-WS ' . $configs['withdraw']['partner_id'] . ':' . $authorization,
                ],
                'verify' => false
            ], options: [
                'body' => $data,
                'timeout' => 50
            ], data: []);
        } catch (ConnectException $connectException) {
            $transaction->status = TransactionStatus::PENDING;
            $transaction->error_data = json_encode($connectException->getMessage());

            $transaction->save();

            throw $connectException;
        }

        $result = json_decode(json_encode(simplexml_load_string($content)), true);
        $this->payoutResponse[$configs['withdraw']['url'] . $path] = $result;
        $transaction->response_data = json_encode($this->payoutResponse);

        $paymentId = intval($result['PaymentID']) ?? null;
        $externalId = intval($result['TransactionID']) ?? null;

        if ($result['Status'] !== '0' || $result['State'] !== '0' || !empty($result['ErrorDescription'])) {
            if (array_key_exists($result['Status'], self::$errorCodes)) {
                $transaction->status = TransactionStatus::CANCELED;
                $message = self::$errorCodes[$result['Status']];
            } else if(is_null($paymentId) || is_null($externalId)) {
                $transaction->status = TransactionStatus::FAILED;
                $message = $result['ErrorDescription'];
            } else {
                $transaction->status = TransactionStatus::PENDING;
                $message = $result['ErrorDescription'];
            }

            $transaction->error_data = json_encode($result['ErrorDescription']);

            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: "Error from Evoca provider.",
                paymentErrorMessage: $message,
                request: [$data],
                response: $result);
        }

        $transaction->external_transaction_id = $externalId;

        $errordescription = !empty($result['ErrorDescription']) ? $result['ErrorDescription'] : '';
        $dataToSignResponseArray = [
            'amount' => $result['Amount'],
            'cardnumber' => $result['CardNumber'],
            'currency' => $result['Currency'],
            'errordescription' => $errordescription,
            'partnerid' => $configs['withdraw']['partner_id'],
            'paydate' => $result['PayDate'],
            'paymentid' => $result['PaymentID'],
            'recipient' => $recipient,
            'state' => $result['State'],
            'status' => $result['Status'],
            'transactionid' => $transaction->id,
        ];
        $dataToSignResponseString = $this->getDataSignString($dataToSignResponseArray);

        $signatureResponse = base64_encode(hex2bin(hash_hmac('sha256', $dataToSignResponseString, $configs['withdraw']['evoca_key'])));

        if ($signatureResponse !== $result['Signature']) {
            $transaction->status = TransactionStatus::PENDING;
            $transaction->error_data = json_encode('Invalid signature from response');

            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Invalid signature from response',
                paymentErrorMessage: $result['ErrorDescription'],
                request: [$data],
                response: $result);
        }

        return $paymentId;
    }

    private function transferToCardAccept(Transaction $transaction, array $configs, string $date, int $paymentId): array
    {
        $recipient = $this->getRecipient();

        $today = Carbon::today()->format('Y-m-d');
        $path = '/Is/ASBankIntegrationService.svc/Partners/' . $configs['withdraw']['partner_id'] . '/TransferToCard';
        $amount = $this->getWithdrawAmount();
        $currency = $this->getCurrency();

        $signatureBaseString = "POST\r\n$date\r\n" . strtolower($path . '/' . $paymentId);
        $authorization = base64_encode(hex2bin(hash_hmac('sha256', $signatureBaseString, $configs['withdraw']['goodwin_key'])));

        $dataToSignArray = [
            'amount' => $amount,
            'cardnumber' => $this->getCardNumber(),
            'currency' => $currency,
            'partnerid' => $configs['withdraw']['partner_id'],
            'paydate' => $today,
            'paymentid' => $paymentId,
            'recipient' => $recipient,
            'transactionid' => $transaction->id,
        ];

        $dataToSignString = $this->getDataSignString($dataToSignArray);
        $signature = base64_encode(hex2bin(hash_hmac('sha256', $dataToSignString, $configs['withdraw']['goodwin_key'])));

        $data = '<ConfirmTransferToCard xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://schemas.datacontract.org/2004/07/ArmenianSoftware.Bank.IntegrationService.Common">
                  <Amount>' . $amount . '</Amount>
                  <CardNumber>' . $this->getCardNumber() . '</CardNumber>
                  <Currency>' . $currency . '</Currency>
                  <PartnerID>' . $configs['withdraw']['partner_id'] . '</PartnerID>
                  <PayDate>' . $today . '</PayDate>
                  <PaymentID>' . $paymentId . '</PaymentID>
                  <Recipient>
                    <City>' . $recipient['city'] . '</City>
                    <Country>' . $recipient['country'] . '</Country>
                    <Name>' . $recipient['name'] . '</Name>
                    <PostalCode>' . $recipient['postalcode'] . '</PostalCode>
                    <Street>' . $recipient['street'] . '</Street>
                  </Recipient>
                  <Signature>' . $signature . '</Signature>
                  <TransactionID>' . $transaction->id . '</TransactionID>
                </ConfirmTransferToCard>';

        $this->payoutRequest[$configs['withdraw']['url'] . $path . '/' . $paymentId] = $data;
        $transaction->request_data = json_encode($this->payoutRequest);

        $client = new HttpClientService(
            new Curl()
        );

        try {
            $content = $client->makeRequest(method: 'POST', url: $configs['withdraw']['url'] . $path . '/' . $paymentId, config: [
                'headers' => [
                    'Content-Type' => 'application/xml',
                    'TimeStamp' => $date,
                    'Authorization' => 'AS-WS ' . $configs['withdraw']['partner_id'] . ':' . $authorization,
                ],
                'verify' => false
            ], options: [
                'body' => $data,
                'timeout' => 50
            ], data: []);
        } catch (ConnectException $connectException) {
            $transaction->status = TransactionStatus::PENDING;
            $transaction->error_data = json_encode($connectException->getMessage());

            $transaction->save();

            throw $connectException;
        }

        $result = json_decode(json_encode(simplexml_load_string($content)), true);
        $this->payoutResponse[$configs['withdraw']['url'] . $path . '/' . $paymentId] = $result;
        $transaction->response_data = json_encode($this->payoutResponse);

        $errordescription = !empty($result['ErrorDescription']) ? $result['ErrorDescription'] : '';
        $dataToSignResponseArray = [
            'amount' => $result['Amount'],
            'cardnumber' => $result['CardNumber'],
            'currency' => $result['Currency'],
            'errordescription' => $errordescription,
            'partnerid' => $configs['withdraw']['partner_id'],
            'paydate' => $result['PayDate'],
            'paymentid' => $result['PaymentID'],
            'recipient' => $recipient,
            'state' => $result['State'],
            'status' => $result['Status'],
            'transactionid' => $transaction->id,
        ];
        $dataToSignResponseString = $this->getDataSignString($dataToSignResponseArray);

        $signatureResponse = base64_encode(hex2bin(hash_hmac('sha256', $dataToSignResponseString, $configs['withdraw']['evoca_key'])));

        if ($result['Status'] !== '0' || $result['State'] !== '1' || !empty($result['ErrorDescription'])) {
            $transaction->status = TransactionStatus::PENDING;
            $transaction->error_data = json_encode($result['ErrorDescription']);
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from Evoca provider.',
                paymentErrorMessage: $result['ErrorDescription'],
                request: [$data],
                response: $result);
        }

        if ($signatureResponse !== $result['Signature']) {
            $transaction->status = TransactionStatus::PENDING;
            $transaction->error_data = json_encode('Invalid signature from response');
            $transaction->save();

            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Invalid signature from response',
                paymentErrorMessage: $result['ErrorDescription'],
                request: [$data],
                response: $result);
        }

        $transaction->status = TransactionStatus::APPROVED;
        $transaction->save();

        return $result;
    }

    public function checkTerminalToken(array $body): bool
    {
        return true;
    }

    public function doTerminalDepositCheck(array $body, array $platformResult)
    {
        return [
            'code' => 100,
            'message' => 'success',
            'data' => [
                [
                    "key" => "Բաժանորդ",
                    "value" => $platformResult['payload']['username']
                ]
            ]
        ];
    }

    public function doTerminalDepositPayment(array $body, Transaction $transaction): mixed
    {
        if ($body['evoca_status'] == 100) {
            $data = [
                'code' => 100,
                'message' => 'success',
                'data' => [
                    'transaction_id' => "{$transaction->internal_transaction_id}"
                ]
            ];

            $transaction->request_data = json_encode($data);

            return $data;
        } else {
            throw new PaymentProviderException(
                transaction: $transaction,
                message: 'Unexpected error from payment provider.',
                request: $body);
        }
    }
}
