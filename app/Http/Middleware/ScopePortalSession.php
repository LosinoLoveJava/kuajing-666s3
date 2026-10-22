<?php

namespace App\Http\Middleware;

use App\Services\PortalContext;
use Closure;

class ScopePortalSession
{
    private $cookie;

    public function __construct()
    {
        $this->cookie = config('session.portal_cookie', config('session.cookie'));
    }

    public function handle($request, Closure $next)
    {
        $portal = PortalContext::resolve($request);
        $request->attributes->set('portal', $portal);
        $request->attributes->set('portal_session_cookie', $this->cookie);
        // Both the name and host boundary are explicit. Old parent-domain
        // cookies and the old unscoped remember cookie are deliberately ignored.
        config(['session.portal_cookie' => $this->cookie, 'session.cookie' => $this->cookie.'_'.$portal, 'session.domain' => null]);
        app('session.store')->setName(config('session.cookie'));
        app('cookie')->setDefaultPathAndDomain(config('session.path'), null,
            config('session.secure'), config('session.same_site'));

        // Persist context in shared navigation URLs, so reloads/bookmarks and
        // subsequent AJAX requests on a single local hostname remain unambiguous.
        $defaultPortal = PortalContext::path('/'.$request->path()) ?? PortalContext::host($request) ??
            ($request->is('login') ? 'seller' : 'customer');
        if ($request->isMethod('GET') && !$request->ajax() && !$request->expectsJson()
            && $request->headers->get('sec-fetch-dest', 'document') === 'document'
            && $portal !== $defaultPortal && !$request->has('portal') && !$request->has('signature')) {
            return redirect()->to($request->fullUrlWithQuery(['portal' => $portal]));
        }

        return $next($request);
    }
}
