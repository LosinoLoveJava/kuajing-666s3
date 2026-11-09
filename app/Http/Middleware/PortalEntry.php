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

        $portal = \App\Services\PortalContext::host($request);
        $portals = [
            'admin' => ['admin', '/admin'],
            'seller' => ['seller', '/seller/dashboard'],
            'sales' => ['salesman', '/dashboard'],
            'delivery' => ['delivery_boy', '/dashboard'],
        ];

        if (!isset($portals[$portal])) {
            return $next($request);
        }

        list($role, $path) = $portals[$portal];
        if ($role === 'delivery_boy' &&
            (!class_exists(\App\Http\Controllers\DeliveryBoyController::class) ||
             !view()->exists('delivery_boys.frontend.dashboard'))) {
            return response('配送员模块暂未启用，请联系平台管理员。', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (!$request->user()) {
            $request->session()->put('link', url($path));
            return redirect(\App\Services\PortalContext::loginUrl());
        }

        $allowed = $role === 'admin' ? ['admin', 'staff', 'salesman'] : [$role];
        if (!in_array($request->user()->user_type, $allowed, true)) {
            return response('当前账号没有此端的访问权限，请使用对应角色的账号登录。', 403)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return redirect($path);
    }
}
