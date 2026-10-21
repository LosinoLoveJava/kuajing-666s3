<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;

/** One definition shared by account metrics and seller reward eligibility. */
class CompletedOrders
{
    public function count(User $user)
    {
        $owner = $user->user_type === 'seller' ? 'seller_id' : 'user_id';
        return Order::where($owner, $user->id)->where('payment_status', 'paid')
            ->where('delivery_status', 'delivered')
            ->whereExists(function ($details) {
                $details->selectRaw('1')->from('order_details')
                    ->whereColumn('order_details.order_id', 'orders.id')
                    ->whereNotExists(function ($refund) {
                        $refund->selectRaw('1')->from('refund_requests')
                            ->whereColumn('refund_requests.order_detail_id', 'order_details.id')
                            ->where('refund_requests.refund_status', 1);
                    });
            })->count();
    }
}
