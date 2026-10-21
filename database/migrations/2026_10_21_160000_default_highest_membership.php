<?php

use App\Services\AccountMembership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DefaultHighestMembership extends Migration
{
    public function up()
    {
        DB::transaction(function () {
            $membership = app(AccountMembership::class);
            if ($package = $membership->sellerPackage()) {
                DB::table('seller_packages')->update(['is_default' => 0]);
                DB::table('seller_packages')->where('id', $package->id)->update(['is_default' => 1]);
                DB::table('shops')->whereIn('user_id', DB::table('users')->where('user_type', 'seller')->select('id'))->update([
                    'seller_package_id' => $package->id,
                    'product_upload_limit' => $package->product_upload_limit,
                    'package_invalid_at' => AccountMembership::PERMANENT_UNTIL,
                ]);
            }
            if ($package = $membership->customerPackage()) {
                DB::table('users')->where('user_type', 'customer')->orderBy('id')->chunkById(200, function ($users) use ($package) {
                    foreach ($users as $user) {
                        $used = Schema::hasTable('customer_products') ? DB::table('customer_products')->where('user_id', $user->id)->count() : 0;
                        DB::table('users')->where('id', $user->id)->update([
                            'customer_package_id' => $package->id,
                            'remaining_uploads' => max(0, (int) $package->product_upload - $used),
                        ]);
                    }
                });
            }
        });
    }

    public function down()
    {
        // Data-only entitlement change: do not downgrade accounts or alter financial history.
    }
}
