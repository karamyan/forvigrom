<?php

declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Rules\CheckAmount;
use App\Rules\CheckPartner;
use App\Rules\CheckPartnerPayments;
use App\Rules\CheckPayment;
use App\Rules\CheckPlatform;
use Illuminate\Validation\Rule;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Class ValidationRules.
 *
 * @package App\Services\Payment
 */
final class ValidationRules
{
    /**
     * Get deposit and withdraw validation rules.
     *
     * @return array
     */
    #[ArrayShape(['amount' => "string", 'currency' => "string", 'payment_id' => "array", 'partner_id' => "array", 'partner_transaction_id' => "string", 'payment_method' => "string", 'description' => "string", 'lang' => "string"])]
    public static function getTransactionRules(): array
    {
        $currencies = implode(',', array_keys(CurrencyCodes::AvailableCurrencyCodes));

        return [
            'amount' => [
                'required',
                new CheckAmount(),
            ],
            'currency' => 'required|in:'.$currencies,
            'payment_id' => [
                'required',
                'numeric',
                Rule::exists('payments', 'partner_payment_id')->whereNull('deleted_at')
            ],
            'partner_id' => [
                'required',
                'numeric',
                Rule::exists('partners', 'external_partner_id')->whereNull('deleted_at')
            ],
            'partner_transaction_id' => 'required|unique:mysql.transactions,partner_transaction_id',
            'description' => 'string',
            'lang' => 'in:hy,ru,en,am,fr,az,uz,es'
        ];
    }

    public static function getWithdrawRules(): array
    {
        $currencies = implode(',', array_keys(CurrencyCodes::AvailableCurrencyCodes));

        return [
            'amount' => [
                'required',
                new CheckAmount(),
            ],
            'currency' => 'required|in:'.$currencies,
            'payment_id' => [
                'required',
                'numeric',
                Rule::exists('payments', 'partner_payment_id')->whereNull('deleted_at')
            ],
            'partner_id' => [
                'required',
                'numeric',
                Rule::exists('partners', 'external_partner_id')->whereNull('deleted_at')
            ],
            'partner_transaction_id' => 'required',
            'description' => 'string',
            'lang' => 'in:hy,ru,en,am,fr,az,uz,es'
        ];
    }

    /**
     * Get deposit callback validation rules.
     *
     * @return array[]
     */
    #[ArrayShape(['payment_id' => "array", 'partner_id' => "array"])]
    public static function getDepositCallbackRules(): array
    {
        return [
            'payment_id' => [
                'required',
                'numeric',
                Rule::exists('payments', 'partner_payment_id')->whereNull('deleted_at')
            ],
            'partner_id' => [
                'required',
                'numeric',
                Rule::exists('partners', 'external_partner_id')->whereNull('deleted_at')
            ],
        ];
    }

    /**
     * Get account transfer validation rules.
     *
     * @return string[]
     */
    #[ArrayShape(['amount' => "string", 'currency' => "string", 'partner_id' => "string", 'payment_id' => "string", 'payment_method' => "string", 'partner_transaction_id' => "string", 'from' => "string", 'to' => "string"])]
    public static function getAccountTransferRules(): array
    {
        $currencies = implode(',', array_keys(CurrencyCodes::AvailableCurrencyCodes));

        return [
            'amount' => [
                'required',
                new CheckAmount(),
            ],
            'currency' => 'required|in:'.$currencies,
            'payment_id' => [
                'required',
                'numeric',
                Rule::exists('payments', 'partner_payment_id')->whereNull('deleted_at')
            ],
            'partner_id' => [
                'required',
                'numeric',
                Rule::exists('partners', 'external_partner_id')->whereNull('deleted_at')
            ],
            'partner_transaction_id' => 'required|unique:mysql.transactions,partner_transaction_id',
            'from' => 'required|in:sport,casino',
            'to' => 'required|in:sport,casino',
        ];
    }

    /**
     * Get terminal deposit validation rules.
     *
     * @param array $params
     * @return array[]
     */
    public static function getTerminalRules(array $params): array
    {
        return [
            'payment_name' => [
                'required',
                'string',
                new CheckPayment(),
                new CheckPlatform()
            ],
            'partner_id' => [
                'required',
                'numeric',
                new CheckPartner()]
            ,
            'action' => ['required', 'in:check,payment,telcell', new CheckPartnerPayments($params)]
        ];
    }
}
