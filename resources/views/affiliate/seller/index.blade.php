@extends('seller.layouts.app')

@section('panel_content')
    <section class="py-5">
        <div class="container">
            <div class="d-flex align-items-start">
                <div class="aiz-user-panel">
                    <div class="row gutters-10">
                        <div class="col-md-4 mx-auto mb-3" >
                          <div class="bg-grad-1 text-white rounded-lg overflow-hidden">
                            <span class="size-30px rounded-circle mx-auto bg-soft-primary d-flex align-items-center justify-content-center mt-3">
                                <i class="las la-dollar-sign la-2x text-white"></i>
                            </span>
                            <div class="px-3 pt-3 pb-3">
                                <div class="h4 fw-700 text-center">{{ single_price(Auth::user()->balance) }}</div>
                                <div class="opacity-50 text-center">{{ translate('Affiliate Balance') }}</div>
                            </div>
                          </div>
                        </div>
                        {{--<div class="col-md-4 mx-auto mb-3" >
                            <a href="{{ route('affiliate.payment_settings') }}">
                                <div class="p-3 rounded mb-3 c-pointer text-center bg-white shadow-sm hov-shadow-lg has-transition">
                                    <span class="size-60px rounded-circle mx-auto bg-secondary d-flex align-items-center justify-content-center mb-3">
                                        <i class="las la-dharmachakra la-3x text-white"></i>
                                    </span>
                                    <div class="fs-18 text-primary">{{ translate('Configure Payout') }}</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4 mx-auto mb-3" >
                          <div class="p-3 rounded mb-3 c-pointer text-center bg-white shadow-sm hov-shadow-lg has-transition" onclick="show_affiliate_withdraw_modal()">
                              <span class="size-60px rounded-circle mx-auto bg-secondary d-flex align-items-center justify-content-center mb-3">
                                  <i class="las la-plus la-3x text-white"></i>
                              </span>
                              <div class="fs-18 text-primary">{{  translate('Affiliate Withdraw Request') }}</div>
                          </div>
                        </div>--}}
                    </div>
                    <div class="row">
                        @php
                            $referral_code_url = $url;
                        @endphp
                        <div class="col">
                            <div class="card">
                                <div class="form-box-content p-3">
                                    <div class="form-group">
                                        <textarea id="referral_code_url" class="form-control" readonly type="text" >{{$referral_code_url}}</textarea>
                                    </div>
                                    <button type=button id="ref-cpurl-btn" class="btn btn-primary float-right" data-attrcpy="{{translate('Copied')}}" onclick="copyToClipboard('url')" >{{translate('Copy Url')}}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br>

                    <div class="card">
                        <form class="" id="sort_blogs" action="" method="GET">
                            <div class="card-header row">
                                <div class="col text-center text-md-left">
                                    <h5 class="mb-md-0 h6">{{translate('Affiliate Stats')}}</h5>
                                </div>
                                <div class="col-md-5 col-xl-4">
                                    <div class="input-group mb-0">
                                        <select class="form-control aiz-selectpicker" name="type" data-live-search="true">
                                            <option value="">Choose</option>
                                            <option value="Today" @if($type == 'Today') selected @endif>Today</option>
                                            <option value="7" @if($type == '7') selected @endif>Last 7 Days</option>
                                            <option value="30" @if($type == '30') selected @endif>Last 30 Days</option>
                                        </select>
                                        <button class="btn btn-primary input-group-append" type="submit">{{ translate('Filter') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="card-body">
                            <div class="row gutters-10">
                                <div class="col-md-3 mx-auto mb-3">
                                    <a href="#">
                                        <div class="p-3 rounded mb-3 c-pointer text-center bg-white shadow-sm hov-shadow-lg has-transition">
                                            <span class="size-60px mx-auto d-flex align-items-center justify-content-center mb-3">
                                                <span class="la-3x text-white" style="color: #007bff !important;">
                                                        {{ $statistics['total_seller'] }}
                                                </span>
                                            </span>
                                            <div class="fs-18 text-primary">{{ translate('total seller') }}</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-3 mx-auto mb-3" >
                                    <a href="#">
                                        <div class="p-3 rounded mb-3 c-pointer text-center bg-white shadow-sm hov-shadow-lg has-transition">
                                            <span class="size-60px mx-auto d-flex align-items-center justify-content-center mb-3">
                                                <span class="la-3x text-white" style="color: #007bff !important;">
                                                        {{ $statistics['total_orders'] }}
                                                </span>
                                            </span>
                                            <div class="fs-18 text-primary">{{ translate('total orders') }}</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-3 mx-auto mb-3" >
                                    <a href="#">
                                        <div class="p-3 rounded mb-3 c-pointer text-center bg-white shadow-sm hov-shadow-lg has-transition">
                                            <span class="size-60px mx-auto d-flex align-items-center justify-content-center mb-3">
                                                <span class="la-3x text-white" style="color: #007bff !important;">
                                                        {{ single_price($statistics['total_amount']) }}
                                                </span>
                                            </span>
                                            <div class="fs-18 text-primary">{{ translate('total amount') }}</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-3 mx-auto mb-3" >
                                    <a href="#">
                                        <div class="p-3 rounded mb-3 c-pointer text-center bg-white shadow-sm hov-shadow-lg has-transition">
                                            <span class="size-60px mx-auto d-flex align-items-center justify-content-center mb-3">
                                                <span class="la-3x text-white" style="color: #007bff !important;">
                                                        {{ single_price($statistics['total_brokerage']) }}
                                                </span>
                                            </span>
                                            <div class="fs-18 text-primary">{{ translate('total brokerage') }}</div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <table class="table aiz-table mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ translate('shop name')}}</th>
                                    <th data-breakpoints="lg">{{ translate('Order number')}}</th>
                                    <th data-breakpoints="lg">{{ translate('brokerage amount') }}</th>
                                    <th data-breakpoints="lg">{{ translate('level') }}</th>
                                </thead>
                                <tbody>
                                @foreach($shops as $key => $shop)
                                    <tr>
                                        <td>{{ ($key+1) }}</td>
                                        <td>
                                            {{ $shop['shop_name'] }}
                                        </td>
                                        <td>{{ $shop['order_number'] }}</td>
                                        <td>
                                            {{ single_price($shop['brokerage']) }}
                                        </td>
                                        <td>{{ $shop['level'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0 h6">{{translate('Affiliate Earning History')}}</h5>
                        </div>
                        <div class="card-body">
                            <table class="table aiz-table mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ translate('Referral User')}}</th>
                                    <th>{{ translate('Amount')}}</th>
                                    <th data-breakpoints="lg">{{ translate('Order Id')}}</th>
                                    <th data-breakpoints="lg">{{ translate('Referral Type') }}</th>
                                    <th data-breakpoints="lg">{{ translate('Product') }}</th>
                                    <th data-breakpoints="lg">{{ translate('Date') }}</th>
                                </thead>
                                <tbody>
                                @foreach($affiliate_logs as $key => $affiliate_log)
                                    <tr>
                                        <td>{{ ($key+1) + ($affiliate_logs->currentPage() - 1)*$affiliate_logs->perPage() }}</td>
                                        <td>
                                            @if($affiliate_log->user_id !== null)
                                                {{ $affiliate_log->user->name }}
                                            @else
                                                {{ translate('Guest').' ('. $affiliate_log->guest_id.')' }}
                                            @endif
                                        </td>
                                        <td>{{ single_price($affiliate_log->amount) }}</td>
                                        <td>
                                            @if($affiliate_log->order_id != null)
                                                {{ $affiliate_log->order->code }}
                                            @else
                                                {{ $affiliate_log->order_detail->order->code }}
                                            @endif
                                        </td>
                                        <td> {{ ucwords(str_replace('_',' ', $affiliate_log->affiliate_type)) }}</td>
                                        <td>
                                            @if($affiliate_log->order_detail_id != null)
                                                {{ $affiliate_log->order_detail->product->name }}
                                            @endif
                                                @foreach ($affiliate_log->order->details as $key => $orderDetail)
                                                    <div>
                                                        {{ $orderDetail->product->name }}
                                                    </div>
                                                @endforeach
                                        </td>
                                        <td>{{ $affiliate_log->created_at->format('d, F Y') }} </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="aiz-pagination">
                                {{ $affiliate_logs->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('modal')

    <div class="modal fade" id="affiliate_withdraw_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ translate('Affiliate Withdraw Request') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"></button>
                </div>

                <form class="" action="{{ route('affiliate.withdraw_request.store') }}" method="post">
                    @csrf
                    <div class="modal-body gry-bg px-3 pt-3">
                        <div class="row">
                            <div class="col-md-3">
                                <label>{{ translate('Amount')}} <span class="text-danger">*</span></label>
                            </div>
                            <div class="col-md-9">
                                <input type="number" class="form-control mb-3" name="amount" min="1" max="{{ Auth::user()->affiliate_user->balance }}" placeholder="{{ translate('Amount')}}" required>
                            </div>
                        </div>
                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-sm btn-primary transition-3d-hover mr-1">{{translate('Confirm')}}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection


@section('script')
    <script>
        function copyToClipboard(btn){
            // var el_code = document.getElementById('referral_code');
            var el_url = document.getElementById('referral_code_url');
            // var c_b = document.getElementById('ref-cp-btn');
            var c_u_b = document.getElementById('ref-cpurl-btn');

            // if(btn == 'code'){
            //     if(el_code != null && c_b != null){
            //         el_code.select();
            //         document.execCommand('copy');
            //         c_b .innerHTML  = c_b.dataset.attrcpy;
            //     }
            // }

            if(btn == 'url'){
                if(el_url != null && c_u_b != null){
                    el_url.select();
                    document.execCommand('copy');
                    c_u_b .innerHTML  = c_u_b.dataset.attrcpy;
                }
            }
        }

        function show_affiliate_withdraw_modal(){
            $('#affiliate_withdraw_modal').modal('show');
        }
    </script>
@endsection
