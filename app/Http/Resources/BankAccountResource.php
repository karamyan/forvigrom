<?php

namespace App\Http\Resources;

use App\Services\BankAccountService\BankAccountStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $array = [
            "client_id" => $this->get('client_id'),
            "bank_slug" => $this->get('bank_slug'),
            "account" => $this->get('bank_account_id'),
            "partner_account_id" => $this->get('partner_account_id'),
            "status" => $this->get('status'),
            "status_name" => BankAccountStatus::getName(intval($this->get('status'))),
            "partner_id" => $this->get('partner_id'),
            "created_at" => $this->get('created_at'),
            "updated_at" => $this->get('updated_at'),
        ];

        if (!empty($this->get('details')))
            $array["details"] = $this->get('details');

        return $array;
    }
}
