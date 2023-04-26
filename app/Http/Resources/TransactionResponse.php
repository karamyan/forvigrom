<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\PaymentService\TransactionStatus;
use Illuminate\Http\Resources\Json\JsonResource;
use JetBrains\PhpStorm\ArrayShape;

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
    #[ArrayShape(["internal_id" => "mixed", "external_id" => "mixed", "partner_id" => "mixed", "amount" => "mixed", "currency" => "mixed", "datetime" => "mixed", "timezone" => "string", "status" => "string", "details" => "mixed"])]
    public function toArray($request): array
    {
        $array = [
            "internal_id" => $this->get('internal_transaction_id'),
            "external_id" => $this->get('external_transaction_id'),
            "partner_id" => $this->get('partner_transaction_id'),
            "amount" => $this->get('amount'),
            "currency" => $this->get('currency'),
            "datetime" => $this->get('created_at'),
            "timezone" => "UTC",
            "status" => intval($this->get('status')),
            "status_name" => TransactionStatus::getName(intval($this->get('status')))
        ];

        if(!empty($this->get('details')))
            $array["details"] = $this->get('details');

        if(!empty($this->get('error_message')))
            $array["error_message"] = $this->get('error_message');

        return $array;
    }
}
