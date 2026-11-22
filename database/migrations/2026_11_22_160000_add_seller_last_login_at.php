<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSellerLastLoginAt extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dateTime('last_login_at')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) { $table->dropColumn('last_login_at'); });
        }
    }
}
