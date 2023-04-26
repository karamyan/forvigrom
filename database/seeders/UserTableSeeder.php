<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert(
            [
                'name' => 'platform',
                'email' => 'support@goodwin.gw',
                'email_verified_at' => now(),
                'password' => bcrypt('Odg4&)(DH&*IU')
            ]
        );
    }
}
