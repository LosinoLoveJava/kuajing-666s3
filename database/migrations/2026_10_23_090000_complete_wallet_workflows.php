<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CompleteWalletWorkflows extends Migration
{
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->string('request_key', 64)->nullable()->unique();
            $table->string('transaction_reference', 200)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
        Schema::table('seller_withdraw_requests', function (Blueprint $table) {
            $table->string('request_key', 64)->nullable()->unique();
            $table->unsignedInteger('operator_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['request_key']);
            $table->dropColumn(['request_key', 'transaction_reference', 'remarks', 'reviewed_at']);
        });
        Schema::table('seller_withdraw_requests', function (Blueprint $table) {
            $table->dropUnique(['request_key']);
            $table->dropColumn(['request_key', 'operator_id', 'reviewed_at']);
        });
    }
}
