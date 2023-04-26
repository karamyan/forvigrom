<?php
//
//use Tests\functional\Helper;
//
//class TerminalAndMobileAppDepositTestCest
//{
//    private array $idramCheckResult = [
//        "ResponseCode",
//        "ResponseMessage",
//        "Checksum",
//        "PropertyList",
//        "key",
//        "value"
//    ];
//
//    private array $easypayCheckResult = [
//        "ResponseCode",
//        "ResponseMessage",
//        "Checksum",
//        "PropertyList",
//        "key",
//        "value",
//        "Debt"
//    ];
//
//    public function idramSportTerminalCheck(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/terminals/idram_sport/1/check', [
//            "Checksum" => 'e29301307621d935c697dcf030c8108d',
//            "Currency" => "AMD",
//            "Lang" => 'hy',
//            "Inputs" => [
//                42873748
//            ]
//        ]);
//        $I->seeResponseCodeIs(200);
//        (new Helper())->seeIsEqualResponseJsonKeys($I->grabResponse(), $this->idramCheckResult);
//    }
//
//    public function idramCasinoTerminalCheck(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/terminals/idram/1/check', [
//            "Checksum" => 'e29301307621d935c697dcf030c8108d',
//            "Currency" => "AMD",
//            "Lang" => 'hy',
//            "Inputs" => [
//                42873748
//            ]
//        ]);
//        $I->seeResponseCodeIs(200);
//        (new Helper())->seeIsEqualResponseJsonKeys($I->grabResponse(), $this->idramCheckResult);
//    }
//
//    public function mobidramSportMobileAppCheck(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/terminals/mobidram_sport/1/check', [
//            "Checksum" => 'c34c156fd2f82899d331a1efa4eee6a3',
//            "Currency" => "AMD",
//            "Lang" => 'hy',
//            "Inputs" => [
//                42873748
//            ]
//        ]);
//        $I->seeResponseCodeIs(200);
//        (new Helper())->seeIsEqualResponseJsonKeys($I->grabResponse(), $this->idramCheckResult);
//    }
//
//    public function mobidramCasinoMobileAppCheck(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/terminals/mobidram/1/check', [
//            "Checksum" => 'c34c156fd2f82899d331a1efa4eee6a3',
//            "Currency" => "AMD",
//            "Lang" => 'hy',
//            "Inputs" => [
//                42873748
//            ]
//        ]);
//        $I->seeResponseCodeIs(200);
//        (new Helper())->seeIsEqualResponseJsonKeys($I->grabResponse(), $this->idramCheckResult);
//    }
//
////    public function mobidramSportTerminalCheck(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/mobidram2_sport/1/check', [
////            "Checksum" => 'c34c156fd2f82899d331a1efa4eee6a3',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////        (new Helper())->seeIsEqualResponseJsonKeys($I->grabResponse(), $this->idramCheckResult);
////    }
////
////    public function mobidramCasinoTerminalCheck(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/mobidram2/1/check', [
////            "Checksum" => 'c34c156fd2f82899d331a1efa4eee6a3',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////        (new Helper())->seeIsEqualResponseJsonKeys($I->grabResponse(), $this->idramCheckResult);
////    }
//
//    public function easypaySportTerminalCheck(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/terminals/easypay_sport/1/check', [
//            "Checksum" => '77b8424f0804f34ed36932f5c199338b',
//            "Currency" => "AMD",
//            "Lang" => 'hy',
//            "Inputs" => [
//                42873748
//            ]
//        ]);
//        $I->seeResponseCodeIs(200);
//        (new Helper())->seeIsEqualResponseJsonKeys($I->grabResponse(), $this->easypayCheckResult);
//    }
//
//    public function easypayCasinoTerminalCheck(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendPost('/api/v1/terminals/easypay/1/check', [
//            "Checksum" => '77b8424f0804f34ed36932f5c199338b',
//            "Currency" => "AMD",
//            "Lang" => 'hy',
//            "Inputs" => [
//                42873748
//            ]
//        ]);
//        $I->seeResponseCodeIs(200);
//        (new Helper())->seeIsEqualResponseJsonKeys($I->grabResponse(), $this->easypayCheckResult);
//    }
//
//    public function telcellSportTerminalCheck(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendGet('/api/v1/terminals/telcell_sport/1/check', [
//            "action" => 'check',
//            "number" => 42873748
//        ]);
//        $I->seeResponseCodeIs(200);
//    }
//
//    public function telcellCasinoTerminalCheck(FunctionalTester $I)
//    {
//        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->sendGet('/api/v1/terminals/telcell/1/check', [
//            "action" => 'check',
//            "number" => 42873748
//        ]);
//        $I->seeResponseCodeIs(200);
//    }
//
////    public function idramSportTerminalPayment(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/idram_sport/1/payment', [
////            "Amount" => '100',
////            "TransactID" => '21100400135987',
////            "DtTime" => '2021-10-04T16:51:41',
////            "Checksum" => 'e29301307621d935c697dcf030c8108d',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
//
////    public function idramCasinoTerminalPayment(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/idram/1/payment', [
////            "Amount" => '100',
////            "TransactID" => '21100400135987',
////            "DtTime" => '2021-10-04T16:51:41',
////            "Checksum" => 'e29301307621d935c697dcf030c8108d',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
//
////    public function mobidramSportMobileAppPayment(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/mobidram_sport/1/payment', [
////            "Amount" => 1,
////            "TransactID" => '202110050001004',
////            "DtTime" => '2021-10-05T14:39:27',
////            "Checksum" => '53683e1637b13b9d42d16143dc8fa490',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
////
////    public function mobidramCasinoMobileAppPayment(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/mobidram/1/payment', [
////            "Amount" => 1,
////            "TransactID" => '202110050001004',
////            "DtTime" => '2021-10-05T14:39:27',
////            "Checksum" => '53683e1637b13b9d42d16143dc8fa490',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
////
////    public function mobidramSportTerminalPayment(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/mobidram2_sport/1/payment', [
////            "Amount" => 50,
////            "TransactID" => '202110050001004',
////            "DtTime" => '2021-10-05T14:39:27',
////            "Checksum" => '5df6ef3ae30e636dd9e5cefb7ff20760',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
////
////    public function mobidramCasinoTerminalPayment(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/mobidram2/1/payment', [
////            "Amount" => 50,
////            "TransactID" => '202110050001004',
////            "DtTime" => '2021-10-05T14:39:27',
////            "Checksum" => '5df6ef3ae30e636dd9e5cefb7ff20760',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
////
////    public function easypaySportTerminalPayment(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/easypay_sport/1/payment', [
////            "Amount" => 1,
////            "TransactID" => '278696487',
////            "DtTime" => '2021-10-04T16:47:16',
////            "Checksum" => '0d9545af892f5aacc73204c2a26e13d6',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748,
////                null,
////                null,
////                null
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
////
////    public function easypayCasinoTerminalPayment(FunctionalTester $I)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendPost('/api/v1/terminals/easypay/1/payment', [
////            "Amount" => 1,
////            "TransactID" => '278696487',
////            "DtTime" => '2021-10-04T16:47:16',
////            "Checksum" => '0d9545af892f5aacc73204c2a26e13d6',
////            "Currency" => "AMD",
////            "Lang" => 'hy',
////            "Inputs" => [
////                42873748,
////                null,
////                null,
////                null
////            ]
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
////
////    public function telcellSportTerminalPayment(FunctionalTester $I)
////    {
////        $this->telcellTerminalPayment($I, 'telcell_sport');
////    }
////
////    public function telcellCasinoTerminalPayment(FunctionalTester $I)
////    {
////        $this->telcellTerminalPayment($I, 'telcell');
////    }
////
////    public function telcellTerminalPayment(FunctionalTester $I, string $type)
////    {
////        $I->haveHttpHeader('Content-Type', 'application/json');
////        $I->sendGet("/api/v1/terminals/$type/1/telcell", [
////            "action" => 'payment',
////            "number" => '42873748',
////            "amount" => '1',
////            "receipt" => '1353925475',
////            "date" => "2021-10-04T21:42:02"
////        ]);
////        $I->seeResponseCodeIs(200);
////    }
//}
