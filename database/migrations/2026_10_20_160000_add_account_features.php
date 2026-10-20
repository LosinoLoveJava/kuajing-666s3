<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountFeatures extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('reputation_score')->default(100);
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('phone_normalized', 16)->nullable()->unique();
        });
        Schema::table('seller_withdraw_requests', function (Blueprint $table) {
            $table->text('iban_details')->nullable();
        });
        Schema::create('reputation_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('operator_id');
            $table->unsignedTinyInteger('old_score');
            $table->unsignedTinyInteger('new_score');
            $table->string('reason', 500);
            $table->timestamps();
        });
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('phone', 16)->unique();
            $table->string('purpose', 24)->nullable();
            $table->string('code_hash')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('provider_uid', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('phone_verifications');
        Schema::dropIfExists('reputation_logs');
        Schema::table('seller_withdraw_requests', function (Blueprint $table) {
            $table->dropColumn('iban_details');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_normalized']);
            $table->dropColumn(['reputation_score', 'phone_verified_at', 'phone_normalized']);
        });
    }
}
