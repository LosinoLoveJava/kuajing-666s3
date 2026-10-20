<div class="aiz-sidebar-wrap">
    <div class="aiz-sidebar left c-scrollbar">
        <div class="aiz-side-nav-avatar_original-wrap">
            <div class="d-block text-center my-3">
                @if (Auth::user()->avatar_original != null)
                    <img class="mw-100 mb-3" src="{{ uploaded_asset(Auth::user()->avatar_original) }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/avatar-place.png') }}';">
                @else
                    <img class="mw-100 mb-3" src="{{ static_asset('assets/img/avatar-place.png') }}" class="image rounded-circle" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/avatar-place.png') }}';">
                @endif
                <h3 class="fs-16  m-0 text-primary">{{ Auth::user()->name }}</h3>
                <p class="text-primary">{{ Auth::user()->email }}</p>
            </div>
        </div>
        <div class="aiz-side-nav-wrap">
            <ul class="aiz-side-nav-list" id="main-menu" data-toggle="aiz-side-menu">
                <li class="aiz-side-nav-item">
                    <a href="{{ route('salesman.sellers_index') }}" class="aiz-side-nav-link {{ areActiveRoutes(['salesman.sellers_index', 'shops.create'])}}">
                        <i class="las la-money-bill aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Seller') }}</span>
                    </a>
                </li>
                <li class="aiz-side-nav-item">
                    <a href="{{ route('salesman.orders.index') }}"
                        class="aiz-side-nav-link {{ areActiveRoutes(['salesman.orders.index', 'salesman.orders.show']) }}">
                        <i class="las la-money-bill aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Orders') }}</span> </a>
                </li>
                <!-- POS Addon-->
                @if (addon_is_activated('pos_system'))
                    @if(get_setting('pos_activation_for_seller') != null && get_setting('pos_activation_for_seller') != 0)
                        <li class="aiz-side-nav-item">
                            <a href="#" class="aiz-side-nav-link">
                                <i class="las la-tasks aiz-side-nav-icon"></i>
                                <span class="aiz-side-nav-text">{{translate('POS System')}}</span>
                                @if (env("DEMO_MODE") == "On")
                                    <span class="badge badge-inline badge-danger">Addon</span>
                                @endif
                                <span class="aiz-side-nav-arrow"></span>
                            </a>
                            <ul class="aiz-side-nav-list level-2">
                                <li class="aiz-side-nav-item">
                                    <a href="{{route('salesman.poin-of-sales.index')}}" class="aiz-side-nav-link {{ areActiveRoutes(['salesman.poin-of-sales.index', 'salesman.poin-of-sales.create'])}}">
                                        <span class="aiz-side-nav-text">{{translate('POS Manager')}}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif
                @endif


            <!-- Offline Payment Addon-->
                @if (addon_is_activated('offline_payment'))
                    @if(Auth::user()->user_type == 'salesman')
                        <li class="aiz-side-nav-item">
                            <a href="#" class="aiz-side-nav-link">
                                <i class="las la-money-check-alt aiz-side-nav-icon"></i>
                                <span class="aiz-side-nav-text">{{translate('Offline Payment System')}}</span>
                                <span class="aiz-side-nav-arrow"></span>
                            </a>
                            <ul class="aiz-side-nav-list level-2">
{{--                                <li class="aiz-side-nav-item">--}}
{{--                                    <a href="{{ route('manual_payment_methods.index') }}" class="aiz-side-nav-link {{ areActiveRoutes(['manual_payment_methods.index', 'manual_payment_methods.create', 'manual_payment_methods.edit'])}}">--}}
{{--                                        <span class="aiz-side-nav-text">{{translate('Manual Payment Methods')}}</span>--}}
{{--                                    </a>--}}
{{--                                </li>--}}
                                <li class="aiz-side-nav-item">
                                    <a href="{{ route('salesman.offline_wallet_recharge_request.index') }}" class="aiz-side-nav-link {{ areActiveRoutes(['salesman.offline_wallet_recharge_request.index'])}}">
                                        <span class="aiz-side-nav-text">{{translate('Offline Wallet Recharge')}}</span>
                                    </a>
                                </li>
                                @if (addon_is_activated('seller_subscription'))
                                    <li class="aiz-side-nav-item">
                                        <a href="{{ route('salesman.offline_seller_package_payment_request.index') }}" class="aiz-side-nav-link {{ areActiveRoutes(['salesman.offline_seller_package_payment_request.index'])}}">
                                            <span class="aiz-side-nav-text">{{translate('Offline Seller Package Payments')}}</span>
                                            @if (env("DEMO_MODE") == "On")
                                                <span class="badge badge-inline badge-danger">Addon</span>
                                            @endif
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif
                @endif
                <li class="aiz-side-nav-item">
                    <a href="{{ route('salesman.customers.index') }}" class="aiz-side-nav-link {{ areActiveRoutes(['salesman.customers.index', 'salesman.customers.create'])}}">
                        <i class="las la-user-friends aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Customers') }}</span>
                    </a>
                </li>


                <li class="aiz-side-nav-item">
                    <a href="{{ route('salesman.withdraw_requests_all') }}" class="aiz-side-nav-link {{ areActiveRoutes(['salesman.sellers.payment_histories', 'salesman.withdraw_requests_all'])}}">
                        <i class="las la-user-friends aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Payout Requests') }}</span>
                    </a>
                </li>

            </ul><!-- .aiz-side-nav -->
        </div><!-- .aiz-side-nav-wrap -->
    </div><!-- .aiz-sidebar -->
    <div class="aiz-sidebar-overlay"></div>
</div><!-- .aiz-sidebar -->
<script type="text/javascript">

    function getConversations(){
        $.ajax({
            type:"get",
            url:'{{ route('seller.conversations.message_count') }}',
            success: function(data){
                if(data.result > 0){
                    $('#conversations').show();
                }else{
                    $('#conversations').hide();
                }
            }
        });
    }
    // setInterval(function (){
    //     getConversations()
    // },1000)

</script>
