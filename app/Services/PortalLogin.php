<?php

namespace App\Services;

use App\Auth\PortalSessionGuard;
use Illuminate\Http\Request;

class PortalLogin
{
    public function login($user, Request $request, $remember = false)
    {
        $portal = PortalContext::forUser($user);
        if ($portal === PortalContext::current()) {
            auth()->login($user, $remember);
            $request->session()->regenerate();
            return;
        }

        // Legacy public login and administrative impersonation may enter from
        // another portal. Create only the destination session, leaving the
        // administrator/buyer/sales session and its CSRF token untouched.
        $name = $request->attributes->get('portal_session_cookie', config('session.cookie')).'_'.$portal;
        $session = clone $request->session();
        $session->flush();
        $session->setName($name);
        $session->setId($request->cookies->get($name));
        $session->start();
        $target = clone $request;
        $target->attributes = clone $request->attributes;
        $target->attributes->set('portal', $portal);
        $guard = new PortalSessionGuard('web', auth()->guard()->getProvider(), $session, $target);
        $guard->setCookieJar(app('cookie'));
        $guard->setDispatcher(app('events'));
        $guard->login($user, $remember);
        $session->regenerateToken();
        $session->save();
        app('cookie')->queue(app('cookie')->make($name, $session->getId(),
            config('session.expire_on_close') ? 0 : config('session.lifetime'),
            config('session.path'), null, config('session.secure'), config('session.http_only'), false, config('session.same_site')));
    }
}
