<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Class CreditCardResponse.
 *
 * @package App\Http\Resources
 */
class CreditCardResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    #[ArrayShape(["binding_id" => "mixed", "card_info" => "mixed"])]
    public function toArray($request): array
    {
        return [
            "binding_id" => $this->get('binding_id'),
            "card_info" => $this->get('card_info')
        ];
    }
}
