<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{

    protected static function booted()
    {
        static::saving(function ($shop) { app(\App\Services\AccountMembership::class)->applyShop($shop); });
    }

    protected $with = [ 'user' ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function seller_package() {
        return $this->belongsTo(SellerPackage::class);
    }

    public function seller_spread_package() {
        return $this->belongsTo(SellerSpreadPackage::class);
    }
}
