<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App;

class SellerSpreadPackage extends Model
{
    protected $guarded = [];

    public function getTranslation($field = '', $lang = false){
        $lang = $lang == false ? App::getLocale() : $lang;
        $seller_spread_package_translation = $this->hasMany(SellerSpreadPackageTranslation::class)->where('lang', $lang)->first();
        return $seller_spread_package_translation != null ? $seller_spread_package_translation->$field : $this->$field;
    }

    public function seller_spread_package_translations(){
      return $this->hasMany(SellerSpreadPackageTranslation::class);
    }

    public function seller_spread_package_payments()
    {
        return $this->hasMany(SelllerPackagePayment::class);
    }

}
