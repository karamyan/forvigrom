<?php

namespace App\Jobs;

use App\Services\PlatformApiService\PlatformApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class BankAccountNotify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(private array $data, private string $action)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(PlatformApiService $platformApiService)
    {
        return Cache::lock('BankAccountNotify_' . $this->data['partner_account_id'], 8)->get(function () use ($platformApiService) {
            $actions = [
                'bank_account_callback' => 'bankAccountCallback'
            ];

            $action = $actions[$this->action];

            $platformApiService->$action(data: $this->data, queueable: false);
        });
    }
}
