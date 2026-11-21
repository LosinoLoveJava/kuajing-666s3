@if($seller_withdraw_request->iban_details)
@php($iban = $seller_withdraw_request->iban_details)
<dl class="border rounded p-3 my-2 iban-account-details">
    <dt>{{ translate('Withdrawal method') }}</dt><dd>{{ $iban['method'] ?? 'IBAN' }}</dd>
    @if(!empty($iban['asset']))
        <dt>{{ __('crypto_wallet.network') }}</dt><dd>{{ $iban['network'] }}</dd>
        <dt>{{ __('crypto_wallet.withdraw_address') }}</dt><dd class="text-break"><bdi dir="ltr">{{ $iban['address'] }}</bdi></dd>
        <dt>{{ __('crypto_wallet.price') }}</dt><dd>1 {{ $iban['currency'] }} = {{ $iban['usd_price'] }} USD</dd>
        <dt>{{ translate('Estimated amount') }}</dt><dd>{{ $iban['estimated_amount'] }} {{ $iban['currency'] }}</dd>
    @else
    <dt>{{ translate('Account country') }}</dt><dd>{{ $iban['country'] ?? '' }}</dd>
    <dt>{{ translate('Account holder') }}</dt><dd>{{ $iban['holder'] ?? '' }}</dd>
    <dt>{{ translate('Bank name') }}</dt><dd>{{ $iban['bank'] ?? '' }}</dd>
    @if(!empty($iban['market']))
        <dt>{{ translate('Withdrawal country') }}</dt><dd>{{ translate($iban['market'] === 'TR' ? 'Turkey' : 'Singapore') }}</dd>
        <dt>{{ translate('Exchange rate') }}</dt><dd><bdi dir="ltr">1 USD = {{ $iban['exchange_rate'] }} {{ $iban['currency'] }}</bdi></dd>
        <dt>{{ translate('Estimated amount') }}</dt><dd><bdi dir="ltr">{{ $iban['estimated_amount'] }} {{ $iban['currency'] }}</bdi></dd>
    @endif
    <dt>IBAN</dt><dd class="text-break">{{ $iban['iban'] ?? '' }}</dd>
    <dt>SWIFT / BIC</dt><dd>{{ $iban['bic'] ?? '' }}</dd>
    @foreach(['account_number' => 'Bank Account Number', 'routing_number' => 'Routing Number', 'network' => 'Network', 'address' => 'Address'] as $field => $label)
        @if(!empty($iban[$field]))<dt>{{ translate($label) }}</dt><dd class="text-break">{{ $iban[$field] }}</dd>@endif
    @endforeach
    @endif
</dl>
@else
    <div class="alert alert-warning">{{ translate('This historical request has no saved account details. Confirm the recipient before recording payment.') }}</div>
@endif
