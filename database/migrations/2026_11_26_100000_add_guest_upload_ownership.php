<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGuestUploadOwnership extends Migration
{
    public function up()
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->string('guest_token', 64)->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropIndex(['guest_token']);
            $table->dropColumn('guest_token');
        });
    }
}
