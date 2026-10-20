@extends('backend.layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Refferal Users')}}</h5>
        </div>
        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ translate('Name')}}</th>
                    <th data-breakpoints="lg">{{ translate('Phone')}}</th>
                    <th data-breakpoints="lg">{{ translate('Email Address')}}</th>
                    <th data-breakpoints="lg">{{ translate('Reffered By')}}</th>
                    <th data-breakpoints="lg">{{ translate('Second level recommender')}}</th>
                    <th data-breakpoints="lg">{{ translate('Third level recommender')}}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($refferal_users as $key => $refferal_user)
                    @if ($refferal_user != null)
                        <tr>
                            <td>{{ ($key+1) + ($refferal_users->currentPage() - 1)*$refferal_users->perPage() }}</td>
                            <td>
                                {{$refferal_user->name}}
                                <div>
                                    <a href="{{ route('withdraw_requests_all_by_user') }}?user_id={{$refferal_user->id}}">
                                        {{ translate('Withdraw Requests')}}
                                    </a>
                                </div>
                                <a href="{{ route('offline_wallet_recharge_request_by_seller.index') }}?user_id={{$refferal_user->id}}">
                                    {{ translate('Offline Wallet Recharge Requests')}}
                                </a>
                            </td>
                            <td>{{$refferal_user->phone}}</td>
                            <td>{{$refferal_user->email}}</td>
                            <td>
                                <span>
                                {{ $refferal_user->referrer->name }} ({{ $refferal_user->referrer->email }})
                                </span>
                            </td>
                            <td>
                                @if ($refferal_user->referrer->referrer != null)
                                    <span>
                                    {{ $refferal_user->referrer->referrer->name }} ({{ $refferal_user->referrer->referrer->email }})
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($refferal_user->referrer->referrer->referrer != null)
                                    <span>
                                    {{ $refferal_user->referrer->referrer->referrer->name }} ({{ $refferal_user->referrer->referrer->referrer->email }})
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $refferal_users->appends(request()->input())->links() }}
            </div>
        </div>
    </div>

@endsection
