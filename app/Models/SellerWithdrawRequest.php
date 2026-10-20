<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerWithdrawRequest extends Model
{
    protected $casts = ['iban_details' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
