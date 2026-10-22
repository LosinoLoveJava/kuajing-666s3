<div class="iban-fields mb-3" hidden>
    <div class="form-group"><label for="withdraw-iban-country">{{ translate('Account country') }} <span class="text-danger">*</span></label>
        <select id="withdraw-iban-country" name="iban_country" class="form-control" data-iban-required>
            @foreach(config('iban.lengths') as $code => $length)<option value="{{ $code }}">{{ $code }}</option>@endforeach
        </select>
    </div>
    <div class="form-group"><label for="withdraw-iban-number">IBAN <span class="text-danger">*</span></label><input id="withdraw-iban-number" class="form-control" name="iban_number" maxlength="64" autocomplete="off" data-iban-required></div>
    <div class="form-group"><label for="withdraw-iban-bic">SWIFT / BIC ({{ translate('Optional') }})</label><input id="withdraw-iban-bic" class="form-control" name="iban_bic" maxlength="11"></div>
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
