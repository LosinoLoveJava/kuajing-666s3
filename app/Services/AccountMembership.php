<?php

namespace App\Services;

use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/** All accounts receive the highest configured benefits, without a paid upgrade. */
class AccountMembership
{
    const PERMANENT_UNTIL = '9999-12-31';

    public function sellerPackage()
    {
        if (!Schema::hasTable('seller_packages')) { return null; }
        return SellerPackage::orderByDesc('amount')->orderByDesc('product_upload_limit')->orderByDesc('id')->first();
    }

    public function customerPackage()
    {
        if (!Schema::hasTable('customer_packages')) { return null; }
        return CustomerPackage::orderByDesc('amount')->orderByDesc('product_upload')->orderByDesc('id')->first();
    }

    public function applyShop(Shop $shop)
    {
        if ($package = $this->sellerPackage()) {
            $shop->seller_package_id = $package->id;
            $shop->product_upload_limit = $package->product_upload_limit;
            $shop->package_invalid_at = self::PERMANENT_UNTIL;
        }
    }

    public function applyCustomer(User $user)
    {
        if ($user->user_type !== 'customer') { return; }
        if ($package = $this->customerPackage()) {
            $user->customer_package_id = $package->id;
            $used = $user->exists && Schema::hasTable('customer_products') ? $user->customer_products()->count() : 0;
            $user->remaining_uploads = max(0, (int) $package->product_upload - $used);
        }
    }

    public function canUpload(User $user, $additional = 1, $publishedOnly = false)
    {
        if ($user->user_type !== 'seller' || !$user->shop || $additional < 0) { return false; }
        $package = $this->sellerPackage();
        if (!$package) { return false; }
        $products = $user->products();
        if ($publishedOnly) { $products->where('published', 1); }
        return $products->count() + $additional <= (int) $package->product_upload_limit;
    }

    public function canUploadClassified(User $user)
    {
        if (!in_array($user->user_type, ['customer', 'seller'], true)) { return false; }
        $package = $this->customerPackage();
        // No customer tiers configured: ordinary account features need no subscription.
        return !$package || $user->customer_products()->count() < (int) $package->product_upload;
    }
}
