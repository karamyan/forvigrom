<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ChangeStoragePrivileges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'change_storage_privileges';

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
        //TODO refactoring this logic.
        exec('chown -R www-data:www-data /var/www/html/storage/logs/');
    }
}
