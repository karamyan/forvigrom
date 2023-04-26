<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(PartnerTableSeeder::class);
        $this->call(PaymentTableSeeder::class);
        $this->call(PaymentConfigsTableSeeder::class);
        $this->call(PartnerPaymentsTableSeeder::class);
        $this->call(PaymentIpsTableSeeder::class);
        $this->call(UserTableSeeder::class);
    }
}
