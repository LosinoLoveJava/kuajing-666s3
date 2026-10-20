<?php

namespace App\Http\Middleware;

use Closure;

class PortalEntry
{
    public function handle($request, Closure $next)
    {
        if (!$request->is('/') || !$request->isMethod('GET')) {
            return $next($request);
        }

        $domain = config('portals.domain');
        $portals = [
            'admin.' . $domain => ['admin', '/admin'],
            'seller.' . $domain => ['seller', '/seller/dashboard'],
            'sales.' . $domain => ['salesman', '/dashboard'],
            'assig.' . $domain => ['delivery_boy', '/dashboard'],
        ];

        if (!isset($portals[$request->getHost()])) {
            return $next($request);
        }

        list($role, $path) = $portals[$request->getHost()];
        if ($role === 'delivery_boy' &&
            (!class_exists(\App\Http\Controllers\DeliveryBoyController::class) ||
             !view()->exists('delivery_boys.frontend.dashboard'))) {
            return response('配送员模块暂未启用，请联系平台管理员。', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (!$request->user()) {
            $request->session()->put('link', url($path));
            return redirect($role === 'admin' ? '/login' : '/users/login');
        }

        $allowed = $role === 'admin' ? ['admin', 'staff', 'salesman'] : [$role];
        if (!in_array($request->user()->user_type, $allowed, true)) {
            return response('当前账号没有此端的访问权限，请使用对应角色的账号登录。', 403)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return redirect($path);
    }
}
