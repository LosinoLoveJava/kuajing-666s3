@if($seller_withdraw_request->w_type == 4 && $seller_withdraw_request->iban_details)
@php($iban = $seller_withdraw_request->iban_details)
<dl class="border rounded p-3 my-2">
    <dt>{{ translate('Withdrawal method') }}</dt><dd>IBAN</dd>
    <dt>{{ translate('Account country') }}</dt><dd>{{ $iban['country'] ?? '' }}</dd>
    <dt>{{ translate('Account holder') }}</dt><dd>{{ $iban['holder'] ?? '' }}</dd>
    <dt>{{ translate('Bank name') }}</dt><dd>{{ $iban['bank'] ?? '' }}</dd>
    <dt>IBAN</dt><dd class="text-break">{{ $iban['iban'] ?? '' }}</dd>
    <dt>SWIFT / BIC</dt><dd>{{ $iban['bic'] ?? '' }}</dd>
</dl>
@endif
