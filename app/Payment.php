<?php

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'payment_name',
        'has_deposit',
        'has_withdraw',
        'has_terminal',
        'has_mobile_app',
    ];

    /**
     * Get payment configs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function paymentConfigs()
    {
        return $this->hasOne('App\PaymentConfigs');
    }

    /**
     * Get payment whitelisted ips.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getIps()
    {
        return $this->hasMany('App\PaymentIps');
    }

    /**
     * Get the has_deposit.
     *
     * @return string
     */
    public function hasDeposit()
    {
        return $this->has_deposit;
    }

    /**
     * Get the has_withdraw.
     *
     * @return string
     */
    public function hasWithdraw()
    {
        return $this->has_withdraw;
    }

    /**
     * Get the has_mobile_app.
     *
     * @return string
     */
    public function hasMobileApp()
    {
        return $this->has_mobile_app;
    }

    /**
     * Get the has_terminal.
     *
     * @return string
     */
    public function hasTerminal()
    {
        return $this->has_terminal;
    }
}
