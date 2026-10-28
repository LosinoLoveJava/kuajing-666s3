<?php

use App\Services\ShopViewCounter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class EnableShopViewIncrementByDefault extends Migration
{
    public function up()
    {
        // One-time upgrade: enable existing shops that still have the legacy zero default.
        // Later explicit zero settings remain disabled because migrations run only once.
        DB::table('shops')->where(function ($query) {
            $query->where('view_inc_num', 0)->orWhereNull('view_inc_num');
        })->update(['view_inc_num' => ShopViewCounter::DEFAULT_DAILY_INCREMENT]);
    }

    public function down()
    {
        // Preserve per-shop configuration; original zero values cannot be distinguished
        // from shops that already used the new default before the migration.
    }
}
