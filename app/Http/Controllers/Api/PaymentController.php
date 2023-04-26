<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService\PaymentService;
use App\Services\PaymentService\ValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

/**
 *
 * @OA\Post(
 *     path="/api/v1/payments/transactions/deposit",
 *     operationId="doDeposit",
 *     tags={"Deposit"},
 *     summary="Deposit",
 *     description="Deposit",
 *     security={{"access_token":{}}},
 *
 *     @OA\RequestBody(
 *          required = true,
 *          @OA\JsonContent(
 *              type="object",
 *              @OA\Property(
 *                  property="amount",
 *                  type="integer",
 *                  example=1
 *              ),
 *              @OA\Property(
 *                  property="currency",
 *                  type="string",
 *                  example="AMD"
 *              ),
 *              @OA\Property(
 *                  property="partner_id",
 *                  type="integer",
 *                  example=1
 *              ),
 *              @OA\Property(
 *                  property="payment_id",
 *                  type="integer",
 *                  example=1
 *              ),
 *              @OA\Property(
 *                  property="payment_method",
 *                  type="string",
 *                  example="card"
 *              ),
 *              @OA\Property(
 *                  property="partner_transaction_id",
 *                  type="string"
 *              ),
 *              @OA\Property(
 *                  property="description",
 *                  type="string",
 *                  example="deposit"
 *              ),
 *              @OA\Property(
 *                  property="lang",
 *                  type="string",
 *                  example="en"
 *              ),
 *              @OA\Property(
 *                  property="wallet_id",
 *                  type="string",
 *                  example="Your phone number or id"
 *              )
 *          )
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
 *                          property="internal_id",
 *                          type="integer",
 *                          example="8779737423146124"
 *                      ),
 *                      @OA\Property(
 *                          property="external_id",
 *                          type="string",
 *                          example="0dc6a549-9c90-4b93-9ff7-54c4ef373643"
 *                      ),
 *                      @OA\Property(
 *                          property="partner_id",
 *                          type="string",
 *                          example="F664FA43-902D-4735-ADD8-BDC64043E550"
 *                      ),
 *                      @OA\Property(
 *                          property="amount",
 *                          type="integer",
 *                          example="2500"
 *                      ),
 *                      @OA\Property(
 *                          property="currency",
 *                          type="string",
 *                          example="AMD"
 *                      ),
 *                      @OA\Property(
 *                          property="datetime",
 *                          type="string",
 *                          example="2021-10-29T14:41:03.000000Z"
 *                      ),
 *                      @OA\Property(
 *                          property="timezone",
 *                          type="string",
 *                          example="UTC"
 *                      ),
 *                      @OA\Property(
 *                          property="status",
 *                          type="integer",
 *                          example="2"
 *                      ),
 *                      @OA\Property(
 *                          property="status_name",
 *                          type="string",
 *                          example="APPROVED"
 *                      ),
 *                      @OA\Property(
 *                          property="details",
 *                          type="array",
 *                          description="Response from payment provider",
 *                          @OA\Items()
 *                      )
 *                  )
 *              )
 *          )
 *     )
 *
 * )
 *
 * @OA\Post(
 *     path="/api/v1/payments/transactions/withdraw",
 *     operationId="doWithdraw",
 *     tags={"Withdraw"},
 *     summary="Withdraw",
 *     description="Withdraw",
 *     security={{"access_token":{}}},
 *
 *     @OA\RequestBody(
 *          required = true,
 *          @OA\JsonContent(
 *              type="object",
 *              @OA\Property(
 *                  property="amount",
 *                  type="integer",
 *                  example=1
 *              ),
 *              @OA\Property(
 *                  property="currency",
 *                  type="string",
 *                  example="AMD"
 *              ),
 *              @OA\Property(
 *                  property="partner_id",
 *                  type="integer",
 *                  example=1
 *              ),
 *              @OA\Property(
 *                  property="payment_id",
 *                  type="integer",
 *                  example=1
 *              ),
 *              @OA\Property(
 *                  property="payment_method",
 *                  type="string",
 *                  example="wallet"
 *              ),
 *              @OA\Property(
 *                  property="partner_transaction_id",
 *                  type="string"
 *              ),
 *              @OA\Property(
 *                  property="description",
 *                  type="string",
 *                  example="withdraw"
 *              ),
 *              @OA\Property(
 *                  property="lang",
 *                  type="string",
 *                  example="en"
 *              ),
 *              @OA\Property(
 *                  property="wallet_id",
 *                  type="string",
 *                  example="Your phone number or id"
 *              )
 *          )
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
 *                          property="internal_id",
 *                          type="integer",
 *                          example="8779737423146124"
 *                      ),
 *                      @OA\Property(
 *                          property="external_id",
 *                          type="string",
 *                          example="0dc6a549-9c90-4b93-9ff7-54c4ef373643"
 *                      ),
 *                      @OA\Property(
 *                          property="partner_id",
 *                          type="string",
 *                          example="F664FA43-902D-4735-ADD8-BDC64043E550"
 *                      ),
 *                      @OA\Property(
 *                          property="amount",
 *                          type="integer",
 *                          example="2500"
 *                      ),
 *                      @OA\Property(
 *                          property="currency",
 *                          type="string",
 *                          example="AMD"
 *                      ),
 *                      @OA\Property(
 *                          property="datetime",
 *                          type="string",
 *                          example="2021-10-29T14:41:03.000000Z"
 *                      ),
 *                      @OA\Property(
 *                          property="timezone",
 *                          type="string",
 *                          example="UTC"
 *                      ),
 *                      @OA\Property(
 *                          property="status",
 *                          type="integer",
 *                          example="2"
 *                      ),
 *                      @OA\Property(
 *                          property="status_name",
 *                          type="string",
 *                          example="APPROVED"
 *                      ),
 *                      @OA\Property(
 *                          property="details",
 *                          type="array",
 *                          description="Response from payment provider",
 *                          @OA\Items()
 *                      )
 *                  )
 *              )
 *          )
 *     )
 *
 * )
 *
 * @OA\Post(
 *     path="/api/v1/payments/transactions/accountTransfer",
 *     operationId="accountTrasnfer",
 *     tags={"Account trasnfer"},
 *     summary="Account transfer",
 *     description="Transfer money from sport to casino or vice versa.",
 *     security={{"access_token":{}}},
 *
 *     @OA\RequestBody(
 *          required = true,
 *          @OA\JsonContent(
 *              type="object",
 *              @OA\Property(
 *                  property="amount",
 *                  type="integer",
 *                  example=1
 *              ),
 *              @OA\Property(
 *                  property="currency",
 *                  type="string",
 *                  example="AMD"
 *              ),
 *              @OA\Property(
 *                  property="partner_id",
 *                  type="integer",
 *                  example=1
 *              ),
 *              @OA\Property(
 *                  property="payment_id",
 *                  type="integer",
 *                  example=7,
 *                  enum="[7]"
 *              ),
 *              @OA\Property(
 *                  property="payment_method",
 *                  type="string",
 *                  example="transfer"
 *              ),
 *              @OA\Property(
 *                  property="partner_transaction_id",
 *                  type="string"
 *              ),
 *              @OA\Property(
 *                  property="from",
 *                  type="string",
 *                  enum="['sport', 'casino']",
 *                  example="sport"
 *              ),
 *              @OA\Property(
 *                  property="to",
 *                  type="string",
 *                  enum="['sport', 'casino']",
 *                  example="casino"
 *              )
 *          )
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
 *                          property="internal_id",
 *                          type="integer",
 *                          example="8779737423146124"
 *                      ),
 *                      @OA\Property(
 *                          property="external_id",
 *                          type="string",
 *                          example="0dc6a549-9c90-4b93-9ff7-54c4ef373643"
 *                      ),
 *                      @OA\Property(
 *                          property="partner_id",
 *                          type="string",
 *                          example="F664FA43-902D-4735-ADD8-BDC64043E550"
 *                      ),
 *                      @OA\Property(
 *                          property="amount",
 *                          type="integer",
 *                          example="2500"
 *                      ),
 *                      @OA\Property(
 *                          property="currency",
 *                          type="string",
 *                          example="AMD"
 *                      ),
 *                      @OA\Property(
 *                          property="datetime",
 *                          type="string",
 *                          example="2021-10-29T14:41:03.000000Z"
 *                      ),
 *                      @OA\Property(
 *                          property="timezone",
 *                          type="string",
 *                          example="UTC"
 *                      ),
 *                      @OA\Property(
 *                          property="status",
 *                          type="integer",
 *                          example="2"
 *                      ),
 *                      @OA\Property(
 *                          property="status_name",
 *                          type="string",
 *                          example="APPROVED"
 *                      ),
 *                      @OA\Property(
 *                          property="details",
 *                          type="array",
 *                          description="Response from payment provider",
 *                          @OA\Items()
 *                      )
 *                  )
 *              )
 *          )
 *     )
 *
 * )
 *
 */
