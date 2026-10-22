<div class="bank-recipient-fields mb-3" hidden>
    <div class="form-group"><label for="withdraw-holder">{{ translate('Account holder') }} <span class="text-danger">*</span></label><input id="withdraw-holder" class="form-control" name="iban_holder" value="{{ old('iban_holder') }}" maxlength="200" data-bank-required></div>
    <div class="form-group"><label for="withdraw-bank">{{ translate('Bank Name') }} <span class="text-danger">*</span></label><input id="withdraw-bank" class="form-control" name="iban_bank" value="{{ old('iban_bank') }}" maxlength="200" data-bank-required></div>
    <div class="form-group"><label for="withdraw-account">{{ translate('Bank Account Number') }}</label><input id="withdraw-account" class="form-control" name="bank_account" value="{{ old('bank_account') }}" maxlength="100" dir="ltr"></div>
</div>
<div class="iban-fields mb-3" hidden>
    <div class="form-group"><label for="withdraw-iban-country">{{ translate('Account country') }} <span class="text-danger">*</span></label>
        <select id="withdraw-iban-country" name="iban_country" class="form-control" data-iban-required>
            @foreach(config('iban.lengths') as $code => $length)<option value="{{ $code }}" @if($code === old('iban_country', 'AE')) selected @endif>{{ $code }}</option>@endforeach
        </select>
    </div>
    <div class="form-group"><label for="withdraw-iban-number">IBAN <span class="text-danger">*</span></label><input id="withdraw-iban-number" class="form-control" name="iban_number" value="{{ old('iban_number') }}" maxlength="64" autocomplete="off" data-iban-required></div>
    <div class="form-group"><label for="withdraw-iban-bic">SWIFT / BIC ({{ translate('Optional') }})</label><input id="withdraw-iban-bic" class="form-control" name="iban_bic" value="{{ old('iban_bic') }}" maxlength="11"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[name="w_type"]').forEach(function (select) {
        var form = select.closest('form'), fields = form.querySelector('.iban-fields'), bank = form.querySelector('.bank-recipient-fields');
        if (!fields) return;
        function toggle() {
            var shown = select.value === '4';
            var bankShown = shown || select.value === '2';
            bank.hidden = !bankShown;
            bank.querySelectorAll('input').forEach(function (el) {
                el.disabled = !bankShown;
                el.required = bankShown && (el.hasAttribute('data-bank-required') || (el.name === 'bank_account' && select.value === '2'));
            });
            fields.hidden = !shown;
            fields.querySelectorAll('input, select').forEach(function (el) {
                el.disabled = !shown;
                el.required = shown && el.hasAttribute('data-iban-required');
            });
        }
        select.value = @json((string) old('w_type', '4'));
        select.addEventListener('change', toggle); toggle();
    });
});
</script>
