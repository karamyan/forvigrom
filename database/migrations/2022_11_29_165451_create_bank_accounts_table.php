<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBankAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('client_id')->nullable(false)->unsigned();
            $table->string('bank_slug', 32)->nullable(false);
            $table->string('bank_account_id', 32)->nullable(true);
            $table->string('partner_account_id', 32)->nullable(true);
            $table->enum('status', [1, 2, 3, 4])->default(\App\Services\BankAccountService\BankAccountStatus::NEW);
            $table->bigInteger('partner_id')->unsigned();
            $table->json('request_data')->nullable(true)->default(null);
            $table->json('response_data')->nullable(true)->default(null);
            $table->json('callback_response_data')->nullable(true)->default(null);

            $table->unique(array('client_id', 'bank_slug'));
            $table->foreign('partner_id')->references('id')->on('partners');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bank_accounts');
    }
}
