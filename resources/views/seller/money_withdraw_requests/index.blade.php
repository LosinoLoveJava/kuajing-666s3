@extends('seller.layouts.app')

@section('panel_content')
@include('partials.wallet_errors')

    <style>
        .seller-wallet-page .wallet-card {
            min-height: 132px;
            border-radius: 6px;
            color: #fff;
            box-shadow: 0 4px 14px rgba(35, 45, 65, .08);
        }
        .seller-wallet-page .wallet-card-action { cursor: pointer; transition: transform .15s ease, box-shadow .15s ease; }
        .seller-wallet-page .wallet-card-action:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(35, 45, 65, .14); }
        .seller-wallet-page .wallet-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .28);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .seller-wallet-page .wallet-card-value { font-size: 24px; line-height: 1.2; font-weight: 700; }
        .seller-wallet-page .wallet-card-label { font-size: 14px; opacity: .92; }
        .seller-wallet-page .wallet-card-balance { background: linear-gradient(135deg, #6c8fdb, #51bde7); }
        .seller-wallet-page .wallet-card-wallet { background: linear-gradient(135deg, #b34bb0, #e83f83); }
        .seller-wallet-page .wallet-card-withdraw { background: linear-gradient(135deg, #5541bb, #8054c7); }
        .seller-wallet-page .wallet-card-recharge { background: linear-gradient(135deg, #f57b58, #f7b52d); }
        .seller-wallet-page .wallet-history-card { border: 1px solid #edf0f5; border-radius: 6px; }
        .seller-wallet-page .wallet-withdraw-table { min-width: 880px; }
        .seller-wallet-page .wallet-withdraw-table .wallet-withdraw-date,
        .seller-wallet-page .wallet-withdraw-table .wallet-withdraw-amount { white-space: nowrap; }
        .seller-wallet-page .wallet-withdraw-method { min-width: 240px; }
        .seller-wallet-page .wallet-withdraw-details { margin-top: .5rem; }
        .seller-wallet-page .wallet-withdraw-details > summary { cursor: pointer; color: #377dff; }
        .seller-wallet-page .wallet-withdraw-details .iban-account-details { margin-bottom: 0 !important; }
        .seller-wallet-page .wallet-withdraw-details dt { overflow-wrap: normal; word-break: normal; }
        .seller-wallet-page .wallet-withdraw-details dd { overflow-wrap: anywhere; word-break: normal; }
        @media (max-width: 991.98px) {
            .seller-wallet-page .wallet-withdraw-table { display: block; min-width: 0; }
            .seller-wallet-page .wallet-withdraw-table > thead {
                position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden;
                clip: rect(0, 0, 0, 0); white-space: nowrap;
            }
            .seller-wallet-page .wallet-withdraw-table > tbody { display: block; }
            .seller-wallet-page .wallet-withdraw-table > tbody > tr {
                display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px 16px; padding: 16px; margin-bottom: 16px;
                border: 1px solid #edf0f5; border-radius: 6px;
            }
            .seller-wallet-page .wallet-withdraw-table > tbody > tr:last-child { margin-bottom: 0; }
            .seller-wallet-page .aiz-table-scroll > .wallet-withdraw-table > tbody > tr > td {
                display: block; min-width: 0; max-width: none; padding: 0; border: 0;
            }
            .seller-wallet-page .wallet-withdraw-table td[data-label]::before {
                content: attr(data-label); display: block; margin-bottom: 4px; color: #6c757d;
                font-size: 12px; white-space: normal; overflow-wrap: normal;
            }
            .seller-wallet-page .wallet-withdraw-table .wallet-withdraw-method,
            .seller-wallet-page .wallet-withdraw-table .wallet-withdraw-notes,
            .seller-wallet-page .wallet-withdraw-table .wallet-withdraw-empty { grid-column: 1 / -1; }
        }
    </style>

    <div class="seller-wallet-page">
    <div class="aiz-titlebar mt-2 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3">{{ translate('My Wallet') }}</h1>
            </div>
        </div>
    </div>

    <div class="row gutters-10">
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="wallet-card wallet-card-balance p-4 d-flex flex-column justify-content-between">
                <span class="wallet-card-icon"><i class="las la-dollar-sign"></i></span>
                <div><div class="wallet-card-value">{{ single_price(Auth::user()->shop->admin_to_pay) }}</div><div class="wallet-card-label">{{ translate('Pending Balance') }}</div></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="wallet-card wallet-card-wallet p-4 d-flex flex-column justify-content-between">
                <span class="wallet-card-icon"><i class="las la-wallet"></i></span>
                <div><div class="wallet-card-value">{{ single_price(Auth::user()->balance) }}</div><div class="wallet-card-label">{{ translate('Wallet Money') }}</div></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="wallet-card wallet-card-withdraw wallet-card-action p-4 d-flex flex-column justify-content-between" onclick="show_request_modal()">
                <span class="wallet-card-icon"><i class="las la-arrow-down"></i></span>
                <div class="wallet-card-label">{{ translate('Send Withdraw Request') }}</div>
            </div>
        </div>
        @if (addon_is_activated('offline_payment'))
            <div class="col-12 col-sm-6 col-xl-3 mb-3">
                <div class="wallet-card wallet-card-recharge wallet-card-action p-4 d-flex flex-column justify-content-between" onclick="show_make_wallet_recharge_modal(1)">
                    <span class="wallet-card-icon"><i class="las la-wallet"></i></span>
                    <div class="wallet-card-label">{{ translate('Offline Recharge Wallet') }}</div>
                </div>
            </div>
        @endif
    </div>

    <div class="card wallet-history-card" id="wallet-history">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ __('seller_wallet.history') }}</h5>
        </div>
        <div class="card-body">
            <div class="aiz-table-scroll" tabindex="0">
                <table class="table aiz-table mb-0">
                    <thead><tr>
                        <th>#</th>
                        <th>{{ translate('Date') }}</th>
                        <th>{{ translate('Type') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Payment method') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($walletHistory as $key => $entry)
                        <tr data-history-source="{{ $entry->source }}" data-history-id="{{ $entry->id }}">
                            <td>{{ $walletHistory->firstItem() + $key }}</td>
                            <td>{{ date('d-m-Y H:i', strtotime($entry->created_at)) }}</td>
                            <td>{{ $entry->source === 'deduction' ? __('seller_balance.title') : __('seller_wallet.'.$entry->source) }}</td>
                            <td>{{ single_price($entry->amount) }}</td>
                            <td>
                                @if ($entry->source === 'deduction')
                                    <span class="badge badge-inline badge-success">{{ __('seller_balance.completed') }}</span>
                                @elseif ($entry->source === 'recharge')
                                    @include('partials.wallet_status', ['approval' => $entry->status])
                                @elseif ((int) $entry->status === 1)
                                    <span class="badge badge-inline badge-success">{{ translate('Paid') }}</span>
                                @elseif ((int) $entry->status === 2)
                                    <span class="badge badge-inline badge-danger">{{ translate('Refuse') }}</span>
                                @else
                                    <span class="badge badge-inline badge-info">{{ translate('Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($entry->source === 'withdrawal')
                                    {{ [1 => translate('Cash'), 2 => translate('Bank'), 3 => 'USDT', 4 => translate('Bank'), 5 => __('crypto_wallet.crypto')][(int) $entry->payment_method] ?? '' }}
                                @elseif ($entry->source === 'deduction')
                                    {{ __('seller_balance.admin_debit') }}
                                @else
                                    {{ $entry->payment_method === 'IBAN' ? translate('Bank') : translate($entry->payment_method ?? '') }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ translate('Nothing found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="aiz-pagination">{{ $walletHistory->links() }}</div>
        </div>
    </div>

    <div class="card wallet-history-card" id="wallet-withdraw-history">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Withdraw Request history')}}</h5>
        </div>
        <div class="card-body">
            <div class="aiz-table-scroll" tabindex="0">
            <table class="table wallet-withdraw-table mb-0" role="table">
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">{{ translate('Date') }}</th>
                    <th scope="col">{{ translate('Amount')}}</th>
                    <th scope="col">{{ translate('Type')}}</th>
                    
                    <th scope="col">{{ translate('Status')}}</th>
                    <th scope="col">{{ translate('Withdraw Type')}}</th>
                    <th scope="col">{{ translate('Remarks')}}</th>
                    <th scope="col">{{ translate('Message')}}</th>
                    
                    
                </tr>
                </thead>
                <tbody>
                @forelse ($seller_withdraw_requests as $key => $seller_withdraw_request)
                    <tr>
                        <td data-label="#">{{ $seller_withdraw_requests->firstItem() + $key }}</td>
                        <td data-label="{{ translate('Date') }}" class="wallet-withdraw-date">{{ date('d-m-Y', strtotime($seller_withdraw_request->created_at)) }}</td>
                        <td data-label="{{ translate('Amount') }}" class="wallet-withdraw-amount">{{ single_price($seller_withdraw_request->amount) }}</td>
                        <td data-label="{{ translate('Type') }}">
                            @if( $seller_withdraw_request->type == 1)
                            
                            {{translate('User Balance')}}
                            @else
                            
                              {{translate('Guarantee')}}
                            @endif
                        </td>
                        <td data-label="{{ translate('Status') }}">
                            @if ($seller_withdraw_request->status == 1)
                                <span class=" badge badge-inline badge-success">{{ translate('Paid')}}</span>
                             @elseif ($seller_withdraw_request->status == 2)
                                <span class=" badge badge-inline badge-danger">{{ translate('Refuse')}} </span>
                            @else
                                <span class=" badge badge-inline badge-info">{{ translate('Pending')}}</span>
                            @endif
                        </td>
                        <td data-label="{{ translate('Withdraw Type') }}" class="wallet-withdraw-method">
                            
                            @if( $seller_withdraw_request->w_type == 1)
                            
                            {{translate('Cash')}}
                            @elseif( $seller_withdraw_request->w_type == 2)
                            
                              {{translate('Bank')}}
                            @elseif( $seller_withdraw_request->w_type == 3)
                            {{translate('USDT')}}
                            @elseif($seller_withdraw_request->w_type == 5)
                            {{ $seller_withdraw_request->iban_details['method'] ?? __('crypto_wallet.crypto') }}
                            @elseif($seller_withdraw_request->w_type == 4)
{{ translate('Bank') }}
@endif
                            @if(in_array((int) $seller_withdraw_request->w_type, [4, 5], true))
                                <details class="wallet-withdraw-details">
                                    <summary>{{ __('seller_wallet.account_details') }}</summary>
                                    @include('partials.iban_details')
                                </details>
                            @endif
                        </td>
                             <td data-label="{{ translate('Remarks') }}" class="wallet-withdraw-notes">
                            {{ $seller_withdraw_request->remarks }}
                        </td>
                        <td data-label="{{ translate('Message') }}" class="wallet-withdraw-notes">
                            {{ $seller_withdraw_request->message }}
                        </td>
                   
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted wallet-withdraw-empty">{{ translate('Nothing found') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            <div class="aiz-pagination">
                {{ $seller_withdraw_requests->links() }}
            </div>
        </div>
    </div>

    <!-- 待解冻订单 -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Froze Order')}}</h5>
        </div>
        <div class="card-body">
            <div class="aiz-table-scroll" tabindex="0">
            <table class="table aiz-table mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ translate('Order Code') }}</th>
                    <th data-breakpoints="md">{{ translate('Amount') }}</th>
                    <th data-breakpoints="md">{{ translate('Profit') }}</th>
                    <th data-breakpoints="md">{{ translate('Payment Status') }}</th>
                    <th data-breakpoints="md">{{ translate('Pick Up Status') }}</th>
                    <th>{{ translate('Date') }}</th>
                    <th>{{ translate('Unfreeze Countdown') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($freezeOrders as $key => $order)
                    <tr>
                        <td>{{ $freezeOrders->firstItem() + $key }}</td>
                        <td>{{ $order->code }}</td>
                        <td>{{ single_price($order->grand_total) }}</td>
                        <td>{{ single_price($order->grand_total - $order->product_storehouse_total) }}</td>
                        <td>
                            @if ($order->payment_status == 'paid')
                                <span class="badge badge-inline badge-success">{{translate('Paid')}}</span>
                            @else
                                <span class="badge badge-inline badge-danger">{{translate('Unpaid')}}</span>
                            @endif
                        </td>
                        <td>
                            @if ($order->product_storehouse_status)
                                <span class="badge badge-inline badge-success">{{translate('Picked Up')}}</span>
                            @else
                                <span class="badge badge-inline badge-danger">{{translate('Unpicked Up')}}</span>
                            @endif
                        </td>
                        <td>{{ date('d-m-Y', strtotime($order->created_at)) }}</td>
                        <td>
                            @if ($order->freeze_expired_at)
                                {{ round(($order->freeze_expired_at - now()->timestamp) / 86400) }} {{translate('Days')}}
                            @else
                                {{translate('Unpicked Up')}}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ translate('Nothing found') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            <div class="aiz-pagination">
                {{ $freezeOrders->links() }}
            </div>
        </div>
    </div>

    <!-- 充值记录 -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ __('seller_wallet.recharge_history') }}</h5>
        </div>
        <div class="card-body">
            <div class="aiz-table-scroll" tabindex="0">
            <table class="table aiz-table mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th data-breakpoints="md">{{ translate('Amount') }}</th>
                    <th data-breakpoints="md">{{ translate('Payment method') }}</th>
                    <th>{{ translate('Payment Details') }}</th>
                    <th data-breakpoints="md">{{ translate('Approval') }}</th>
                    <th data-breakpoints="md">{{ translate('Offline payment') }}</th>
                    <th data-breakpoints="md">{{ translate('Type') }}</th>
                    <th data-breakpoints="md">{{ translate('Receipt') }}</th>
                    <th>{{ translate('Date') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rechargeList as $key => $list)
                    <tr>
                        <td>{{ $rechargeList->firstItem() + $key }}</td>
                        <td>{{ single_price($list->amount) }}</td>
                        <td>{{ $list->payment_method === 'IBAN' ? translate('Bank') : $list->payment_method }}</td>
                        <td>{{ $list->payment_details }}</td>
                        <td>
                            @include('partials.wallet_status', ['approval' => $list->approval])
                            <div class="small text-muted">{{ $list->remarks }}</div>
                        </td>
                        <td>
                            @if ($list->offline_payment == 1)
                                <span class="badge badge-inline badge-success">{{translate('yes')}}</span>
                            @else
                                <span class="badge badge-inline badge-danger">{{translate('No')}}</span>
                            @endif
                        </td>
                        
                         <td>
                            @if( $list->type == 1)
                            
                            {{translate('User Balance')}}
                            @else
                            
                              {{translate('Guarantee')}}
                            @endif
                        </td>
                        
                        
                        <td>@if($list->reciept)<a href="{{ uploaded_asset($list->reciept) }}" target="_blank" rel="noopener">{{ translate('Receipt') }}</a>@endif</td>
                        <td>{{ date('d-m-Y', strtotime($list->created_at)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">{{ translate('Nothing found') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            <div class="aiz-pagination">
                {{ $rechargeList->links() }}
            </div>
        </div>
    </div>
    
    
    
    
      <!-- 充值记录 -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Payment History')}}</h5>
        </div>
        <div class="card-body">
            <div class="aiz-table-scroll" tabindex="0">
            <table class="table aiz-table mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th data-breakpoints="md">{{ translate('Amount') }}</th>
                
                    <th>{{ translate('Payment Details') }}</th>
         
                    <th data-breakpoints="md">{{ translate('Payment method') }}</th>
                    
                  
                    <th>{{ translate('Date') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($paymentList as $key => $list)
                    <tr>
                        <td>{{ $paymentList->firstItem() + $key }}</td>
                        <td>{{ single_price($list->amount) }}</td>
                    
                        <td>{{ $list->payment_details }}</td>
                        
                        <td>
                            {{translate($list->payment_method)}}
                        </td>
                        
                      
                        
                        
                      
                        <td>{{ date('d-m-Y', strtotime($list->created_at)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">{{ translate('Nothing found') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            <div class="aiz-pagination">
                {{ $paymentList->links() }}
            </div>
        </div>
    </div>
    
    
    </div>
@endsection

@section('modal')
    <!-- offline payment Modal -->
    <div class="modal fade" id="offline_wallet_recharge_modal" tabindex="-1" role="dialog"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">
                        {{ translate('Offline Recharge Wallet') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
                </div>
                <div id="offline_wallet_recharge_modal_body"></div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="request_modal" tabindex="-1" role="dialog" aria-labelledby="withdraw-modal-title"
         aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="withdraw-modal-title">{{ translate('Send A Withdraw Request') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
                </div>
                @if ($balance >= 0.01)
                    <form class="" action="{{ route('seller.money_withdraw_request.store') }}" method="post">
                        @csrf
                        <input type="hidden" name="wallet_form" value="withdraw">
                        <input type="hidden" name="request_token" value="{{ old('request_token', (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="modal-body p-4" style="max-height:calc(100vh - 180px);overflow-y:auto">
                            <div id="withdraw-bank-market" class="d-flex align-items-center mb-4">
                                <img src="{{ static_asset('assets/img/currencies/try.svg') }}" width="22" height="22" class="mr-2" alt="TRY"> {{ translate('Turkey') }}
                            </div>
                            <input type="hidden" name="withdrawal_market" value="TR">
                            <input type="hidden" name="iban_country" value="TR">
                            <div class="row">
                                <div class="col">
                                    <div class="alert alert-success" role="alert">
                                        <h6>{{ translate('Your wallet balance :') }} ${{ $balance }}</h6>
                                    </div>
                                    
                                    
                                </div>
                            </div>
                            <input type="hidden" name="type" value="1">
                            <div class="form-group"><label for="p">{{ translate('Withdraw Type') }}</label>
                                <input type="hidden" name="w_type" value="4">
                                <select class="form-control" id="p"><option value="4">{{ __('crypto_wallet.bank') }}</option><option value="5">{{ __('crypto_wallet.crypto') }}</option></select>
                            </div>
                            <div id="withdraw-crypto-fields" hidden>
                                <fieldset class="form-group"><legend class="fs-14">{{ __('crypto_wallet.asset') }}</legend><div class="row">@include('partials.crypto_asset_options', ['deposit' => false])</div></fieldset>
                                <div class="form-group"><label for="withdraw-crypto-address">{{ __('crypto_wallet.withdraw_address') }} *</label><input id="withdraw-crypto-address" name="crypto_address" class="form-control" value="{{ old('crypto_address') }}" maxlength="128" dir="ltr" autocomplete="off" spellcheck="false" disabled></div>
                                <div class="alert alert-info">{{ __('crypto_wallet.withdraw_hint') }}</div>
                                <div id="withdraw-crypto-unavailable" class="alert alert-warning" hidden>{{ __('crypto_wallet.unavailable') }}</div>
                            </div>
                            <div id="withdraw-country-panel">
                                @include('partials.iban_form', ['fixedBank' => true, 'manualReview' => true, 'bankOnly' => true])
                                <div class="form-group" id="turkey-iban-field" hidden>
                                    <label for="withdraw-turkey-iban">IBAN ({{ translate('Optional') }})</label>
                                    <input id="withdraw-turkey-iban" class="form-control" dir="ltr" name="iban_number" value="{{ old('iban_number') }}" maxlength="2000" autocomplete="off" aria-describedby="withdraw-iban-hint" disabled>
                                    <small id="withdraw-iban-hint" class="form-text text-muted">{{ __('seller_wallet.iban_hint') }}</small>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="withdraw-amount">{{ translate('Amount')}} (USD) <span class="text-danger">*</span></label>
                                <input id="withdraw-amount" type="number" lang="en" dir="ltr" class="form-control" name="amount" value="{{ old('amount') }}" min="0.01" max="9999999999.99" step="0.01" placeholder="{{ translate('Amount') }}" required>
                                <div class="mt-2 text-muted">{{ translate('Exchange rate') }}: <bdi dir="ltr" id="withdraw-rate"></bdi></div>
                                <div class="mt-1 text-primary" aria-live="polite">{{ translate('Estimated amount') }}: <bdi dir="ltr">≈ <span id="withdraw-estimate">0.00</span> <span id="withdraw-currency">TRY</span></bdi></div>
                            </div>

                            <div class="form-group">
                                <label for="withdraw-message">{{ translate('Message')}}</label>
                                <textarea id="withdraw-message" name="message" rows="5" class="form-control">{{ old('message') }}</textarea>
                            </div>
                            <div style="text-align: end">
                                <button type="submit" class="btn btn-sm btn-primary">{{translate('Send')}}</button>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="modal-body gry-bg px-3 pt-3">
                        <div class="p-5 heading-3">
                            {{ translate('You do not have enough balance to send withdraw request') }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script')
@include('partials.wallet_script')
    <script type="text/javascript">
        function show_request_modal() {
            $('#request_modal').modal('show');
        }

        function show_message_modal(id) {
            $.post('{{ route('withdraw_request.message_modal') }}', {
                _token: '{{ @csrf_token() }}',
                id: id
            }, function (data) {
                $('#message_modal .modal-content').html(data);
                $('#message_modal').modal('show', {backdrop: 'static'});
            });
        }
        function show_make_wallet_recharge_modal(type){
            $.post('{{ route('offline_wallet_recharge_modal', ['portal' => 'seller']) }}', {type:type,_token:'{{ csrf_token() }}'}, function(data){
                $('#offline_wallet_recharge_modal_body').html(data);
                $('#offline_wallet_recharge_modal').modal('show');
            }).fail(function () { AIZ.plugins.notify('danger', @json(translate('Unable to load the recharge form. Please try again.'))); });
        }

        (function () {
            var form = document.querySelector('#request_modal form');
            if (!form) return;
            var amount = form.elements.amount;
            var output = document.getElementById('withdraw-estimate');
            var locales = {cn: 'zh-CN', hk: 'zh-HK', sa: 'ar-SA', bd: 'bn-BD', jp: 'ja-JP', kr: 'ko-KR', in: 'hi-IN', be: 'be-BY'};
            var locale = @json(app()->getLocale());
            var numberFormat = new Intl.NumberFormat(locales[locale] || locale.replace('_', '-'), {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
            var rates = @json(app(\App\Services\ExchangeRate::class)->availableRates());
            var cryptoAssets = @json(\App\Services\CryptoWallet::assets());
            var cryptoType = document.getElementById('p');
            function updateAmount() {
                if (cryptoType.value === '5') {
                    var selected = form.querySelector('[name="crypto_asset"]:checked');
                    var asset = selected && cryptoAssets[selected.value];
                    document.getElementById('withdraw-currency').textContent = asset ? asset.coin : '';
                    document.getElementById('withdraw-rate').textContent = asset ? '1 ' + asset.coin + ' = ' + asset.usd_price + ' USD' : @json(__('crypto_wallet.unavailable'));
                    output.textContent = asset && Number(amount.value) > 0 ? (Math.floor(Number(amount.value) / Number(asset.usd_price) * 1e8) / 1e8).toFixed(8) : '0.00000000';
                    amount.setCustomValidity(asset ? '' : @json(__('crypto_wallet.unavailable')));
                    form.querySelector('button[type="submit"]').disabled = !asset;
                    document.getElementById('withdraw-crypto-unavailable').hidden = !!asset;
                    return;
                }
                form.querySelector('button[type="submit"]').disabled = false;
                var currency = 'TRY';
                var rate = Number(rates[currency]);
                document.getElementById('withdraw-currency').textContent = currency;
                document.getElementById('withdraw-rate').textContent = rate > 0 ? '1 USD = ' + numberFormat.format(rate) + ' ' + currency : @json(translate('Exchange rate is not configured. Please contact support.'));
                amount.setCustomValidity(rate > 0 ? '' : @json(translate('Exchange rate is not configured. Please contact support.')));
                var usd = Number(amount.value);
                output.textContent = numberFormat.format(rate > 0 && Number.isFinite(usd) && usd > 0 ? Math.round(Math.round(usd * 100) * rate) / 100 : 0);
            }
            function configureBankFields(crypto) {
                ['iban_holder', 'iban_bank'].forEach(function (name) {
                    form.elements[name].disabled = crypto;
                    form.elements[name].required = !crypto;
                    form.elements[name].maxLength = 200;
                });
                form.querySelector('label[for="withdraw-holder"]').textContent = @json(translate('Full name')) + ' *';
                form.querySelector('label[for="withdraw-bank"]').textContent = @json(translate('Bank Name')) + ' *';
                form.elements.bank_account.closest('.form-group').hidden = true;
                form.elements.bank_account.disabled = true;
                document.getElementById('turkey-iban-field').hidden = false;
                form.elements.iban_number.disabled = crypto;
                form.elements.iban_number.required = false;
            }
            amount.addEventListener('input', updateAmount);
            var firstCrypto = form.querySelector('[name="crypto_asset"]:not(:disabled)');
            var oldAsset = @json(old('wallet_form') === 'withdraw' ? old('crypto_asset') : null);
            if (oldAsset) firstCrypto = form.querySelector('[name="crypto_asset"][value="' + oldAsset + '"]:not(:disabled)') || firstCrypto;
            if (firstCrypto) firstCrypto.checked = true;
            function toggleCrypto() {
                var crypto = cryptoType.value === '5';
                form.elements.w_type.value = cryptoType.value;
                document.getElementById('withdraw-crypto-fields').hidden = !crypto;
                document.getElementById('withdraw-country-panel').hidden = crypto;
                document.getElementById('withdraw-bank-market').hidden = crypto;
                document.getElementById('withdraw-bank-market').classList.toggle('d-flex', !crypto);
                form.elements.withdrawal_market.disabled = crypto;
                form.elements.iban_country.disabled = crypto;
                configureBankFields(crypto);
                form.elements.crypto_address.disabled = !crypto;
                form.elements.crypto_address.required = crypto;
                form.querySelectorAll('[name="crypto_asset"]').forEach(function (el) { el.disabled = !crypto || !cryptoAssets[el.value].enabled; el.required = crypto; });
                updateAmount();
            }
            cryptoType.addEventListener('change', toggleCrypto);
            form.querySelectorAll('[name="crypto_asset"]').forEach(function (el) { el.addEventListener('change', updateAmount); });
            cryptoType.value = @json(old('wallet_form') === 'withdraw' && (string) old('w_type') === '5' ? '5' : '4');
            toggleCrypto();
        })();
    </script>
@endsection
