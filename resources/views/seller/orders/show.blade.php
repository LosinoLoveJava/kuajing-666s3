@extends('seller.layouts.app')

@section('panel_content')

    <div class="card">
        <div class="card-header">
            <h1 class="h2 fs-16 mb-0">{{ translate('Order Details') }}</h1>
        </div>

        <div class="card-body">
            <div class="row gutters-2">
                <div class="col text-md-left text-center">
                </div>
                @php
                    $delivery_status = $order->delivery_status;
                    $payment_status = $order->orderDetails->where('seller_id', Auth::user()->id)->first()->payment_status;
                @endphp
                @if (get_setting('product_manage_by_admin') == 0)
                    @if ($order->product_storehouse_total > 0)
                        @if (!$order->product_storehouse_status)
                            <div class="col-md-2 d-flex flex-nowrap justify-content-end align-items-end ml-auto">
                                <button id="payment_for_storehouse" type="button" class="btn btn-primary">{{ translate('Payment For Storehouse') }}</button>
                            </div>
                        @else
                            <div class="col-md-2 d-flex flex-nowrap justify-content-end align-items-end ml-auto">
                                <button type="button" class="btn btn-primary" disabled>{{ translate('Picked up') }}</button>
                            </div>
                        @endif
                    @endif
                    <div class="col-md-3 ml-auto">
                        <label for="update_payment_status">{{ translate('Payment Status') }}</label>
                        @if ($order->payment_type == 'cash_on_delivery' && $payment_status == 'unpaid')
                            <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity"
                                    id="update_payment_status">
                                <option value="unpaid" @if ($payment_status == 'unpaid') selected @endif>
                                    {{ translate('Unpaid') }}</option>
                                <option value="paid" @if ($payment_status == 'paid') selected @endif>
                                    {{ translate('Paid') }}</option>
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ translate($payment_status) }}" disabled>
                        @endif
                    </div>
                    <div class="col-md-3 ml-auto">
                        <label for="update_delivery_status">{{ translate('Delivery Status') }}</label>
                        @if ($delivery_status != 'delivered' && $delivery_status != 'cancelled')
                            <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity"
                                    id="update_delivery_status">
                                <option value="pending" @if ($delivery_status == 'pending') selected @endif>
                                    {{ translate('Pending') }}</option>
                                <option value="confirmed" @if ($delivery_status == 'confirmed') selected @endif>
                                    {{ translate('Confirmed') }}</option>
                                <option value="picked_up" @if ($delivery_status == 'picked_up') selected @endif>
                                    {{ translate('Picked Up') }}</option>
                                <option value="on_the_way" @if ($delivery_status == 'on_the_way') selected @endif>
                                    {{ translate('On The Way') }}</option>
                                <option value="delivered" @if ($delivery_status == 'delivered') selected @endif>
                                    {{ translate('Delivered') }}</option>
                                <option value="cancelled" @if ($delivery_status == 'cancelled') selected @endif>
                                    {{ translate('Cancel') }}</option>
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}" disabled>
                        @endif
                    </div>
                @endif
            </div>









            <div class="row gutters-5 mt-2">
                <div class="col text-md-left text-center">
                    @if(json_decode($order->shipping_address))
                        <address>
                            <strong class="text-main">
                                {{ json_decode($order->shipping_address)->name }}
                            </strong><br>
{{--                            {{ json_decode($order->shipping_address)->email }}--}}
                            @php
                                $emailArray = str_split(json_decode($order->shipping_address)->email);
                                $email = '';
                                foreach ($emailArray as $key => $stock) {
                                    if($key < 3)
                                        $email .= $stock;
                                    else
                                        $email .= "*";

                                }
                                echo $email;
                            @endphp
                            <br>
{{--                            {{ json_decode($order->shipping_address)->phone }}--}}
                            @php
                                $phoneArray = str_split(json_decode($order->shipping_address)->phone);
                                $phone = '';
                                foreach ($phoneArray as $key => $stock) {
                                    if($key < 3)
                                        $phone .= $stock;
                                    else
                                        $phone .= "*";

                                }
                                echo $phone;
                            @endphp
                            <br>
{{--                            {{ json_decode($order->shipping_address)->address }}--}}
                            @php
                                $addressArray = str_split(json_decode($order->shipping_address)->address);
                                $address = '';
                                foreach ($addressArray as $key => $stock) {
                                	$address .= "*";
                                }
                                echo $address;
                            @endphp
                            ,
{{--                            {{ json_decode($order->shipping_address)->city }}--}}
                            @php
                                $cityArray = str_split(json_decode($order->shipping_address)->city);
                                $city= '';
                                foreach ($cityArray as $key => $stock) {
                                	$city .= "*";
                                }
                                echo $city;
                            @endphp
                            ,
{{--                            {{ json_decode($order->shipping_address)->postal_code }}--}}
                            @php
                                $postal_codeArray = str_split(json_decode($order->shipping_address)->postal_code);
                                $postal_code= '';
                                foreach ($postal_codeArray as $key => $stock) {
                                	$postal_code .= "*";
                                }
                                echo $postal_code;
                            @endphp
                            <br>
{{--                            {{ json_decode($order->shipping_address)->country }}--}}
                            @php
                                $countryArray = str_split(json_decode($order->shipping_address)->country);
                                $country= '';
                                foreach ($countryArray as $key => $stock) {
                                	$country .= "*";
                                }
                                echo $country;
                            @endphp
                        </address>
                    @else
                        <address>
                            <strong class="text-main">
                                {{ $order->user->name }}
                            </strong><br>
{{--                            {{ $order->user->email }}--}}
                            @php
                                $emailArray = str_split($order->user->email);
                                $email= '';
                                foreach ($emailArray as $key => $stock) {
                                	if($key < 3)
                                        $email .= $stock;
                                    else
                                        $email .= "*";
                                }
                                echo $email;
                            @endphp
                            <br>
{{--                            {{ $order->user->phone }}--}}
                            @php
                                $phoneArray = str_split($order->user->phone);
                                $phone= '';
                                foreach ($phoneArray as $key => $stock) {
                                	if($key < 3)
                                        $phone .= $stock;
                                    else
                                        $phone .= "*";
                                }
                                echo $phone;
                            @endphp
                            <br>
                        </address>
                    @endif
                    @if ($order->manual_payment && is_array(json_decode($order->manual_payment_data, true)))
                        <br>
                        <strong class="text-main">{{ translate('Payment Information') }}</strong><br>
                        {{ translate('Name') }}: {{ json_decode($order->manual_payment_data)->name }},
                        {{ translate('Amount') }}:
                        {{ single_price(json_decode($order->manual_payment_data)->amount) }},
                        {{ translate('TRX ID') }}: {{ json_decode($order->manual_payment_data)->trx_id }}
                        <br>
                        <a href="{{ uploaded_asset(json_decode($order->manual_payment_data)->photo) }}"
                           target="_blank"><img
                                src="{{ uploaded_asset(json_decode($order->manual_payment_data)->photo) }}" alt=""
                                height="100"></a>
                    @endif
                </div>
                <div class="col-md-4 ml-auto">
                    <table>
                        <tbody>
                        <tr>
                            <td class="text-main text-bold">{{ translate('Order #') }}</td>
                            <td class="text-info text-bold text-right">{{ $order->code }}</td>
                        </tr>
                        <tr>
                            <td class="text-main text-bold">{{ translate('Order Status') }}</td>
                            <td class="text-right">
                                @if ($delivery_status == 'delivered')
                                    <span
                                        class="badge badge-inline badge-success">{{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}</span>
                                @else
                                    <span
                                        class="badge badge-inline badge-info">{{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-main text-bold">{{ translate('Order Date') }}</td>
                            <td class="text-right">{{ date('d-m-Y h:i A', $order->date) }}</td>
                        </tr>
                        <tr>
                            <td class="text-main text-bold">{{ translate('Total amount') }}</td>
                            <td class="text-right">
                                {{ single_price($order->grand_total) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-main text-bold">{{ translate('Payment method') }}</td>
                            <td class="text-right">
                                {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}</td>
                        </tr>

                        <tr>
                            <td class="text-main text-bold">{{ translate('Additional Info') }}</td>
                            <td class="text-right">{{ $order->additional_info }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <hr class="new-section-sm bord-no">
            <div class="row">
                <div class="col-lg-12 table-responsive">
                    <table class="table-bordered aiz-table invoice-summary table">
                        <thead>
                        <tr class="bg-trans-dark">
                            <th data-breakpoints="lg" class="min-col">#</th>
                            <th width="10%">{{ translate('Photo') }}</th>
                            <th class="text-uppercase">{{ translate('Description') }}</th>
                            <th data-breakpoints="lg" class="text-uppercase">{{ translate('Delivery Type') }}</th>
                            <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                                {{ translate('Qty') }}
                            </th>
                            <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                                {{ translate('Price') }}</th>
                            <th data-breakpoints="lg" class="min-col text-uppercase text-right">
                                {{ translate('Total') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($order->orderDetails as $key => $orderDetail)
                            <tr>
                                <td>{{ $key + 1 }}</td>
                                <td>
                                    @if ($orderDetail->product != null && $orderDetail->product->auction_product == 0)
                                        <a href="{{ route('product', $orderDetail->product->slug) }}"
                                           target="_blank"><img height="50"
                                                                src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}"></a>
                                    @elseif ($orderDetail->product != null && $orderDetail->product->auction_product == 1)
                                        <a href="{{ route('auction-product', $orderDetail->product->slug) }}"
                                           target="_blank"><img height="50"
                                                                src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}"></a>
                                    @else
                                        <strong>{{ translate('N/A') }}</strong>
                                    @endif
                                </td>
                                <td>
                                    @if ($orderDetail->product != null && $orderDetail->product->auction_product == 0)
                                        <strong><a href="{{ route('product', $orderDetail->product->slug) }}"
                                                   target="_blank"
                                                   class="text-muted">{{ $orderDetail->product->getTranslation('name') }}</a></strong>
                                        <small>{{ $orderDetail->variation }}</small>
                                    @elseif ($orderDetail->product != null && $orderDetail->product->auction_product == 1)
                                        <strong><a href="{{ route('auction-product', $orderDetail->product->slug) }}"
                                                   target="_blank"
                                                   class="text-muted">{{ $orderDetail->product->getTranslation('name') }}</a></strong>
                                    @else
                                        <strong>{{ translate('Product Unavailable') }}</strong>
                                    @endif
                                </td>
                                <td>
                                    @if ($order->shipping_type != null && $order->shipping_type == 'home_delivery')
                                        {{ translate('Home Delivery') }}
                                    @elseif ($order->shipping_type == 'pickup_point')
                                        @if ($order->pickup_point != null)
                                            {{ $order->pickup_point->getTranslation('name') }}
                                            ({{ translate('Pickup Point') }})
                                        @else
                                            {{ translate('Pickup Point') }}
                                        @endif
                                    @endif
                                </td>
                                <td class="text-center">{{ $orderDetail->quantity }}</td>
                                <td class="text-center">
                                    {{ single_price($orderDetail->price / $orderDetail->quantity) }}</td>
                                <td class="text-center">{{ single_price($orderDetail->price) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="clearfix float-right">
                <table class="table">
                    <tbody>
                    @if ($order->product_storehouse_total > 0)
                    <tr>
                        <td>
                            <strong class="text-muted">{{ translate('Storehouse Price') }} :</strong>
                        </td>
                        <td>
                            {{ single_price($order->product_storehouse_total) }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong class="text-muted">{{ translate('Profit') }} :</strong>
                        </td>
                        <td>
                            {{ single_price($order->grand_total - $order->product_storehouse_total) }}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td>
                            <strong class="text-muted">{{ translate('Sub Total') }} :</strong>
                        </td>
                        <td>
                            {{ single_price($order->orderDetails->sum('price')) }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong class="text-muted">{{ translate('Tax') }} :</strong>
                        </td>
                        <td>
                            {{ single_price($order->orderDetails->sum('tax')) }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong class="text-muted">{{ translate('Shipping') }} :</strong>
                        </td>
                        <td>
                            {{ single_price($order->orderDetails->sum('shipping_cost')) }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong class="text-muted">{{ translate('Coupon') }} :</strong>
                        </td>
                        <td>
                            {{ single_price($order->coupon_discount) }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong class="text-muted">{{ translate('TOTAL') }} :</strong>
                        </td>
                        <td class="text-muted h5">
                            {{ single_price($order->grand_total) }}
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="no-print text-right">
                    <a href="{{ route('seller.invoice.download', $order->id) }}" type="button"
                       class="btn btn-icon btn-light"><i class="las la-print"></i></a>
                </div>
            </div>

        </div>
    </div>


         @if( $express->express_info )
             <?php  date_default_timezone_set("PRC");?>
<style>
    .table {
  border-collapse: collapse;
  margin:20px 0px;
}

.table, th, td {
  border: 2px solid #f0fafe;
}

</style>
                     <table class="table">
                        <tr>
                            <td colspan="2" style="text-align:center; background-color:#f0fafe;"><b>{{ translate('Express information') }} </b> </td>
                        </tr>
                        <tr>
                            <td style="width:100px;"> {{ translate('courier company') }}：</td>
                            <td>  {{ $express->express_name }} </td>
                        </tr>

                        <tr>
                            <td> {{ translate('shipment number') }} ：</td>
                            <td>  {{ $express->express_code }} </td>
                        </tr>


                        <tr>
                            <td colspan="2" style="text-align:center;background-color:#f0fafe;"><b>{{ translate('Logistics tracking information') }}</b></td>

                        </tr>

                         @if( $express->express_info )

                                @foreach ($express->express_info as $key => $ex )
                                 <?php

                                 if( strtotime( $express->express_time[$key] ) < time()   ){ ?>
                          <tr><td colspan="2">     {{ $express->express_stime[$key] }} {{ $ex }}  </td></tr>
                                <?php  } ?>
                              @endforeach


                              @endif



                    </table>
                    @endif


@endsection
@section('modal')
    <!-- Payment For Storehouse Modal -->
    <div class="modal fade" id="payment_for_storehouse_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ translate('Payment For Storehouse') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="order_id" value="{{encrypt($order->id)}}">
                    <div class="row">
                        <div class="col-12">
                            <h5 class="text-center">{{ translate('Pay with wallet')}} {{single_price($order->product_storehouse_total)}}</h5>
                        </div>
                    </div>
                    <div class="row">

                                <div class="col-md-9">
                                    <input type="password" lang="en" class="form-control mb-3" id="tpwd" name="tpwd"
                                   placeholder="{{ translate('Transaction password') }}" max=6 required>
                                </div>
                    </div>
                    <div class="form-group text-right">
                        <button type="button" class="btn btn-sm btn-light transition-3d-hover mr-3" data-dismiss="modal">{{translate('Cancel')}}</button>
                        <button id="payment_button" type="button" class="btn btn-sm btn-primary transition-3d-hover mr-1">{{translate('Payment')}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.bootcss.com/blueimp-md5/2.10.0/js/md5.min.js"></script>
@endsection
@section('script')
    <script type="text/javascript">
        $('#payment_for_storehouse').on('click', function () {
            
             var tpwd = '{{ $tpwd }}'
             if (!tpwd) {
                 location.href="/seller/transaction"
                 return
             } else {
                 $('#payment_for_storehouse_modal').modal('show');
             }
        })
        // 付款
        $('#payment_button').on('click', function () {
            
            var tpwd = '{{ $tpwd }}'
            var pwd = $("#tpwd").val();
            if (md5(pwd) != tpwd) {
                AIZ.plugins.notify('danger',
                        '{{ translate('password error') }}');
                return;
            }
            
            $.post('{{ route('seller.orders.payment_for_storehouse_product') }}', {
                _token: '{{ @csrf_token() }}',
                order_id: '{{encrypt($order->id)}}'
            }, function (data) {
                console.log(data)
                if (data.success == 1) {
                    $('#order_details').modal('hide');
                    AIZ.plugins.notify('success', '{{ translate('Order status has been updated') }}');
                    location.reload().setTimeOut(500);
                } else {
                    AIZ.plugins.notify('danger', data.message ? data.message : '{{ translate('Something went wrong') }}');
                }
            });
        })
        $('#update_delivery_status').on('change', function () {
            var $select = $(this);
            var order_id = {{ $order->id }};
            var status = $select.val();
            $.post('{{ route('seller.orders.update_delivery_status') }}', {
                _token: '{{ @csrf_token() }}',
                order_id: order_id,
                status: status
            }, function (data) {
                if (data == 1 || (data && data.success == 1)) {
                    AIZ.plugins.notify('success', (data && data.message) ? data.message : '{{ translate('Order status has been updated') }}');
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                } else {
                    AIZ.plugins.notify('danger', (data && data.message) ? data.message : '{{ translate('Something went wrong') }}');
                    location.reload();
                }
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : '{{ translate('Something went wrong') }}';
                AIZ.plugins.notify('danger', msg);
                location.reload();
            });
        });

        $('#update_payment_status').on('change', function () {
            var order_id = {{ $order->id }};
            var status = $('#update_payment_status').val();
            $.post('{{ route('seller.orders.update_payment_status') }}', {
                _token: '{{ @csrf_token() }}',
                order_id: order_id,
                status: status
            }, function (data) {
                AIZ.plugins.notify('success', '{{ translate('Payment status has been updated') }}');
                setTimeout(function () {
                    location.reload();
                }, 500);
            });
        });
    </script>
@endsection
