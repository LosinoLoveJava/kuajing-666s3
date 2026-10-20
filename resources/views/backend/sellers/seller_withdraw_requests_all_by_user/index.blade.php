@extends('backend.layouts.app')
@section('content')
    <div class="card">
        <div class="card-header">
            <button type="button" onclick="history.go(-1);" class="btn btn-styled btn-base-3" style="color: #0e76e6">返回</button>
            <h5 class="mb-0 h6">{{translate('Seller Withdraw Request')}}</h5>
        </div>
        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <th data-breakpoints="lg">#</th>
                        <th data-breakpoints="lg">{{translate('Date')}}</th>
                        <th>{{translate('Seller')}}</th>
                        <th data-breakpoints="lg">{{translate('Total Amount to Pay')}}</th>
                        <th>{{translate('Requested Amount')}}</th>
                 
                        <th data-breakpoints="lg" width="40%">{{ translate('Message') }}</th>
                        <th data-breakpoints="lg">{{ translate('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($seller_withdraw_requests as $key => $seller_withdraw_request)
                        @php $user = \App\Models\User::find($seller_withdraw_request->user_id); @endphp
                        @if ($user )
                            <tr>
                                <td>{{ ($key+1) }}</td>
                                <td>{{ $seller_withdraw_request->created_at }}</td>
                                <td>{{ $user->name }} ({{ $user->shop->name }})</td>
                                <td>{{ single_price($user->shop->admin_to_pay) }}</td>
                                <td>{{ single_price($seller_withdraw_request->amount) }}</td>
                      
                                <td>
                                    {{ $seller_withdraw_request->message }}
                                </td>
                                <td>
                                    @if ($seller_withdraw_request->status == 1)
                                    <span class="badge badge-inline badge-success">{{translate('Paid')}}</span>
                                    @elseif ($seller_withdraw_request->status == 2)
                                    <span class="badge badge-inline badge-error">{{translate('Refuse')}}</span>
                                    @else
                                    <span class="badge badge-inline badge-info">{{translate('Pending')}}</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
