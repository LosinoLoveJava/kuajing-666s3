@extends('seller.layouts.app')

@section('panel_content')

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

    <div class="card wallet-history-card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Withdraw Request history')}}</h5>
        </div>
        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ translate('Date') }}</th>
                    <th>{{ translate('Amount')}}</th>
                    <th>{{ translate('Type')}}</th>
                    
                    <th data-breakpoints="lg">{{ translate('Status')}}</th>
                    <th>{{ translate('Withdraw Type')}}</th>
                    <th>{{ translate('Remarks')}}</th>
                    <th data-breakpoints="lg" width="40%">{{ translate('Message')}}</th>
                    
                    
                </tr>
                </thead>
                <tbody>
                @foreach ($seller_withdraw_requests as $key => $seller_withdraw_request)
                    <tr>
                        <td>{{ $key+1 }}</td>
                        <td>{{ date('d-m-Y', strtotime($seller_withdraw_request->created_at)) }}</td>
                        <td>{{ single_price($seller_withdraw_request->amount) }}</td>
                        <td>
                            @if( $seller_withdraw_request->type == 1)
                            
                            {{translate('User Balance')}}
                            @else
                            
                              {{translate('Guarantee')}}
                            @endif
                        </td>
                        <td> 
                            @if ($seller_withdraw_request->status == 1)
                                <span class=" badge badge-inline badge-success">{{ translate('Paid')}}</span>
                             @elseif ($seller_withdraw_request->status == 2)
                                <span class=" badge badge-inline badge-danger">{{ translate('Refuse')}} </span>
                            @else
                                <span class=" badge badge-inline badge-info">{{ translate('Pending')}}</span>
                            @endif
                        </td>
                        <td>
                            
                            @if( $seller_withdraw_request->w_type == 1)
                            
                            {{translate('Cash')}}
                            @elseif( $seller_withdraw_request->w_type == 2)
                            
                              {{translate('Bank')}}
                            @elseif( $seller_withdraw_request->w_type == 3)
                            {{translate('USDT')}}
                            @elseif($seller_withdraw_request->w_type == 4)
