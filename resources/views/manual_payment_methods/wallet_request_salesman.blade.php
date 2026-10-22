@extends('salesman.layouts.app')

@section('panel_content')

<div class="card">
    <div class="card-header">
        <h5 class="mb-0 h6">{{translate('Offline Wallet Recharge Requests')}}</h5>
    </div>
    <div class="card-body">
        <table class="table aiz-table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{translate('Name')}}</th>
                    <th>{{translate('Amount')}}</th>
                    <th>{{translate('Method')}}</th>
                    <th>{{translate('TXN ID')}}</th>
                    <th>{{translate('Photo')}}</th>
                    <th>{{translate('Approval')}}</th>
                    <th>{{translate('Date')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($wallets as $key => $wallet)
                    @if ($wallet->user != null)
                        <tr>
                            <td>{{ ($key+1) }}</td>
                            <td>{{ $wallet->user->name }}</td>
                            <td>{{ $wallet->amount }}</td>
                            <td>{{ $wallet->payment_method }}</td>
                            <td>{{ $wallet->payment_details }}</td>
                            <td>
                                @if ($wallet->reciept != null)
                                    <a href="{{ uploaded_asset($wallet->reciept) }}" target="_blank">{{translate('Open Reciept')}}</a>
                                @endif
                            </td>
                            <td>
                                @include('partials.wallet_status', ['approval' => $wallet->approval])
                                <div class="small text-muted">{{ $wallet->remarks }}</div>
                            </td>
                            <td>{{ $wallet->created_at }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        <div class="aiz-pagination">
            {{ $wallets->links() }}
        </div>
    </div>
</div>

@endsection
@section('script')
@endsection
