<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Class PartnerTableSeeder.
 *
 * @package Database\Seeders
 */
class PartnerTableSeeder extends Seeder
{
    /**
     * Seed the application's Partner table.
     *
     * @return void
     */
    public function run()
    {
        DB::table('partners')->insert(
            [
                'name' => 'SmartBet',
                'return_url' => 'http://10.10.10.132:8080/payment/return_url',
                'notify_url' => 'http://10.10.10.132:8080/payment/notify_url',
                'external_partner_id' => 1
            ]
        );
    }
}
