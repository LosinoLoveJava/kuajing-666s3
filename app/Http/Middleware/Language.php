<?php

namespace App\Http\Middleware;

use Closure;
use App;
use Session;
use Config;

class Language
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->has('lang') && is_string($request->query('lang')) &&
            \App\Models\Language::where('code', $request->query('lang'))->where('status', 1)->exists()) {
            Session::put('locale', $request->query('lang'));
        }
        if(Session::has('locale')){
            $locale = Session::get('locale');
        }
        else{
            $locale = env('DEFAULT_LANGUAGE','en');
        }

        App::setLocale($locale);
        \Carbon\Carbon::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
