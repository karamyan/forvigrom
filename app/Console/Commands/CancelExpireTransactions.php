<?php

namespace App\Console\Commands;

use App\Payment;
use App\Services\PaymentService\TransactionStatus;
use App\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CancelExpireTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancel_expire_transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the status of cancel transactions for those that have not been approved for more than a day.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Cache::lock('cancel_expire_transactions_lock', 180)->get(function () {
            $payments = Payment::select('id as payment_id', 'deposit_max_available_time')->get();

            foreach ($payments as $payment) {
                if (is_null($payment->deposit_max_available_time)) {
                    continue;
                }

                $endDate = Carbon::now()->subMinutes($payment->deposit_max_available_time)->format('Y-m-d H:i:s');
                $startDate = Carbon::now()->subDays(4)->format('Y-m-d H:i:s');

                try {
                    Transaction::query()
                        ->where('status', TransactionStatus::PENDING)
                        ->where('payment_id', $payment->payment_id)
                        ->where('type', 'deposit')
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->update([
                            'status' => TransactionStatus::CANCELED,
                            'error_data' => json_encode('Canceled from CancelExpireTransactions job')
                        ]);

                    Log::info('cancel_expire_transactions', ['Canceled transaction with payment_id:' . $payment->payment_id . '  those created_at from ' . $startDate . ' to ' . $endDate]);
                } catch (\Throwable $exception) {
                    Log::info('cancel_expire_transactions', [$exception->getMessage()]);
                }
            }
        });
    }
}
