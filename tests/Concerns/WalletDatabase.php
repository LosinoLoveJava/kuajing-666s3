<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait WalletDatabase
{
    protected function createWalletDatabase()
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('user_type'); $t->decimal('balance',20,2)->default(100);
            $t->integer('reputation_score')->default(100); $t->boolean('banned')->default(false);
            $t->string('email')->nullable(); $t->timestamp('email_verified_at')->nullable();
            foreach (['bank_name','bank_acc_name','bank_acc_no','bank_routing_no','usdt_type','usdt_address'] as $field) $t->string($field)->nullable();
            foreach (['cash_on_delivery_status','bank_payment_status','usdt_payment_status'] as $field) $t->integer($field)->default(0);
            $t->timestamps();
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('bzj_money',20,2)->default(0); $t->timestamps();
        });
        Schema::create('wallets', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('amount',20,2);
            $t->string('payment_method')->nullable(); $t->text('payment_details')->nullable();
            $t->integer('approval')->default(0); $t->integer('offline_payment')->default(0);
            $t->string('reciept')->nullable(); $t->integer('type')->default(1); $t->integer('operator_id')->default(0); $t->timestamps();
        });
        Schema::create('seller_withdraw_requests', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('amount',20,2);
            $t->text('message'); $t->integer('status'); $t->integer('viewed');
            $t->integer('type')->default(1); $t->integer('w_type'); $t->integer('t_type')->default(1);
            $t->text('remarks'); $t->text('iban_details')->nullable(); $t->timestamps();
        });
        require_once database_path('migrations/2026_10_23_090000_complete_wallet_workflows.php');
        (new \CompleteWalletWorkflows)->up();
        Schema::create('payments', function (Blueprint $t) {
            $t->increments('id'); $t->integer('seller_id'); $t->decimal('amount',20,2);
            $t->string('payment_method'); $t->text('payment_details')->nullable(); $t->integer('t_type')->default(1); $t->timestamps();
        });
        Schema::create('manual_payment_methods', function (Blueprint $t) {
            $t->increments('id'); $t->string('heading'); $t->string('type')->default('bank');
            $t->text('description')->nullable(); $t->text('bank_info')->nullable(); $t->integer('photo')->nullable(); $t->timestamps();
        });
        DB::table('manual_payment_methods')->insert(['heading' => 'Test bank transfer', 'description' => 'Test transfer instructions']);
        Schema::create('uploads', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->string('type'); });
        Schema::create('translations', function (Blueprint $t) { $t->increments('id'); $t->string('lang'); $t->string('lang_key'); $t->text('lang_value'); $t->timestamps(); });
        Schema::create('business_settings', function (Blueprint $t) { $t->increments('id'); $t->string('type'); $t->text('value')->nullable(); $t->string('lang')->nullable(); });
    }
}
