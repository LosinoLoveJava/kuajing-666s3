<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerSpreadPackagePayment extends Model
{
    public function user(){
    	return $this->belongsTo(User::class);
    }

    public function seller_spread_package(){
    	return $this->belongsTo(SellerSpreadPackage::class);
    }

    public function products() {
        return $this->hasMany(Product::class);
    }
}
