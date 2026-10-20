<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Upload extends Model
{
    #use SoftDeletes;

    /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'file_original_name', 'file_name', 'user_id', 'extension', 'type', 'file_size',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return $this->external_link == null ? my_asset($this->file_name) : $this->external_link;
    }

    public function user()
    {
    	return $this->belongsTo(User::class);
    }
}
