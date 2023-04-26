<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Payment;
use App\Services\PaymentService\PaymentService;
use App\Services\PaymentService\ValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Class TerminalController.
 *
 * @package App\Http\Controllers\Api
 */
class TerminalController extends Controller
{
    /**
     * Handle terminal deposit endpoint.
     *
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function index(Request $request): mixed
    {
        $params = $request->route()->parameters();

        $validator = Validator::make($params, ValidationRules::getTerminalRules($params));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $params = explode('_', $request->route('payment_name'));

        $request->request->add([
            'payment_id' => Payment::query()->where('payment_name', $params[0])->pluck('partner_payment_id')->first(),
            'payment_name' => $params[0]
        ]);

        if (!empty($params[1])) {
            if ($params[0] == 'telcell') {
                $request->request->add(['platform' => 'casino']);
            } else {
                Throw new \Error('We does not support sport account.');
                $request->request->add(['platform' => 'sport']);
            }

        } else {
            if ($params[0] == 'telcell') {
                Throw new \Error('We does not support sport account.');
                $request->request->add(['platform' => 'sport']);
            } else {
                $request->request->add(['platform' => 'casino']);
            }

        }

        if (is_null($request->get('partner_id'))) {
            $request->request->add(['partner_id' => 1]);
        }

        $paymentService = app(PaymentService::class);

        return $paymentService->doAppDeposit(array_merge($request->route()->parameters(), $request->all()));
    }
}
