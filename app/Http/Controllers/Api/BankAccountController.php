<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Http\Resources\BankAccountResource;
use App\Services\BankAccountService\BankAccountService;
use Illuminate\Support\Facades\Log;


class BankAccountController extends Controller
{
    public function clientCheck(BankAccountService $bankAccountService)
    {
        Log::info('account clientCheck', [request()->all()]);
        $bankAccountService = app(BankAccountService::class);

        $response = collect($bankAccountService->clientCheck());

        return (new BankAccountResource($response))->response();
    }

    public function accountCallback(BankAccountService $bankAccountService, $bankSlug)
    {
        Log::info('account accountCallback', [request()->all()]);

        $bankAccountService = app(BankAccountService::class);

        return response()->json($bankAccountService->accountCallback($bankSlug));
    }
}
