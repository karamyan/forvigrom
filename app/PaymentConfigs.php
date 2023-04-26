<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class PaymentConfigs extends Model
{
    use Notifiable;

    public $timestamps = false;

    protected $table = 'payment_configs';
}