class PaymentController extends Controller
{
    /**
     * Handling transaction deposit request.
     *
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function deposit(Request $request): mixed
    {
        return Cache::lock('deposit_' . $request->get('partner_transaction_id'), 10)->get(function () use($request) {
            $this->validate(request: $request, rules: ValidationRules::getTransactionRules());

            $paymentService = app(PaymentService::class);

            return $paymentService->doDeposit(body: $request->all());
        });
    }

    /**
     * Handling transaction deposit callback request.
     *
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function depositCallback(Request $request): mixed
    {
        return Cache::lock('depositCallback_' . $request->get('partner_transaction_id'), 10)->get(function () use($request) {
//            $this->validateWithRouteParams(request: $request, rules: ValidationRules::getdepositCallbackRules());

            $paymentService = app(PaymentService::class);

            return $paymentService->doDepositCallback(body: $request->all());
        });
    }

    /**
     * Handling transaction withdraw request.
     *
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function withdraw(Request $request): mixed
    {
        return Cache::lock('withdraw_' . $request->get('partner_transaction_id'), 10)->get(function () use($request) {
            $this->validate(request: $request, rules: ValidationRules::getWithdrawRules());

            $paymentService = app(PaymentService::class);

            return $paymentService->doWithdraw(body: $request->all());
        });
    }

    /**
     * Handling transaction withdraw callback request.
     *
     * @param Request $request
     * @return mixed
     */
    public function withdrawCallback(Request $request): mixed
    {
        return Cache::lock('withdrawCallback_' . $request->get('partner_transaction_id'), 10)->get(function () use($request) {
            $this->validate(request: $request, rules: ValidationRules::getdepositCallbackRules());

            $paymentService = app(PaymentService::class);

            return $paymentService->doWithdrawCallback(body: $request->all());
        });
    }

