<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:api', 'check_platform_ip'])->group(function () {
    // Transaction routes.
    Route::middleware(['throttle:120,1'])->group(function () {
        Route::post('v1/partner/{partner_id}/transactions', 'Api\TransactionController@getTransactionsByPartnerIds')->name('get_transactions_by_partner_transaction_ids');
        Route::post('v1/partner/{partner_id}/transactions/{search}', 'Api\TransactionController@searchTransactionsByExternalId')->name('get_transactions_by_external_transaction_id');

    });

    // Account transfer route.
    Route::post('v1/payments/transactions/accountTransfer', 'Api\PaymentController@accountTransfer')->name('account_transfer');

    //Get card bindings.
    Route::get('v1/creditcards', 'Api\CreditCardController@index')->name('get_credit_cards');
    Route::post('v1/creditcards/check_card_ownership', 'Api\CreditCardController@check_card_ownership')->name('check_card_ownership');
    Route::delete('v1/creditcards/{token}', 'Api\CreditCardController@delete')->name('delete_credit_card');

    // Deposit routes.
    Route::post('v1/payments/transactions/deposit', 'Api\PaymentController@deposit')->name('do_deposit');

    //Withdraw routes.
    Route::post('v1/payments/transactions/withdraw', 'Api\PaymentController@withdraw')->name('do_withdraw');

    Route::post('v1/bank_account/client_check', 'Api\BankAccountController@clientCheck')->name('client_check');
});

Route::middleware(['throttle:120,1'])->group(function () {
    // Deposit callback routes.
    Route::post('v1/payments/transactions/depositCallback', 'Api\PaymentController@depositCallback')->name('do_deposit_callback_post');
    Route::get('v1/payments/transactions/depositCallback', 'Api\PaymentController@depositCallback')->name('do_deposit_callback_get');

    Route::post('v1/partner/{partner_id}/payment/{partner_payment_id}/depositCallback', 'Api\PaymentController@depositCallback')->name('post_deposit_callback_url');
    Route::get('v1/partner/{partner_id}/payment/{partner_payment_id}/depositCallback', 'Api\PaymentController@depositCallback')->name('get_deposit_callback_url');

    Route::post('v1/partner/{partner_id}/payment/{payment_id}/transaction/success', 'Api\PaymentController@success')->name('post_handle_in_success');
    Route::get('v1/partner/{partner_id}/payment/{payment_id}/transaction/success', 'Api\PaymentController@success')->name('get_handle_in_success');

    Route::post('v1/partner/{partner_id}/payment/{payment_id}/transaction/fail', 'Api\PaymentController@fail')->name('post_handle_in_fail');
    Route::get('v1/partner/{partner_id}/payment/{payment_id}/transaction/fail', 'Api\PaymentController@fail')->name('get_handle_in_fail');

    // Withdraw callback routes.
    Route::post('v1/payments/transactions/withdrawCallback', 'Api\PaymentController@withdrawCallback')->name('do_withdraw_callback_post');
    Route::get('v1/payments/transactions/withdrawCallback', 'Api\PaymentController@withdrawCallback')->name('do_withdraw_callback_get');

    // Handle success and fail transactions.
    Route::get('v1/payments/transactions/success', 'Api\PaymentController@success')->name('handle_transaction_success');
    Route::get('v1/payments/transactions/fail', 'Api\PaymentController@fail')->name('handle_transaction_fail');

    //Route::post('v1/payments/transactions/withdrawCallback/{payment_id}', 'Api\PaymentController@withdrawCallback')->name('do_withdraw_callback');

    //Terminal and mobile app deposit routes.
    Route::post('v1/terminals/{payment_name}/{partner_id}/{action}', 'Api\TerminalController@index')->name('do_terminal_deposit_post');
    Route::get('v1/terminals/{payment_name}/{partner_id}/{action}', 'Api\TerminalController@index')->name('do_terminal_deposit_get');

    Route::post('v1/bank_account/{bank_slug}/account_callback', 'Api\BankAccountController@accountCallback')->name('account_callback');

    Route::get('documentation', function () {
        return response('', 404);
    });
});
