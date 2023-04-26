<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('partner_id')->unsigned();
            $table->bigInteger('payment_id')->unsigned();
            $table->enum('payment_method', ['card_processing', 'card','terminal', 'wallet', 'gateway']);
            $table->enum('type', ['deposit', 'withdraw', 'account_transfer']);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10);
            $table->string('internal_transaction_id', 16)->unique();
            $table->string('external_transaction_id', 100)->nullable(true)->default(null);
            $table->string('partner_transaction_id', 100)->nullable(true)->default(null);
            $table->enum('status', [0, 1, 2, 3, 4, 5, 6])->default(\App\Services\PaymentService\TransactionStatus::NEW);
            $table->json('error_data')->nullable(true)->default(null);
            $table->json('request_data')->nullable(true)->default(null);
            $table->json('response_data')->nullable(true)->default(null);
            $table->json('callback_response_data')->nullable(true)->default(null);
            $table->string('description', 255)->nullable(true)->default(null);
            $table->string('lang', 10)->nullable(true)->default(null);
            $table->index('partner_id');
            $table->foreign('partner_id')->references('id')->on('partners');
            $table->foreign('payment_id')->references('id')->on('payments');
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
        Schema::dropIfExists('transactions');
    }
}