    /**
     * Handling Account money transfer from sport to casino or from casino to sport.
     *
     * @param Request $request
     * @return mixed
     */
    public function accountTransfer(Request $request): mixed
    {
        return Cache::lock('accountTransfer_' . $request->get('partner_transaction_id'), 10)->get(function () use($request) {
            $this->validate(request: $request, rules: ValidationRules::getAccountTransferRules());

            $paymentService = app(PaymentService::class);

            return $paymentService->doAccountTransfer(body: $request->all());
        });
    }

    /**
     * Handling success after transaction approved.
     *
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function success(Request $request): mixed
    {
        $this->validateWithRouteParams(request: $request, rules: ValidationRules::getdepositCallbackRules());

        $paymentService = app(PaymentService::class);

        return $paymentService->handleSuccess(body: $request->all());
    }

    /**
     * Handling fail after transaction failed.
     *
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function fail(Request $request): mixed
    {
        $this->validateWithRouteParams(request: $request, rules: ValidationRules::getdepositCallbackRules());

        $paymentService = app(PaymentService::class);

        return $paymentService->handleFail(body: $request->all());
    }

    /**
     * @param Request $request
     * @param array $rules
     * @throws ValidationException
     */
    private function validateWithRouteParams(Request $request, array $rules): void
    {
        $params = array_replace_recursive(
            $request->all(),
            $request->route()->parameters()
        );

        $validator = Validator::make($params, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->getMessageBag()->toArray());
        }
    }

}
