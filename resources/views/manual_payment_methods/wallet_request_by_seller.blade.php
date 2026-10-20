@extends('backend.layouts.app')

@section('content')

<div class="card">
    <div class="card-header">
        <button type="button" onclick="history.go(-1);" class="btn btn-styled btn-base-3" style="color: #0e76e6">返回</button>
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

                            </td>
                            <td>
                                <label class="aiz-switch aiz-switch-success mb-0">
                                    <input disabled readonly type="checkbox" @if($wallet->approval == 1) checked @endif >
                                    <span class="slider round"></span>
                                </label>
                            </td>
                            <td>{{ $wallet->created_at }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
@section('script')
    <script type="text/javascript">

    </script>
@endsection
