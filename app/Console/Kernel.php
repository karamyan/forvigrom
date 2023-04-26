<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command("cancel_expire_transactions")
            ->everySixHours();

        $schedule->command("check_transactions_status")
            ->everyThreeMinutes();

        $schedule->command("run_failed_jobs")
            ->everyTenMinutes();

        $schedule->command("cache_ip_whitelist")
            ->everyTenMinutes();

        $schedule->command("change_storage_privileges")
            ->everyMinute();

        $schedule->command("compress_log_files")
            ->tuesdays();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
