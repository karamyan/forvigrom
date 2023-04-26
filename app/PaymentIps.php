<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PaymentIps.
 *
 * @package App
 */
class PaymentIps extends Model
{
    /**
     * @var string
     */
    protected $table = 'payment_ips';

    /**
     * @var bool
     */
    public $timestamps = true;
}
