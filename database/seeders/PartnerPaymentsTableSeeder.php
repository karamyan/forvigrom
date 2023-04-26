<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Class PartnerPaymentsTableSeeder.
 *
 * @package Database\Seeders
 */
class PartnerPaymentsTableSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run()
    {
        DB::table('partner_payments')->insert([
            [
                'payment_id' => 1,
                'partner_id' => 1,
            ],
            [
                'payment_id' => 2,
                'partner_id' => 1,
            ],
            [
                'payment_id' => 3,
                'partner_id' => 1,
            ],
            [
                'payment_id' => 4,
                'partner_id' => 1,
            ],
            [
                'payment_id' => 5,
                'partner_id' => 1,
            ],
            [
                'payment_id' => 6,
                'partner_id' => 1,
            ],
            [
                'payment_id' => 7,
                'partner_id' => 1,
            ]
        ]);
    }
}