IBAN
@include('partials.iban_details')
@endif
                        </td>
                             <td>
                            {{ $seller_withdraw_request->remarks }}
                        </td>
                        <td>
                            {{ $seller_withdraw_request->message }}
                        </td>
                   
                    </tr>
                @endforeach
                </tbody>
            </table>
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
                @foreach ($freezeOrders as $key => $order)
                    <tr>
                        <td>{{ $key+1 }}</td>
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
                @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $freezeOrders->links() }}
            </div>
        </div>
    </div>

    <!-- 充值记录 -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Wallet Recharge History')}}</h5>
        </div>
        <div class="card-body">
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
                @foreach ($rechargeList as $key => $list)
                    <tr>
                        <td>{{ $key+1 }}</td>
                        <td>{{ single_price($list->amount) }}</td>
                        <td>{{ $list->payment_method }}</td>
                        <td>{{ $list->payment_details }}</td>
                        <td>
                            @if ($list->approval == 1)
                                <span class="badge badge-inline badge-success">{{translate('yes')}}</span>
                            @else
                                <span class="badge badge-inline badge-danger">{{translate('No')}}</span>
                            @endif
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
                        
                        
                        <td>{{ $list->reciept }}</td>
                        <td>{{ date('d-m-Y', strtotime($list->created_at)) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
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
                @foreach ($paymentList as $key => $list)
                    <tr>
                        <td>{{ $key+1 }}</td>
                        <td>{{ single_price($list->amount) }}</td>
                    
                        <td>{{ $list->payment_details }}</td>
                        
                        <td>
                            {{translate($list->payment_method)}}
                        </td>
                        
                      
                        
                        
                      
                        <td>{{ date('d-m-Y', strtotime($list->created_at)) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $paymentList->links() }}
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
    </div>


    <div class="modal fade" id="request_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ translate('Send A Withdraw Request') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
                </div>
                @if ($balance > 5)
                    <form class="" action="{{ route('seller.money_withdraw_request.store') }}" method="post">
                        @csrf
                        <div class="modal-body p-4">
                            <div class="row">
                                <div class="col">
                                    <div class="alert alert-success" role="alert">
                                        <h6>{{ translate('Your wallet balance :') }} ${{ $balance }}</h6>
                                    </div>
                                    
                                    
                                </div>
                            </div>
                            <input type="hidden" name="type" value="1">
                            <div class="form-group">
                                <label for="p">{{ translate('Withdraw Type')}} <span class="text-danger">*</span></label>
                                <select name="w_type" class="form-control" id="p" required>
                                        <option value="2">{{translate('Bank')}}</option>
                                        <option value="4">IBAN</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="withdraw-bank">{{ translate('Bank Name') }} <span class="text-danger">*</span></label>
                                <input id="withdraw-bank" name="bank_name" class="form-control" maxlength="200" placeholder="{{ translate('Please enter the bank name') }}">
                            </div>
                            <div class="form-group">
                                <label for="withdraw-holder">{{ translate('Account holder') }} <span class="text-danger">*</span></label>
                                <input id="withdraw-holder" name="account_holder" class="form-control" maxlength="200" placeholder="{{ translate('Please enter the name') }}">
                            </div>
                            <div id="withdraw-bank-fields">
                                <div class="form-group">
                                    <label for="withdraw-account">{{ translate('Bank Account Number') }} <span class="text-danger">*</span></label>
                                    <input id="withdraw-account" name="bank_account" class="form-control" dir="ltr" maxlength="100" placeholder="{{ translate('Please enter the bank account') }}">
                                </div>
                            </div>
@include('partials.iban_form')

                            <div class="form-group">
                                <label for="withdraw-amount">{{ translate('Amount')}} (USD) <span class="text-danger">*</span></label>
                                <input id="withdraw-amount" type="number" lang="en" dir="ltr" class="form-control" name="amount" min="0.01" step="0.01" placeholder="{{ translate('Amount') }}" required>
                                <div class="mt-2 text-muted">{{ translate('Exchange rate') }}: <bdi dir="ltr">1 USD = 3.67 AED</bdi></div>
                                <div class="mt-1 text-primary" aria-live="polite">{{ translate('Estimated amount') }}: <bdi dir="ltr">≈ <span id="withdraw-aed">0.00</span> AED</bdi></div>
                            </div>

                            <div class="form-group">
                                <label for="withdraw-message">{{ translate('Message')}}</label>
                                <textarea id="withdraw-message" name="message" rows="5" class="form-control"></textarea>
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
            $.post('{{ route('offline_wallet_recharge_modal') }}', {type:type,_token:'{{ csrf_token() }}'}, function(data){
                $('#offline_wallet_recharge_modal_body').html(data);
                $('#offline_wallet_recharge_modal').modal('show');
            });
        }

        (function () {
            var form = document.querySelector('#request_modal form');
            if (!form) return;
            var amount = form.elements.amount;
            var output = document.getElementById('withdraw-aed');
            var bankFields = document.getElementById('withdraw-bank-fields');
            var bankName = document.getElementById('withdraw-bank');
            var holder = document.getElementById('withdraw-holder');
            var bankAccount = document.getElementById('withdraw-account');
            var countrySelect = form.elements.iban_country;
            if (window.Intl && Intl.DisplayNames && countrySelect) {
                var countryNames = new Intl.DisplayNames([document.documentElement.lang || 'en'], {type: 'region'});
                Array.from(countrySelect.options).forEach(function (option) {
                    option.textContent = countryNames.of(option.value) + ' (' + option.value + ')';
                });
            }
            function updateAmount() {
                var usd = Number(amount.value);
                output.textContent = new Intl.NumberFormat(document.documentElement.lang || 'en', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                }).format(Number.isFinite(usd) && usd > 0 ? Math.round(usd * 3.67 * 100) / 100 : 0);
            }
            function toggleBankFields() {
                var bank = form.elements.w_type.value === '2';
                bankFields.hidden = !bank;
                bankName.name = bank ? 'bank_name' : 'iban_bank';
                holder.name = bank ? 'account_holder' : 'iban_holder';
                bankName.disabled = false;
                holder.disabled = false;
                bankName.required = true;
                holder.required = true;
                bankAccount.disabled = !bank;
                bankAccount.required = bank;
            }
            amount.addEventListener('input', updateAmount);
            form.elements.w_type.addEventListener('change', toggleBankFields);
            updateAmount();
            toggleBankFields();
        })();
    </script>
@endsection
