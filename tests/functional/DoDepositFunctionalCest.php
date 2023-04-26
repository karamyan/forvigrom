<?php

class DoDepositFunctionalCest
{
    /**
     * Response keys.
     *
     * @var array
     */
    private array $jsonKeys = [];

//    public function ArcadepositCall(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/payments/transactions/deposit', [
//            "amount" => 1,
//            "currency" => "AMD",
//            "partner_id" => 1,
//            "payment_id" => 1,
//            "payment_method" => "card",
//            "partner_transaction_id" => "sds",
//            "description" => "543",
//            "lang" => "en"
//        ]);
//
//        $I->seeResponseCodeIs(200);
//
//        $response = json_decode(json: $I->grabResponse(), associative: true);
//
//        $result = [
//            "data",
//            "internal_id",
//            "external_id",
//            "partner_id",
//            "amount",
//            "currency",
//            "datetime",
//            "timezone",
//            "status",
//            "details",
//            "external_transaction_id",
//            "redirect_to",
//        ];
//
//        $keys = $this->getJsonKeys($response);
//
//        if (count(array_diff($keys, $result)) !== 0) {
//            throw new Exception('Invalid response struct.');
//        }
//    }

//    public function IdramDepositCall(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/payments/transactions/deposit', [
//            "amount" => 1,
//            "currency" => "AMD",
//            "partner_id" => 1,
//            "payment_id" => 2,
//            "payment_method" => "card",
//            "partner_transaction_id" => "sds",
//            "description" => "543",
//            "lang" => "en"
//        ]);
//
//        $I->seeResponseCodeIs(200);
//        $I->seeResponseIsJson();
//
//        $response = json_decode(json: $I->grabResponse(), associative: true);
//
//        $result = [
//            "data",
//            "internal_id",
//            "external_id",
//            "partner_id",
//            "amount",
//            "currency",
//            "datetime",
//            "timezone",
//            "status",
//            "details",
//            "external_transaction_id",
//            "redirect_to",
//        ];
//
//        $keys = $this->getJsonKeys($response);
//
//        if (count(array_diff($keys, $result)) !== 0) {
//            throw new Exception('Invalid response struct.');
//        }
//    }

//    public function TelcellDepositCall(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/payments/transactions/deposit', [
//            "amount" => 1,
//            "currency" => "AMD",
//            "partner_id" => 1,
//            "payment_id" => 3,
//            "payment_method" => "card",
//            "partner_transaction_id" => "sds",
//            "description" => "543",
//            "lang" => "en",
//            "wallet_id" => "+37498781202"
//        ]);
//
//        $I->seeResponseCodeIs(200);
//        $I->seeResponseIsJson();
//
//        $response = json_decode(json: $I->grabResponse(), associative: true);
//
//        $result = [
//            "data",
//            "internal_id",
//            "external_id",
//            "partner_id",
//            "amount",
//            "currency",
//            "datetime",
//            "timezone",
//            "status",
//            "details",
//            "external_transaction_id",
//            "redirect_to",
//        ];
//
//        $keys = $this->getJsonKeys($response);
//
//        if (count(array_diff($keys, $result)) !== 0) {
//            throw new Exception('Invalid response struct.');
//        }
//    }

    /**
     * Get response all keys.
     *
     * @param array $fields
     * @return array
     */
    private function getJsonKeys(array $fields): array
    {
        foreach ($fields as $key => $val) {
            if (is_array($val)) {
                $this->getJsonKeys($val);
            }
            $this->jsonKeys[] = $key;
        }

        return $this->jsonKeys;
    }
}
