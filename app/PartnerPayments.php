<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PartnerPayments
 *
 * @package App
 */
class PartnerPayments extends Model
{
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'partner_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'partner_id', 'payment_id'
    ];
}
