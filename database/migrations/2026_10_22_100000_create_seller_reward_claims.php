<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateSellerRewardClaims extends Migration
{
    public function up()
    {
        Schema::create('seller_reward_claims', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('milestone', 64);
            $table->unsignedInteger('target_orders');
            $table->unsignedInteger('completed_orders');
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3);
            $table->unsignedInteger('wallet_id')->unique();
            $table->timestamps();
            $table->unique(['user_id', 'milestone']);
        });
    }

    public function down()
    {
        // Deleting paid claims would allow a second payout after re-running this migration.
        if (Schema::hasTable('seller_reward_claims') && DB::table('seller_reward_claims')->exists()) {
            throw new \RuntimeException('Paid seller reward claims must be preserved. Restore code without dropping the financial ledger.');
        }
        Schema::dropIfExists('seller_reward_claims');
    }
}
