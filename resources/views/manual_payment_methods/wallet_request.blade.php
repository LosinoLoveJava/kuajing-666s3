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
                            <td>{{ $wallet->operator->user_type=='admin'?'admin':$wallet->operator->name }}</td>
                            <td>{{ $wallet->amount }}</td>
                            <td>{{ $wallet->payment_method }}</td>
                            <td>{{ $wallet->payment_details }}</td>
                            <td>
                                @if ($wallet->reciept != null)
                                    <a href="{{ uploaded_asset($wallet->reciept) }}" target="_blank">{{translate('Open Reciept')}}</a>
                                @endif
                            </td>
                            <td>
                                <label class="aiz-switch aiz-switch-success mb-0">
                                    <input onchange="update_approved(this)" value="{{ $wallet->id }}" type="checkbox" @if($wallet->approval == 1) checked @endif >
                                    <span class="slider round"></span>
                                </label>
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
    <script type="text/javascript">
        function update_approved(el){
            if(el.checked){
                var status = 1;
            }
            else{
                var status = 0;
            }
            $.post('{{ route('offline_recharge_request.approved') }}', {_token:'{{ csrf_token() }}', id:el.value, status:status}, function(data){
                if(data == 1){
                    AIZ.plugins.notify('success', '{{ translate('successfully') }}');
                }
                else{
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }
    </script>
@endsection
