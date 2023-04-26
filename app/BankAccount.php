<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $table = 'bank_accounts';

    public $timestamps  = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'client_id',
        'bank_slug',
        'bank_account_id',
        'partner_account_id',
        'status',
        'partner_id',
        'request_data',
        'response_data',
        'callback_response_data'
    ];
}
