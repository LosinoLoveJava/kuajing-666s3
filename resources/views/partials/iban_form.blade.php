<div class="iban-fields border rounded p-3 mb-3" hidden>
    <p class="font-weight-bold">{{ translate('IBAN account details') }}</p>
    <label class="d-block">{{ translate('Account country') }}
        <select name="iban_country" class="form-control" data-iban-required>
            @foreach(config('iban.lengths') as $code => $length)<option value="{{ $code }}">{{ $code }}</option>@endforeach
        </select>
    </label>
    <label class="d-block">{{ translate('Account holder') }}<input class="form-control" name="iban_holder" maxlength="200" data-iban-required></label>
    <label class="d-block">{{ translate('Bank name') }}<input class="form-control" name="iban_bank" maxlength="200" data-iban-required></label>
    <label class="d-block">IBAN<input class="form-control" name="iban_number" maxlength="64" autocomplete="off" data-iban-required></label>
    <label class="d-block">SWIFT / BIC ({{ translate('Optional') }})<input class="form-control" name="iban_bic" maxlength="11"></label>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[name="w_type"]').forEach(function (select) {
        var form = select.closest('form'), fields = form.querySelector('.iban-fields');
        if (!fields) return;
        function toggle() {
            var shown = select.value === '4';
            fields.hidden = !shown;
            fields.querySelectorAll('input, select').forEach(function (el) {
                el.disabled = !shown;
                el.required = shown && el.hasAttribute('data-iban-required');
            });
        }
        select.addEventListener('change', toggle); toggle();
    });
});
</script>
