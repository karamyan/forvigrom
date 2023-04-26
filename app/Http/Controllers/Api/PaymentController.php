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


class PaymentController extends Controller
{
    /**
     * Handling transaction deposit request.
     *
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function deposit(Request $request): JsonResponse
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
            $this->validateWithRouteParams(request: $request, rules: ValidationRules::getdepositCallbackRules());

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
