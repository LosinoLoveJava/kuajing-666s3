@php($bankMethods = $bankMethods ?? \App\Services\WalletService::methods())
@forelse($bankMethods as $bankMethod)
    <div class="border rounded p-3 mb-3">
        <strong>{{ $bankMethod->heading }}</strong>
        <div>{!! $bankMethod->description !!}</div>
        @foreach((json_decode($bankMethod->bank_info, true) ?: []) as $bank)
            <dl class="mb-0">
                @foreach(['bank_name' => 'Bank Name', 'account_name' => 'Account holder', 'account_number' => 'Bank Account Number', 'routing_number' => 'Routing Number'] as $field => $label)
                    @if(!empty($bank[$field]))<dt>{{ translate($label) }}</dt><dd class="text-break">{{ $bank[$field] }}</dd>@endif
                @endforeach
            </dl>
        @endforeach
    </div>
@empty
    <div class="alert alert-warning">{{ translate('No bank transfer instructions are configured. Please contact support.') }}</div>
@endforelse
