<?php

namespace App;

use App\Services\PaymentService\TransactionStatus;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'partner_id',
        'payment_id',
        'client_id',
        'wallet_id',
        'payment_method',
        'type',
        'amount',
        'currency',
        'internal_transaction_id',
        'external_transaction_id',
        'partner_transaction_id',
        'status',
        'is_notified',
        'error_data',
        'request_data',
        'response_data',
        'callback_response_data',
        'description',
        'lang'
    ];

    public function isCompleted(): bool
    {
        return in_array($this->status, TransactionStatus::COMPLETED_STATUSES);
    }
}
