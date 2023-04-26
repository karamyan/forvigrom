<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Post(
 *      path="/api/v1/partner/{partner_id}/transactions",
 *      operationId="getTransactionsByPartnerTransactionIds",
 *      tags={"Transactions"},
 *      summary="Get list of transactions",
 *      description="Returns list of transactions by transaction ids.",
 *      security={{"access_token":{}}},
 *      @OA\Parameter(
 *           name="partner_id",
 *           in="path",
 *           required=true,
 *           example=1,
 *           @OA\Schema(
 *                type="integer"
 *           )
 *      ),
 *
 *     @OA\RequestBody(
 *          @OA\JsonContent(
 *              type="object",
 *              @OA\Property(
 *                  property="transaction_ids",
 *                  type="array",
 *                  @OA\Items(
 *                      type="string"
 *                  )
 *              )
 *          ),
 *     ),
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
 *                          property="partner_transaction_id",
 *                          type="array",
 *                          @OA\Items(
 *                              @OA\Property(
 *                                  property="external_id",
 *                                  type="integer",
 *                                  example="1"
 *                              ),
 *                              @OA\Property(
 *                                  property="amount",
 *                                  type="integer",
 *                                  example="1"
 *                              ),
 *                              @OA\Property(
 *                                  property="currency",
 *                                  type="string",
 *                                  example="AMD"
 *                              ),
 *                              @OA\Property(
 *                                  property="internal_transaction_id",
 *                                  type="string",
 *                                  example="7118091483570993"
 *                              ),
 *                              @OA\Property(
 *                                  property="external_transaction_id",
 *                                  type="string",
 *                                  example="8af9cc39-6f1e-49fa-b0a8-be93011be6ee"
 *                              ),
 *                              @OA\Property(
 *                                  property="partner_transaction_id",
 *                                  type="string",
 *                                  example="8E8A8C4E-C25A-45DF-98A7-E354CD60FD69"
 *                              ),
 *                              @OA\Property(
 *                                  property="status",
 *                                  type="string",
 *                                  example="APPROVED"
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
 * @OA\Post(
 *      path="/api/v1/partner/{partner_id}/transactions/{search}",
 *      operationId="getTransactionsByExternalId",
 *      tags={"Transactions"},
 *      summary="Get transaction",
 *      description="Returns transaction by transaction external id.",
 *      security={{"access_token":{}}},
 *      @OA\Parameter(
 *           name="partner_id",
 *           in="path",
 *           required=true,
 *           example=1,
 *           @OA\Schema(
 *                type="integer"
 *           )
 *      ),
 *      @OA\Parameter(
 *           name="search",
 *           in="path",
 *           required=true,
 *           description="transaction external id",
 *           @OA\Schema(
 *                type="string"
 *           )
 *      ),
 *      @OA\Parameter(
 *           name="page",
 *           in="query",
 *           required=false,
 *           description="Number of pagination",
 *           @OA\Schema(
 *                type="integer"
 *           )
 *      ),
 *
 *     @OA\Response(response="default", description="")
 * )
 *
 */
class TransactionController extends Controller
{
    /**
     * Get transactions by partner transaction ids.
     *
     * @param Request $request
     * @param int $partnerId
     * @return JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getTransactionsByPartnerIds(Request $request, int $partnerId): JsonResponse
    {
        $this->validate(request: $request, rules: [
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'string',
        ]);

        $partnerTransactionIds = $request->input('transaction_ids');
        $response = Transaction::query()->whereIn('partner_transaction_id', $partnerTransactionIds)->where('partner_id', $partnerId)->get();

        $result = [];
        foreach ($response as $item) {
            $result[$item->partner_transaction_id] = $this->mappingByKey($item);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Search transactions by external id.
     *
     * @param int $partnerId
     * @param string $externalId
     * @return JsonResponse
     */
    public function searchTransactionsByExternalId(int $partnerId, string $externalId): JsonResponse
    {
        $response = Transaction::query()->where('external_transaction_id', 'LIKE', "%$externalId%")->where('partner_id', $partnerId)->paginate();

        $result = [];
        foreach ($response as $item) {
            $result[$item->partner_transaction_id] = $this->mappingByKey($item);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Mapping transaction data.
     *
     * @param Transaction $item
     * @return array[]
     */
    private function mappingByKey(Transaction $item): array
    {
        return [
                'site_id' => $item->partner_id,
                'amount' => $item->amount,
                'currency' => $item->currency,
                'internal_transaction_id' => $item->internal_transaction_id,
                'external_transaction_id' => $item->external_transaction_id,
                'partner_transaction_id' => $item->partner_transaction_id,
                'status' => TransactionStatus::getName(intval($item->status)),
        ];
    }
}
