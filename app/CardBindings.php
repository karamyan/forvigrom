<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardBindings extends Model
{
    use SoftDeletes;

    /**
     *
     *
     * @var string
     */
    protected $table = 'card_bindings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'client_id', 'binding_id', 'card_info',
    ];

    public $timestamps = true;
}
