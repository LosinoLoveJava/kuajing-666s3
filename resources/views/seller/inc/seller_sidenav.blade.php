<div class="aiz-sidebar-wrap">
    <div class="aiz-sidebar left c-scrollbar">
        <div class="aiz-side-nav-logo-wrap">
            <div class="d-block text-center my-3">
                @if (Auth::user()->shop->logo != null)
                    <img class="mw-100 mb-3" src="{{ uploaded_asset(Auth::user()->shop->logo) }}" class="brand-icon"
                        alt="{{ get_setting('site_name') }}">
                @else
                    <img class="mw-100 mb-3" src="{{ uploaded_asset(get_setting('header_logo')) }}" class="brand-icon"
                        alt="{{ get_setting('site_name') }}">
                @endif
                <h3 class="fs-16  m-0 text-primary">{{ Auth::user()->shop->name }}</h3>
                <p class="text-primary">{{ Auth::user()->email }}</p>
            </div>
        </div>
        <div class="aiz-side-nav-wrap">
            <div class="px-20px mb-3">
                <input class="form-control bg-soft-secondary border-0 form-control-sm text-white" type="text"
                    name="" placeholder="{{ translate('Search in menu') }}" id="menu-search"
                    onkeyup="menuSearch()">
            </div>
            <ul class="aiz-side-nav-list" id="search-menu">
            </ul>
            <ul class="aiz-side-nav-list" id="main-menu" data-toggle="aiz-side-menu">
                <li class="aiz-side-nav-item">
                    <a href="{{ route('seller.dashboard') }}" class="aiz-side-nav-link">
                        <i class="las la-home aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Dashboard') }}</span>
                    </a>
                </li>
                <li class="aiz-side-nav-item">
                    <a href="{{ route('seller.money_withdraw_requests.index') }}"
                        class="aiz-side-nav-link {{ areActiveRoutes(['seller.money_withdraw_requests.index']) }}">
                        <i class="las la-wallet aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('My Wallet') }}</span>
                    </a>
                </li>
                <li class="aiz-side-nav-item">
                    <a href="#" class="aiz-side-nav-link">
                        <i class="las la-shopping-cart aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Products') }}</span>
                        <span class="aiz-side-nav-arrow"></span>
                    </a>
                    <!--Submenu-->
                    <ul class="aiz-side-nav-list level-2">
                        <li class="aiz-side-nav-item">
                            <a href="{{ route('seller.products') }}"
                                class="aiz-side-nav-link {{ areActiveRoutes(['seller.products', 'seller.products.create', 'seller.products.edit']) }}">
                                <span class="aiz-side-nav-text">{{ translate('Products') }}</span>
                            </a>
                        </li>

                        <li class="aiz-side-nav-item">
                            <a href="{{ route('seller.product_bulk_upload.index') }}"
                                class="aiz-side-nav-link {{ areActiveRoutes(['product_bulk_upload.index']) }}">
                                <span class="aiz-side-nav-text">{{ translate('Product Bulk Upload') }}</span>
                            </a>
                        </li>
                        {{-- <li class="aiz-side-nav-item">
                            <a href="{{ route('seller.digitalproducts') }}"
                                class="aiz-side-nav-link {{ areActiveRoutes(['seller.digitalproducts', 'seller.digitalproducts.create', 'seller.digitalproducts.edit']) }}">
                                <span class="aiz-side-nav-text">{{ translate('Digital Products') }}</span>
                            </a>
                        </li> --}}
                        <li class="aiz-side-nav-item">
                            <a href="{{ route('seller.reviews') }}"
                                class="aiz-side-nav-link {{ areActiveRoutes(['seller.reviews']) }}">
                                <span class="aiz-side-nav-text">{{ translate('Product Reviews') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="aiz-side-nav-item">
                    <a href="{{ route('seller.product_storehouse.index') }}"
                       class="aiz-side-nav-link {{ areActiveRoutes(['seller.product_storehouse.index', 'seller.product_storehouse.index']) }}">
                        <i class="las la-store aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Product Storehouse') }}</span>
                    </a>
                </li>
                 <!--订单-->
                <li class="aiz-side-nav-item">
                    <a href="{{ route('seller.orders.index') }}"
                        class="aiz-side-nav-link {{ areActiveRoutes(['seller.orders.index', 'seller.orders.show']) }}">
                        <i class="las la-money-bill aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Orders') }}</span>
                    </a>
                </li>

                  <!--对话-->
                <!-- @if (get_setting('conversation_system') == 1)
                    <li class="aiz-side-nav-item">
                        <a href="{{ route('seller.conversations.index') }}"
                            style="align-items: center"
                            class="aiz-side-nav-link {{ areActiveRoutes(['seller.conversations.index', 'seller.conversations.show']) }}">
                            <i class="las la-comment aiz-side-nav-icon"></i>
                            <span class="aiz-side-nav-text">{{ translate('Conversations') }}</span>
                            <span class="badge badge-danger badge-circle badge-sm badge-dot" id="conversations" style="display: none"> </span>
                        </a>
                    </li>
                @endif -->
                 <!--店铺设置-->
                <li class="aiz-side-nav-item">
                    <a href="{{ route('seller.shop.index') }}"
                        class="aiz-side-nav-link {{ areActiveRoutes(['seller.shop.index']) }}">
                        <i class="las la-cog aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Shop Setting') }}</span>
                    </a>
                </li>

                 <!--优惠券-->
                @if (get_setting('coupon_system') == 1)
                    <li class="aiz-side-nav-item">
                        <a href="{{ route('seller.coupon.index') }}"
                            class="aiz-side-nav-link {{ areActiveRoutes(['seller.coupon.index', 'seller.coupon.create', 'seller.coupon.edit']) }}">
                            <i class="las la-bullhorn aiz-side-nav-icon"></i>
                            <span class="aiz-side-nav-text">{{ translate('Coupon') }}</span>
                        </a>
                    </li>
                @endif

                @if (addon_is_activated('wholesale') && get_setting('seller_wholesale_product') == 1)
                    <li class="aiz-side-nav-item">
                        <a href="{{ route('seller.wholesale_products_list') }}"
                            class="aiz-side-nav-link {{ areActiveRoutes(['wholesale_product_create.seller', 'wholesale_product_edit.seller']) }}">
                            <i class="las la-luggage-cart aiz-side-nav-icon"></i>
                            <span class="aiz-side-nav-text">{{ translate('Wholesale Products') }}</span>
                        </a>
                    </li>
                @endif
                @if (addon_is_activated('auction'))
                    <li class="aiz-side-nav-item">
                        <a href="javascript:void(0);" class="aiz-side-nav-link">
                            <i class="las la-gavel aiz-side-nav-icon"></i>
                            <span class="aiz-side-nav-text">{{ translate('Auction') }}</span>
                            <span class="aiz-side-nav-arrow"></span>
                        </a>
                        <ul class="aiz-side-nav-list level-2">
                            @if (Auth::user()->user_type == 'seller' && get_setting('seller_auction_product') == 1)
                                <li class="aiz-side-nav-item">
                                    <a href="{{ route('auction_products.seller.index') }}"
                                        class="aiz-side-nav-link {{ areActiveRoutes(['auction_products.seller.index', 'auction_product_create.seller', 'auction_product_edit.seller', 'product_bids.seller']) }}">
                                        <span
                                            class="aiz-side-nav-text">{{ translate('All Auction Products') }}</span>
                                    </a>
                                </li>
                                <li class="aiz-side-nav-item">
                                    <a href="{{ route('auction_products_orders.seller') }}"
                                        class="aiz-side-nav-link {{ areActiveRoutes(['auction_products_orders.seller']) }}">
                                        <span
                                            class="aiz-side-nav-text">{{ translate('Auction Product Orders') }}</span>
                                    </a>
                                </li>
                            @endif
                            <li class="aiz-side-nav-item">
                                <a href="{{ route('auction_product_bids.index') }}" class="aiz-side-nav-link">
                                    <span class="aiz-side-nav-text">{{ translate('Bidded Products') }}</span>
                                </a>
                            </li>
                            <li class="aiz-side-nav-item">
                                <a href="{{ route('auction_product.purchase_history') }}"
                                    class="aiz-side-nav-link">
                                    <span class="aiz-side-nav-text">{{ translate('Purchase History') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif
                @if (addon_is_activated('pos_system') && false)
                    @if (get_setting('pos_activation_for_seller') != null && get_setting('pos_activation_for_seller') != 0)
                        <li class="aiz-side-nav-item">
                            <a href="{{ route('poin-of-sales.seller_index') }}"
                                class="aiz-side-nav-link {{ areActiveRoutes(['poin-of-sales.seller_index']) }}">
                                <i class="las la-fax aiz-side-nav-icon"></i>
                                <span class="aiz-side-nav-text">{{ translate('POS Manager') }}</span>
                            </a>
                        </li>
                    @endif
                @endif

                 <!--退款-->
                @if (addon_is_activated('refund_request'))
                    <li class="aiz-side-nav-item">
                        <a href="{{ route('vendor_refund_request') }}"
                            class="aiz-side-nav-link {{ areActiveRoutes(['vendor_refund_request', 'reason_show']) }}">
                            <i class="las la-backward aiz-side-nav-icon"></i>
                            <span class="aiz-side-nav-text">{{ translate('Received Refund Request') }}</span>
                        </a>
                    </li>
                @endif



                <li class="aiz-side-nav-item">
                    <a href="{{ route('seller.payments.index') }}"
                        class="aiz-side-nav-link {{ areActiveRoutes(['seller.payments.index']) }}">
                        <i class="las la-history aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Payment History') }}</span>
                    </a>
                </li>



                <li class="aiz-side-nav-item">
                    <a href="{{ route('seller.commission-history.index') }}" class="aiz-side-nav-link">
                        <i class="las la-file-alt aiz-side-nav-icon"></i>
                        <span class="aiz-side-nav-text">{{ translate('Commission History') }}</span>
                    </a>
                </li>

                    <li class="aiz-side-nav-item">
                        <a href="javascript:void(0);" class="aiz-side-nav-link">
                            <i class="las la-gavel aiz-side-nav-icon"></i>
                            <span class="aiz-side-nav-text">{{ translate('Transaction Password') }}</span>
                            <span class="aiz-side-nav-arrow"></span>
                        </a>
                        <ul class="aiz-side-nav-list level-2">
                                <li class="aiz-side-nav-item">
                                    <a href="/seller/transaction"
                                        class="aiz-side-nav-link ">
                                        <span
                                            class="aiz-side-nav-text">{{ translate('Set Transaction Password') }}</span>
                                    </a>
                                </li>
                        </ul>
                    </li>



            </ul><!-- .aiz-side-nav -->
        </div><!-- .aiz-side-nav-wrap -->
    </div><!-- .aiz-sidebar -->
    <div class="aiz-sidebar-overlay"></div>
</div><!-- .aiz-sidebar -->

<!-- conversations Modal -->
<div id="conversations-modal" class="modal fade">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body text-center">
                <div class="px-3 c-scrollbar-light overflow-auto " style="max-height:300px;">
                    <ul class="list-group list-group-flush">
                        @forelse(Auth::user()->unreadNotifications->take(20) as $notification)
                            <li class="list-group-item d-flex justify-content-between align-items- py-3">
                                <div class="media text-inherit">
                                    <div class="media-body">
                                        @if($notification->type == 'App\Notifications\OrderNotification')
                                            @if($notification->data['order_id'] != 0)
                                                <p class="mb-1 text-truncate-2">
                                                    <a href="{{ route('seller.orders.show', encrypt($notification->data['order_id'])) }}">
                                                        {{translate('Order code: ')}} {{$notification->data['order_code']}} {{ translate('has been '. ucfirst(str_replace('_', ' ', $notification->data['status'])))}}
                                                    </a>
                                                </p>
                                                <small class="text-muted">
                                                    {{ date("F j Y, g:i a", strtotime($notification->created_at)) }}
                                                </small>
                                            @else
                                                <p class="mb-1 text-truncate-2">
                                                    <a href="javascript:void(0);">
                                                        {{$notification->data['order_code']}}
                                                    </a>
                                                </p>
                                                <small class="text-muted">
                                                    {{ date("F j Y, g:i a", strtotime($notification->created_at)) }}
                                                </small>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item">
                                <div class="py-4 text-center fs-16">
                                    {{ translate('No notification found') }}
                                </div>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /.modal -->


<script type="text/javascript">
        @php
            $boolean = Session::get('conversations-modal');
            Session::put('conversations-modal', 0);
            $count = count(Auth::user()->unreadNotifications->take(20));
        @endphp
    var boolean = '{{$boolean}}';
    var count = {{$count}};
    function getConversations() {
        $.ajax( {
            type: "get",
            url: '{{ route('seller.conversations.message_count') }}',
            success: function (data)
            {
                if ( data.result > 0 ) {
                    $( '#conversations' ).show();
                }
                else {
                    $( '#conversations' ).hide();
                }
            }
        } );
    }

    setInterval( function ()
    {
        getConversations()
    }, 10000 )
    window.onload = function(){
        if(boolean==1 && count > 0){
            $('#conversations-modal').modal('show');
        }
    }
</script>



  @if(Auth::user()->user_type == 'seller')
    <script>


        function audioPlay(text) {
            var zhText = text;
            zhText = encodeURI( zhText );
            var audio = "<audio autoplay=\"autoplay\">" + "<source src=\"/public/new.mp3\" type=\"audio/mpeg\">" + "<embed height=\"0\" width=\"0\" src=\"http://tts.baidu.com/text2audio?text=" + zhText + "\">" + "</audio>";
            $( 'body' ).append( audio );
        }

        window.onload = function ()
        {
            setInterval( function ()
            {
                $.get( '{{route('conversations.check_new_msg')}}', {}, function (res)
                {
                    if ( res.code == 1 ) {
                        audioPlay( res.msg );
                    }
                }, 'json' )
            }, '3000' );
        }
    </script>

  @endif
