<?php

namespace App\Jobs;

use App\Services\PlatformApiService\PlatformApiService;
use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class NotifyPlatform extends Notification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(private Transaction $transaction, private array $data, private string $action)
    {
    }

    /**
     * Execute the job.
     *
     * @param PlatformApiService $platformApiService
     * @return mixed
     */
    public function handle(PlatformApiService $platformApiService)
    {
        return Cache::lock('NotifyPlatform_' . $this->transaction->id, 8)->get(function () use ($platformApiService) {
            $actions = [
                'change_status' => 'depositCallback',
                'create_payment' => 'remotePayment',
                'change_payout_status' => 'withdrawCallback',
                'withdraw_callback' => 'payoutCallback',
            ];

            $action = $actions[$this->action];

            $isNotified = Transaction::query()->where('id', $this->transaction->id)->where('is_notified', true)->exists();

            if (!$isNotified) {
                $platformApiService->$action(transaction: $this->transaction, data: $this->data, queueable: false);
            }
        });
    }
}
