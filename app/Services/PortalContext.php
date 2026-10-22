<?php

namespace App\Services;

use Illuminate\Http\Request;

class PortalContext
{
    public static function normalize($portal)
    {
        $aliases = ['buyer' => 'customer', 'salesman' => 'sales', 'delivery_boy' => 'delivery', 'assig' => 'delivery'];
        $portal = is_string($portal) ? ($aliases[$portal] ?? $portal) : null;
        return in_array($portal, ['customer', 'seller', 'admin', 'sales', 'delivery'], true) ? $portal : null;
    }

    public static function host(Request $request)
    {
        $domain = strtolower(config('portals.domain'));
        return ['seller.'.$domain => 'seller', 'admin.'.$domain => 'admin',
            'sales.'.$domain => 'sales', 'assig.'.$domain => 'delivery'][strtolower($request->getHost())] ?? null;
    }

    public static function path($path)
    {
        foreach (['seller' => 'seller', 'admin' => 'admin', 'salesman' => 'sales', 'delivery-boy' => 'delivery'] as $prefix => $portal) {
            if ($path === '/'.$prefix || strpos($path, '/'.$prefix.'/') === 0) {
                return $portal;
            }
        }
        return null;
    }

    public static function resolve(Request $request)
    {
        $host = self::host($request);
        $path = self::path('/'.$request->path());
        $explicit = self::normalize($request->query('portal'));
        $referer = self::referer($request);
        // Sales staff also use authorized /admin handlers, including phone login.
        if ($path) {
            return $path === 'admin' && ($explicit === 'sales' || (!$explicit && ($host === 'sales' || $referer === 'sales'))) ? 'sales' : $path;
        }
        if ($explicit) {
            return $explicit;
        }
        if ($request->is('/')) {
            return $host ?? 'customer';
        }
        if ($request->is('users/*') && !$host) {
            return 'customer';
        }
        return $referer ?? $host ?? ($request->is('login') ? 'seller' : 'customer');
    }

    private static function referer(Request $request)
    {
        // Shared AJAX/form endpoints must use the initiating tab's portal, not
        // whichever account last logged in. Never trust a cross-origin Referer.
        $referer = $request->headers->get('referer');
        if ($referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()
            && (parse_url($referer, PHP_URL_PORT) ?: ($request->isSecure() ? 443 : 80)) === $request->getPort()
            && parse_url($referer, PHP_URL_SCHEME) === $request->getScheme()) {
            parse_str(parse_url($referer, PHP_URL_QUERY) ?: '', $query);
            $path = self::path(parse_url($referer, PHP_URL_PATH) ?: '/');
            $explicit = self::normalize($query['portal'] ?? null);
            $portal = $path === 'admin' && $explicit === 'sales' ? 'sales' : ($path ?? $explicit);
            if ($portal) {
                return $portal;
            }
        }
        return null;
    }

    public static function current()
    {
        return request()->attributes->get('portal') ?? self::resolve(request());
    }

    public static function forUser($user)
    {
        return ['admin' => 'admin', 'staff' => 'admin', 'salesman' => 'sales',
            'seller' => 'seller', 'delivery_boy' => 'delivery'][$user->user_type] ?? 'customer';
    }

    public static function loginUrl()
    {
        $portal = self::current();
        return $portal === 'customer' ? '/users/login' : '/login?portal='.$portal;
    }

    public static function dashboardUrl($user)
    {
        $portal = self::forUser($user);
        if ($portal === 'seller') { return route('seller.dashboard'); }
        if ($portal === 'admin') { return route('admin.dashboard'); }
        return route('dashboard', ['portal' => $portal]);
    }
}
