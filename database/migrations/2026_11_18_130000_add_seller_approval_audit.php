<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSellerApprovalAudit extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('shops', 'approval_version')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->unsignedInteger('approval_version')->default(0);
            });
        }
        if (!Schema::hasTable('seller_approval_logs')) {
            Schema::create('seller_approval_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('shop_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('operator_id');
                $table->string('operator_role', 20);
                $table->unsignedTinyInteger('old_status');
                $table->unsignedTinyInteger('new_status');
                $table->unsignedInteger('old_version');
                $table->unsignedInteger('new_version');
                $table->string('action', 20);
                $table->uuid('request_id')->unique();
                $table->string('ip_address', 45)->nullable();
                $table->string('route', 191)->nullable();
                $table->timestamp('created_at');
                $table->index(['shop_id', 'created_at']);
                $table->index(['operator_id', 'created_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('seller_approval_logs');
        if (Schema::hasColumn('shops', 'approval_version')) {
            Schema::table('shops', function (Blueprint $table) { $table->dropColumn('approval_version'); });
        }
    }
}
