<?php

namespace App\Rules;

use App\PartnerPayments;
use App\Payment;
use Illuminate\Contracts\Validation\Rule;

class CheckPartnerPayments implements Rule
{
    /**
     * @var array
     */
    private array $params;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        list($paymentName) = explode('_', $this->params['payment_name']);
        $paymentId = Payment::query()->where('payment_name', $paymentName)->pluck('id')->first();

        return PartnerPayments::query()->join('payments', 'partner_payments.payment_id', '=', 'payments.id')
            ->where('partner_payments.partner_id', $this->params['partner_id'])
            ->where('partner_payments.disabled', 0)
            ->where('payments.id', $paymentId)
            ->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'This payment not available for this partner.';
    }
}
