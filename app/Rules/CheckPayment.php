<?php

namespace App\Rules;

use App\Payment;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class CheckPayment.
 *
 * @package App\Rules
 */
class CheckPayment implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        list($payment) = explode('_', $value, 2);

        return Payment::query()->where('payment_name', $payment)->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Payment does not found.';
    }
}
