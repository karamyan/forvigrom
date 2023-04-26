<?php

namespace App\Console\Commands;

use App\PaymentIps;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CacheIPWhitelist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache_ip_whitelist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $items = PaymentIps::query()->get();

        $list = [];
        foreach ($items as $item) {
            $list[$item->payment_id][] = $item->ip;
        }

        Cache::put('IPWhitelist', json_encode($list));
    }
}
