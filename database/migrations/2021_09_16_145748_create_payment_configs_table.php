<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('config');
            $table->bigInteger('payment_id')->unsigned();
            $table->bigInteger('partner_id')->unsigned();
            $table->json('deposit_fields')->nullable()->default(null);
            $table->json('deposit_callback_fields')->nullable()->default(null);
            $table->json('withdraw_fields')->nullable()->default(null);
            $table->json('withdraw_callback_fields')->nullable();
            $table->json('terminal_deposit_fields')->nullable()->default(null);
            $table->json('mobile_app_deposit_fields')->nullable()->default(null);
            $table->foreign('payment_id')->references('id')->on('payments');
            $table->foreign('partner_id')->references('id')->on('partners');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_configs');
    }
}
