<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('display_name', 50);
            $table->string('payment_name', 50);
            $table->string('handler', 50);
            $table->boolean('has_deposit')->default(true);
            $table->boolean('has_withdraw')->default(true);
            $table->boolean('has_mobile_app')->default(true);
            $table->boolean('has_terminal')->default(true);
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
        Schema::dropIfExists('payments');
    }
}
