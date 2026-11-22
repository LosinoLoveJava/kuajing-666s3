@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar mt-2 mb-3 d-flex flex-wrap align-items-center justify-content-between">
    <h1 class="h3 mb-2">{{ __('seller_balance.title') }}</h1>
    <a href="{{ route('sellers.index') }}" class="btn btn-light mb-2">{{ translate('All Sellers') }}</a>
</div>
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif
<div class="card">
    <div class="card-header"><h2 class="h6 mb-0 text-break">{{ $seller->name }} · #{{ $seller->id }}</h2></div>
    <div class="card-body">
        <p class="text-break">{{ $seller->phone ?: $seller->email }}</p>
        <p>{{ __('seller_balance.current_balance') }}: <strong dir="ltr">{{ number_format((float) $seller->balance, 2, '.', ',') }} USD</strong></p>
        <p class="text-muted">{{ __('seller_balance.hint') }}</p>
        <form id="seller-balance-deduction-form" action="{{ route('sellers.balance_deduction.store', $seller->id) }}" method="post">
            @csrf
            <input type="hidden" name="request_token" value="{{ old('request_token', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="form-group" style="max-width:360px">
                <label for="deduction-amount">{{ __('seller_balance.amount') }} (USD) <span class="text-danger">*</span></label>
                <input id="deduction-amount" name="amount" type="number" class="form-control" dir="ltr" inputmode="decimal" min="0.01" step="0.01" max="9999999999.99" value="{{ old('amount') }}" required>
                @error('amount')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label for="deduction-reason">{{ __('seller_balance.reason') }} <span class="text-danger">*</span></label>
                <textarea id="deduction-reason" name="remarks" class="form-control" maxlength="2000" rows="3" required>{{ old('remarks') }}</textarea>
                @error('remarks')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-danger">{{ __('seller_balance.submit') }}</button>
        </form>
    </div>
</div>
<div class="card" id="seller-deduction-history">
    <div class="card-header"><h2 class="h6 mb-0">{{ __('seller_balance.history') }}</h2></div>
    <div class="card-body">
        <div class="aiz-table-scroll" tabindex="0">
            <table class="table mb-0" style="min-width:760px">
                <thead><tr>
                    <th>#</th><th>{{ translate('Date') }}</th><th>{{ __('seller_balance.amount') }} (USD)</th>
                    <th>{{ __('seller_balance.before') }} (USD)</th><th>{{ __('seller_balance.after') }} (USD)</th>
                    <th>{{ __('seller_balance.operator') }}</th><th>{{ __('seller_balance.reason') }}</th>
                </tr></thead>
                <tbody>
                @forelse($deductions as $deduction)
                    @php($snapshot = json_decode($deduction->payment_details, true) ?: [])
                    <tr>
                        <td>{{ $deduction->id }}</td><td class="text-nowrap">{{ $deduction->created_at }}</td>
                        <td class="text-danger text-nowrap">{{ bcsub('0', (string) $deduction->amount, 2) }}</td>
                        <td>{{ $snapshot['balance_before'] ?? '—' }}</td><td>{{ $snapshot['balance_after'] ?? '—' }}</td>
                        <td class="text-break">{{ optional($deduction->operator)->name ?? '—' }} (#{{ $deduction->operator_id }})</td>
                        <td class="text-break" style="min-width:180px;white-space:pre-wrap">{{ $deduction->remarks }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">{{ __('seller_balance.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="aiz-pagination">{{ $deductions->links() }}</div>
    </div>
</div>
@endsection

@section('script')
<script>
document.getElementById('seller-balance-deduction-form').addEventListener('submit', function () {
    this.querySelector('button[type="submit"]').disabled = true;
});
</script>
@endsection
