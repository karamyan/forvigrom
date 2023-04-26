<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Class PaymentConfigsTableSeeder.
 *
 * @package Database\Seeders
 */
class PaymentConfigsTableSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run()
    {
        DB::table('payment_configs')->insert([
            [
                'config' => '{"deposit": {"login": "goodwinbet_test", "password": "Mju7<ki8", "redirect_url": "https://dev.smartbet.live", "register_url": "https://ipaytest.arca.am:8445/payment/rest/register.do", "binding_login": "goodwinbet_binding", "binding_password": "Mju7<ki8", "order_status_url": "https://ipaytest.arca.am:8445/payment/rest/getOrderStatusExtended.do", "binding_payment_url": "https://ipaytest.arca.am:8445/payment/rest/paymentOrderBinding.do"}, "withdraw": {"partnerId": "71200", "bankSecretKey": "87654321", "partnerSecretKey": "12345678"}, "withdraw_uri": "https://arusapi.aeb.am:4444/ArcaOnlineServiceExternalAsmx.asmx?wsdl", "debitCardNumber": "9051210205087061"}',
                'payment_id' => 1,
                'partner_id' => 1,
                'deposit_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "currency": {"name": "Currency", "type": "string", "required": true}, "language": {"name": "Language", "type": "string", "required": false}, "pageView": {"name": "Page view", "type": "string", "required": false}, "description": {"name": "Description", "type": "string", "required": false}}',
                'deposit_callback_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "orderId": {"name": "Order id", "type": "string", "required": true}, "password": {"name": "Password", "type": "string", "required": true}, "userName": {"name": "User name", "type": "string", "required": true}}',
                'withdraw_fields' => '{"amount": {"name": "Amount", "type": "integer", "regexp": null, "required": true}}',
                'withdraw_callback_fields' => null,
                'mobile_app_deposit_fields' => null,
                'terminal_deposit_fields' => null,
            ],
            [
                'config' => '{"terminal": {"token": "ZPMGDC&MB3H6U8O5HN4QORF2GDXML9", "token2": "D.ksQ{QqP4ULC88U"}, "account_id": "110000211", "secret_key": "hMKeYLdzRbhz8npPtcbK5hD7LNBm8a8q3czZWK", "deposit_url": "https://web.idram.am/1251/payment.aspx", "redirect_url": "https://goodwin.am", "withdraw_url": "https://money.idram.am/transinternal.aspx", "withdraw_account_id": "110000319", "withdraw_secret_key": "e8N56K5hEyN4tvrJX3wrQaUM8xrgrPaXzNRQUU"}',
                'payment_id' => 2,
                'partner_id' => 1,
                'deposit_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "currency": {"name": "Currency", "type": "string", "required": false}, "language": {"name": "Language", "type": "string", "required": false}, "description": {"name": "Description", "type": "string", "required": false}}',
                'deposit_callback_fields' => '{"EDP_AMOUNT": {"name": "EDP AMOUNT", "type": "string", "required": true}, "EDP_BILL_NO": {"name": "EDP BILL NO", "type": "string", "required": true}, "EDP_CHECKSUM": {"name": "EDP CHECKSUM", "type": "string", "required": true}, "EDP_TRANS_ID": {"name": "EDP TRANS ID", "type": "string", "required": true}, "EDP_TRANS_DATE": {"name": "EDP TRANS DATE", "type": "string", "required": false}, "EDP_REC_ACCOUNT": {"name": "EDP REC ACCOUNT", "type": "string", "required": true}, "EDP_PAYER_ACCOUNT": {"name": "EDP PAYER ACCOUNT", "type": "string", "required": true}}',
                'withdraw_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "wallet_id": {"name": "Wallet id", "type": "string", "required": true}}',
                'withdraw_callback_fields' => null,
                'mobile_app_deposit_fields' => '{"check": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}}, "payment": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Amount": {"name": "Amount", "type": "numeric", "mapped": "amount", "required": true}, "DtTime": {"name": "Datetime", "type": "string", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}, "TransactID": {"name": "Transact ID", "type": "string", "mapped": "external_transaction_id", "required": true}}}',
                'terminal_deposit_fields' => '{"check": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}}, "payment": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Amount": {"name": "Amount", "type": "numeric", "mapped": "amount", "required": true}, "DtTime": {"name": "Datetime", "type": "string", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}, "TransactID": {"name": "Transact ID", "type": "string", "mapped": "external_transaction_id", "required": true}}}',
            ],
            [
                'config' => '{"wallet": {"deposit": {"shop_id": "ceo@goodwin.am", "shop_key": ">b!WUs5j&Bnq42jr>7_>m2BbIld}oF0xFBH4*F5EpR-&&WBql_x$af*49>FLEXUn_%^9QZ@OQA$H57|STx#a)sH#@varO3&?Y6IM_d]bJufvQ<CBNx&Yr6]4M1J1gI+a"}}, "api_key": "ffff"}',
                'payment_id' => 3,
                'partner_id' => 1,
                'deposit_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "currency": {"name": "Currency", "type": "string", "required": true}, "wallet_id": {"name": "wallet id", "type": "string", "required": true}}',
                'deposit_callback_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "orderId": {"name": "Order id", "type": "string", "required": true}, "password": {"name": "Password", "type": "string", "required": true}, "userName": {"name": "User name", "type": "string", "required": true}}',
                'withdraw_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "wallet_id": {"name": "Wallet Id", "type": "string", "required": true}}',
                'withdraw_callback_fields' => null,
                'mobile_app_deposit_fields' => '{"check": {"action": {"name": "Action", "type": "string", "mapped": "action", "required": true}, "number": {"name": "Number", "type": "string", "mapped": "account_id", "required": true}}, "payment": {"date": {"name": "Date", "type": "string", "required": true}, "action": {"name": "Action", "type": "string", "mapped": "action", "required": true}, "amount": {"name": "Amount", "type": "numeric", "mapped": "amount", "required": true}, "number": {"name": "Number", "type": "string", "mapped": "account_id", "required": true}, "receipt": {"name": "Receipt", "type": "string", "mapped": "external_transaction_id", "required": true}}}',
                'terminal_deposit_fields' => '{"check": {"action": {"name": "Action", "type": "string", "mapped": "action", "required": true}, "number": {"name": "Number", "type": "string", "mapped": "account_id", "required": true}}, "payment": {"date": {"name": "Date", "type": "string", "required": true}, "action": {"name": "Action", "type": "string", "mapped": "action", "required": true}, "amount": {"name": "Amount", "type": "numeric", "mapped": "amount", "required": true}, "number": {"name": "Number", "type": "string", "mapped": "account_id", "required": true}, "receipt": {"name": "Receipt", "type": "string", "mapped": "external_transaction_id", "required": true}}}',
            ],
            [
                'config' => '{"token": "%A}y:/tTHM6bx?(3", "password": "Testwin12!", "terminal": {"token": "%A}y:/tTHM6bx?(3"}, "username": "GoodAgent", "check_url": "https://10.0.0.21:17737/PaymentApi.svc/paymentapi/check", "providerId": "10950", "payment_url": "https://10.0.0.21:17737/PaymentApi.svc/paymentapi/pay", "withdrawToken": "F1D514A174CAE74226799597B2E599F00FEB772AE3587901C7413B7979A7187CA80B6513"}',
                'payment_id' => 4,
                'partner_id' => 1,
                'deposit_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "currency": {"name": "Currency", "type": "string", "required": true}, "wallet_id": {"name": "wallet id", "type": "string", "required": true}}',
                'deposit_callback_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "orderId": {"name": "Order id", "type": "string", "required": true}, "password": {"name": "Password", "type": "string", "required": true}, "userName": {"name": "User name", "type": "string", "required": true}}',
                'withdraw_fields' => '{"amount": {"name": "Amount", "type": "integer", "required": true}, "wallet_id": {"name": "Wallet Id", "type": "string", "required": true}}',
                'withdraw_callback_fields' => null,
                'mobile_app_deposit_fields' => '{"check": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}}, "payment": {"Amount": {"name": "Amount", "type": "numeric", "mapped": "amount", "required": true}, "DtTime": {"name": "Datetime", "type": "string", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}, "TransactID": {"name": "Transact ID", "type": "string", "mapped": "external_transaction_id", "required": true}}}',
                'terminal_deposit_fields' => '{"check": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}}, "payment": {"Amount": {"name": "Amount", "type": "numeric", "mapped": "amount", "required": true}, "DtTime": {"name": "Datetime", "type": "string", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}, "TransactID": {"name": "Transact ID", "type": "string", "mapped": "external_transaction_id", "required": true}}}',
            ],
            [
                'config' => '{"terminal": {"token": "^+F8ANw9<[AG.`8S"}}',
                'payment_id' => 5,
                'partner_id' => 1,
                'deposit_fields' => null,
                'deposit_callback_fields' => null,
                'withdraw_fields' => null,
                'withdraw_callback_fields' => null,
                'mobile_app_deposit_fields' => '{"check": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}}, "payment": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Amount": {"name": "Amount", "type": "numeric", "mapped": "amount", "required": true}, "DtTime": {"name": "Datetime", "type": "string", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}, "TransactID": {"name": "Transact ID", "type": "string", "mapped": "external_transaction_id", "required": true}}}',
                'terminal_deposit_fields' => null,
            ],
            [
                'config' => '{"terminal": {"token": "Q5HNP;h~\"K\\\Uq^h{"}}',
                'payment_id' => 6,
                'partner_id' => 1,
                'deposit_fields' => null,
                'deposit_callback_fields' => null,
                'withdraw_fields' => null,
                'withdraw_callback_fields' => null,
                'mobile_app_deposit_fields' => null,
                'terminal_deposit_fields' => '{"check": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}}, "payment": {"Lang": {"name": "Lang", "type": "string", "mapped": "lang", "required": true}, "Amount": {"name": "Amount", "type": "numeric", "mapped": "amount", "required": true}, "DtTime": {"name": "Datetime", "type": "string", "required": true}, "Inputs": {"name": "Inputs", "type": "array", "mapped": "account_id", "required": true}, "Checksum": {"name": "Checksum", "type": "string", "required": true}, "Currency": {"name": "Currency", "type": "string", "mapped": "currency", "required": true}, "TransactID": {"name": "Transact ID", "type": "string", "mapped": "external_transaction_id", "required": true}}}',
            ],
            [
                'config' => '{"token": "CCueuzs{NDlbzLmeNw1S@CvW}3zIQE", "payment_url": "https://10.0.0.21:7496/VivaroTransactionService.svc/VivaroWCF/Pay", "sport_account": {"provider": 13349}, "casino_account": {"provider": 13348}}',
                'payment_id' => 7,
                'partner_id' => 1,
                'deposit_fields' => null,
                'deposit_callback_fields' => null,
                'withdraw_fields' => null,
                'withdraw_callback_fields' => null,
                'mobile_app_deposit_fields' => null,
                'terminal_deposit_fields' => null,
            ],
        ]);
    }
}
