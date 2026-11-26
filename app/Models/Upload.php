<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

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

    protected $hidden = ['guest_token'];

    public static function guestToken(Request $request)
    {
        if (!$request->session()->has('upload_guest_token')) {
            $request->session()->put('upload_guest_token', bin2hex(random_bytes(32)));
        }

        return hash('sha256', $request->session()->get('upload_guest_token'));
    }

    public function scopeAccessibleTo($query, Request $request)
    {
        $user = $request->user();
        if ($user) {
            return in_array($user->user_type, ['admin', 'staff'], true)
                ? $query : $query->where('user_id', $user->id);
        }

        // Never treat legacy user_id=0 records as a shared guest library.
        $token = $request->hasSession() ? $request->session()->get('upload_guest_token') : null;
        return $token
            ? $query->where('user_id', 0)->where('guest_token', hash('sha256', $token))
            : $query->whereRaw('1 = 0');
    }

    public function getUrlAttribute()
    {
        if ($this->isPrivate()) {
            return route('uploads.private', ['id' => $this->id, 'portal' => \App\Services\PortalContext::current()]);
        }
        return $this->external_link == null ? my_asset($this->file_name) : $this->external_link;
    }

    public function isPrivate()
    {
        return strpos((string) $this->file_name, 'private/uploads/') === 0;
    }

    public function scopeReadableBy($query, Request $request)
    {
        return $query->where(function ($allowed) use ($request) {
            $allowed->accessibleTo($request);
            $user = $request->user();
            if ($user && !in_array($user->user_type, ['admin', 'staff'], true)) {
                $allowed->orWhereExists(function ($subjects) use ($user) {
                    $subjects->selectRaw('1')->from('users')->where(function ($owner) {
                        $owner->whereColumn('users.identity_card_front', 'uploads.id')
                            ->orWhereColumn('users.identity_card_back', 'uploads.id');
                    })->where(function ($subject) use ($user) {
                        $subject->where('users.id', $user->id);
                        if ($user->user_type === 'salesman') {
                            $subject->orWhere('users.pid', $user->id);
                        }
                    });
                });
            }
        });
    }

    public function user()
    {
    	return $this->belongsTo(User::class);
    }
}
