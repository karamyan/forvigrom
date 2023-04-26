<?php

namespace App\Jobs;

use App\Payment;
use App\Services\PaymentService\PaymentService;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountTransfer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(private array $body, private Transaction $transaction)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle()
    {
        $partnerPaymentId = Payment::query()->where('id', $this->transaction->payment_id)->pluck('partner_payment_id')->first();

        request()->request->set('payment_id', $partnerPaymentId);
        request()->request->set('partner_id', $this->transaction->partner_id);

        $paymentService = app(PaymentService::class);

        try {
            $paymentService->getPaymentProvider()->doAccountTransfer(body: $this->body, transaction: $this->transaction);

            $this->transaction->status = TransactionStatus::APPROVED;
            $this->transaction->save();
        } catch (Throwable $e) {
            Log::alert($e->getMessage(), [$e->getTraceAsString()]);
            // Set transaction status failed in error.
            $this->transaction->error_data = json_encode($e->getMessage());

            throw new $e;
        }
    }
}
