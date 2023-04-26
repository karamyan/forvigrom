<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\CardBindings;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Get(
 *      path="/api/v1/creditcards",
 *      operationId="getCreditCardTokenList",
 *      tags={"Credit cards"},
 *      summary="Get list of card bindings",
 *      description="Returns list of binding card tokens.",
 *      security={{"access_token":{}}},
 *      @OA\Parameter(
 *           name="client_id",
 *           in="query",
 *           required=true,
 *           example="1",
 *           @OA\Schema(
 *                type="integer"
 *           )
 *      ),
 *      @OA\Parameter(
 *           name="partner_id",
 *           in="query",
 *           required=true,
 *           example="1",
 *           @OA\Schema(
 *                type="integer"
 *           )
 *      ),
 *      @OA\Parameter(
 *           name="payment_id",
 *           in="query",
 *           required=true,
 *           example="1",
 *           @OA\Schema(
 *                type="integer"
 *           )
 *      ),
 *
 *     @OA\Response(
 *          response="200",
 *          description="ok",
 *          @OA\JsonContent(
 *              type="object",
 *              @OA\Property(
 *                  property="data",
 *                  type="array",
 *                  @OA\Items(
 *                      @OA\Property(
 *                          property="token",
 *                          type="string",
 *                          example="8d25740e-43c0-49f4-a633-a615c8a0926q"
 *                      ),
 *                      @OA\Property(
 *                          property="card_info",
 *                          type="array",
 *                          @OA\Items(
 *                              @OA\Property(
 *                                  property="pan",
 *                                  type="string",
 *                                  example="000000**0000"
 *                              ),
 *                              @OA\Property(
 *                                  property="expiration",
 *                                  type="string",
 *                                  example="202306"
 *                              ),
 *                              @OA\Property(
 *                                  property="cardholderName",
 *                                  type="string",
 *                                  example="John Smith"
 *                              )
 *                          )
 *                      )
 *                  )
 *              )
 *          )
 *     )
 *
 * )
 *
 *
 *
 *  * @OA\Delete (
 *      path="/api/v1/creditcards/{token}",
 *      operationId="deleteCreditCardToken",
 *      tags={"Credit cards"},
 *      summary="Delete credit card token",
 *      description="Delete credit card token.",
 *      security={{"access_token":{}}},
 *      @OA\Parameter(
 *           name="token",
 *           in="path",
 *           required=true,
 *           @OA\Schema(
 *                type="string"
 *           )
 *      ),
 *      @OA\Parameter(
 *           name="client_id",
 *           in="query",
 *           required=true,
 *           example="1",
 *           @OA\Schema(
 *                type="integer"
 *           )
 *      ),
 *      @OA\Parameter(
 *           name="partner_id",
 *           in="query",
 *           required=true,
 *           example="1",
 *           @OA\Schema(
 *                type="integer"
 *           )
 *      ),
 *
 *     @OA\Response(
 *          response="200",
 *          description="ok",
 *          @OA\JsonContent(
 *              type="object",
 *              @OA\Property(
 *                  property="data",
 *                  type="array",
 *                  @OA\Items(
 *                      @OA\Property(
 *                          type="boolean",
 *                      )
 *                  )
 *              )
 *          )
 *     )
 *
 * )
 *
 *
 */
class CreditCardController extends Controller
{
    /**
     * List of credit card tokens.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function index(Request $request): JsonResponse
    {
        $this->validate(request: $request, rules: [
            'client_id' => ['required', 'numeric'],
            'partner_id' => ['required', 'numeric'],
            'payment_id' => ['numeric']
        ]);

        $clientId = $request->get('client_id');
        $partnerId = $request->get('partner_id');
        $paymentId = $request->get('payment_id');

        $creditCards = CardBindings::query()->where('client_id', $partnerId . ":" . $clientId)->whereNotNull('binding_id');

        if (!is_null($paymentId))
            $creditCards = $creditCards->where('payment_id', $paymentId)->get();
        else
            $creditCards = $creditCards->get();

        $response = [];
        foreach ($creditCards as $creditCard) {

            $cardInfo = json_decode($creditCard->card_info, true);

            $response[$creditCard->payment_id][] = [
                'binding_id' => $creditCard->binding_id,
                'card_info' => $cardInfo,
            ];
        }

        return response()->json(['data' => $response]);
    }

    /**
     * Delete credit card token.
     *
     * @param Request $request
     * @param $token
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(Request $request, $token): JsonResponse
    {
        $this->validate(request: $request, rules: [
            'client_id' => ['required'],
            'partner_id' => ['required']
        ]);

        $clientId = $request->get('client_id');
        $partnerId = $request->get('partner_id');

        $creditCards = CardBindings::query()->where('client_id', $partnerId . ":" . $clientId)->where('binding_id', $token)->first();

        if (!$creditCards) {
            throw new \Error('Object not found', 404);
        }

        $creditCards->delete();

        return response()->json(['data' => []]);
    }

    /**
     * Checking user has deposit with same card.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function check_card_ownership(Request $request): JsonResponse
    {
        $this->validate(request: $request, rules: [
            'client_id' => ['required'],
            'partner_id' => ['required'],
            'card_number' => ['required'],
        ]);

        $clientId = $request->get('client_id');
        $partnerId = $request->get('partner_id');
        $cardNumber = $request->get('card_number');

        $chars = str_split($cardNumber);
        $pan = $chars[0] . $chars[1] . $chars[2] . $chars[3] . $chars[4] . $chars[5] . '**' . $chars[12] . $chars[13] . $chars[14] . $chars[15];


        $cardExists = CardBindings::withTrashed()
            ->whereJsonContains('card_info', [
                'pan' => $pan
            ])
            ->where('client_id', $partnerId . ':' . $clientId)
            ->exists();

        return response()->json(['data' => [
            'check_res' => $cardExists
        ]]);
    }
}
