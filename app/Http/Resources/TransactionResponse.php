<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\PaymentService\TransactionStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class TransactionResponse.
 *
 * @package App\Http\Resources
 */
class TransactionResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
   public function toArray($request): array
    {
        // return transaction nresponse
    }
}
