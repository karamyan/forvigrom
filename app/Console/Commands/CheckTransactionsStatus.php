<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Partner;
use App\Payment;
use App\Services\PaymentService\PaymentService;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CheckTransactionsStatus.
 *
 * @package App\Console\Commands
 */
class CheckTransactionsStatus extends Command
{
    protected $signature = 'check_transactions_status';

    /**
     * Check transaction statuses if are pending.
     *
     * @param Request $request
     */
    public function handle(Request $request)
    {
        Cache::lock('check_transactions_status_lock', 180)->get(function () use ($request) {
            $transactions = Transaction::query()
                ->whereIn('status', [TransactionStatus::PENDING, TransactionStatus::PROCESSING])
                ->whereNotIn('payment_method', ['terminal'])
                ->whereBetween('created_at', [Carbon::yesterday(), Carbon::tomorrow()])->get();

            if ($transactions->isNotEmpty()) {
                foreach ($transactions as $transaction) {
                    $partnerPaymentId = Payment::query()->where('id', $transaction->payment_id)->pluck('partner_payment_id')->first();
                    $partnerExternalId = Partner::query()->where('id', $transaction->partner_id)->pluck('external_partner_id')->first();

                    $request->request->set('payment_id', $partnerPaymentId);
                    $request->request->set('partner_id', $partnerExternalId);

                    $paymentService = app(PaymentService::class);

                    try {
                        $paymentService->checkTransactionStatus($transaction);
                    } catch (\Throwable $e) {
                        Log::alert('check_transaction_status', [$e->getMessage()]);
                    }
                }
            }
        });
    }
}
