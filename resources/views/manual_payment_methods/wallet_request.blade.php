@extends('backend.layouts.app')

@section('content')

<div class="card">
{{--    <div class="card-header">--}}
        <form action="" class="card-header">
            <div class="col">
                <h5 class="mb-0 h6">{{translate('Offline Wallet Recharge Requests')}}</h5>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="name" name="name" @isset($name) value="{{ $name }}" @endisset placeholder="{{ translate('name') }}" onkeyup="filterProducts()">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="operator" name="operator" @isset($operator) value="{{ $operator}}" @endisset placeholder="{{ translate('operator') }}" onkeyup="filterProducts()">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <input type="text" class="aiz-date-range form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" data-format="Y-MM-DD" data-separator=" to " data-advanced-range="true" autocomplete="off">
                </div>
            </div>
            <button type="submit" class="btn btn-success btn-styled">{{ translate('Search') }}</button>
        </form>
{{--    </div>--}}

    <div class="card-body">
        <table class="table aiz-table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{translate('Name')}}</th>
                    <th>{{translate('Operator')}}</th>
                    <th>{{translate('Amount')}}</th>
                    <th>{{translate('Method')}}</th>
                    <th>{{translate('TXN ID')}}</th>
                    <th>{{translate('Photo')}}</th>
                    <th>{{translate('Approval')}}</th>
                    <th>{{translate('Type')}}</th>
                    <th>{{translate('Date')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($wallets as $key => $wallet)
                    @if ($wallet->user != null)
                        <tr>
                            <td>{{ ($key+1) }}</td>
                            <td>{{ $wallet->user->name }}</td>
                            <td>{{ optional($wallet->operator)->name ?? '—' }}</td>
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
                                @if((int) $wallet->approval === 0 && in_array(Auth::user()->user_type, ['admin', 'staff'], true))
                                    <div class="mt-2 d-flex">
                                        <button type="button" class="btn btn-sm btn-success wallet-review-button mr-1" data-id="{{ $wallet->id }}" data-status="1" data-account="{{ $wallet->user->name }} · {{ single_price($wallet->amount) }}">{{ translate('Approve') }}</button>
                                        <button type="button" class="btn btn-sm btn-danger wallet-review-button" data-id="{{ $wallet->id }}" data-status="2" data-account="{{ $wallet->user->name }} · {{ single_price($wallet->amount) }}">{{ translate('Reject') }}</button>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if( $wallet->type == 1 )
                                {{ translate('Balance Recharge')}}
                                @else
                                {{ translate('Guarantee Recharge')}}
                            @endif
                            </td>
                            <td>{{ $wallet->created_at }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        <div class="aiz-pagination">
            {{ $wallets->appends(request()->input())->links() }}
        </div>
    </div>
</div>

@endsection
@section('script')
@include('partials.wallet_review_modal')
@endsection
