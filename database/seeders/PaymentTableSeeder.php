<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Class PaymentTableSeeder.
 *
 * @package Database\Seeders
 */
class PaymentTableSeeder extends Seeder
{
    /**
     * Seed the application's Partner table.
     *
     * @return void
     */
    public function run()
    {
        DB::table('payments')->insert([
            [
                'display_name' => 'Arca',
                'payment_name' => 'arca',
                'handler' => 'arca',
                'has_deposit' => true,
                'has_withdraw' => true,
                'has_mobile_app' => true,
                'has_terminal' => true,
            ],
            [
                'display_name' => 'Idram',
                'payment_name' => 'idram',
                'handler' => 'idram',
                'has_deposit' => true,
                'has_withdraw' => true,
                'has_mobile_app' => true,
                'has_terminal' => true,
            ],
            [
                'display_name' => 'Telcell',
                'payment_name' => 'telcell',
                'handler' => 'telcell',
                'has_deposit' => true,
                'has_withdraw' => true,
                'has_mobile_app' => true,
                'has_terminal' => true,
            ],
            [
                'display_name' => 'Easypay',
                'payment_name' => 'easypay',
                'handler' => 'easypay',
                'has_deposit' => false,
                'has_withdraw' => true,
                'has_mobile_app' => true,
                'has_terminal' => true,
            ],
            [
                'display_name' => 'Mobidram',
                'payment_name' => 'mobidram',
                'handler' => 'mobidram',
                'has_deposit' => false,
                'has_withdraw' => false,
                'has_mobile_app' => true,
                'has_terminal' => false,
            ],
            [
                'display_name' => 'Mobidram',
                'payment_name' => 'mobidram2',
                'handler' => 'mobidram',
                'has_deposit' => false,
                'has_withdraw' => false,
                'has_mobile_app' => false,
                'has_terminal' => true,
            ],
            [
                'display_name' => 'Easypay transfer',
                'payment_name' => 'easypay_transfer',
                'handler' => 'easypay',
                'has_deposit' => false,
                'has_withdraw' => false,
                'has_mobile_app' => false,
                'has_terminal' => false,
            ],
        ]);
    }
}
