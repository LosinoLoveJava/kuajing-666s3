<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerSpreadPackageTranslation extends Model
{
    protected $fillable = ['name', 'lang', 'seller_spread_package_id'];

    public function seller_spread_package(){
      return $this->belongsTo(SellerSpreadPackage::class);
    }
}
