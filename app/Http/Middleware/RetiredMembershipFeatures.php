<?php

namespace App\Http\Middleware;

use Closure;

class RetiredMembershipFeatures
{
    public function handle($request, Closure $next)
    {
        // Keep route names and historical payment callbacks, but retire purchase entrypoints.
        $retired = [
            'website.guarantee', 'sellers.guarantee_money_modal', 'sellers.setbzj',
            'seller.seller_packages_list', 'seller.packages_payment_list',
            'seller_packages.purchase', 'orders.buy_package_cash',
            'seller.seller_spread_packages_list', 'seller.spread_packages_payment_list',
            'seller_spread_packages.purchase', 'orders.buy_spread_cash', 'seller.products.spread',
            'seller.affiliate.index', 'seller_packages.unpublish_products', 'seller_spread_packages.unpublish_products',
            'customer_packages_list_show', 'customer_packages.purchase',
            'customer_package.make_offline_payment', 'offline_customer_package_purchase_modal',
            'seller.offline_seller_package_purchase_modal',
            'seller.offline_seller_spread_package_purchase_modal',
            'seller.make_offline_payment', 'seller.spread_make_offline_payment', 'sellers.setpackage',
        ];
        abort_if(in_array($request->route()->getName(), $retired, true), 404);
        $user = $request->user();
        abort_if($user && $user->user_type === 'seller' && strpos((string) $request->route()->getName(), 'affiliate.') === 0, 404);
        return $next($request);
    }
}
