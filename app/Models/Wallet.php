<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    public function user(){
    	return $this->belongsTo(User::class);
    }

    public function operator(){
        return $this->belongsTo(User::class, 'operator_id');
    }
}
