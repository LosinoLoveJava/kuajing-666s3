@php
    $rechargeLocales = ['cn' => 'zh-CN', 'hk' => 'zh-HK', 'sa' => 'ar-SA', 'bd' => 'bn-BD', 'jp' => 'ja-JP', 'kr' => 'ko-KR', 'in' => 'hi-IN', 'be' => 'be-BY'];
    $rechargeLocale = $rechargeLocales[app()->getLocale()] ?? str_replace('_', '-', app()->getLocale());
@endphp
<form id="seller-recharge-form" lang="{{ $rechargeLocale }}" action="{{ route('wallet_recharge.make_payment') }}" method="post" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="modal-body p-4">
        <div class="row align-items-center mb-3">
            <div class="col-md-3"><label class="mb-0" for="recharge-method">{{ translate('Payment method') }} <span class="text-danger">*</span></label></div>
            <div class="col-md-9">
                <select id="recharge-method" name="recharge_method" class="form-control" required>
                    <option value="Bank">{{ translate('Bank') }}</option>
                    <option value="IBAN">IBAN</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="recharge-bank">{{ translate('Bank Name') }} <span class="text-danger">*</span></label>
            <input id="recharge-bank" class="form-control" name="bank_name" maxlength="200" placeholder="{{ translate('Please enter the bank name') }}" required>
            <label for="recharge-holder" class="mt-3">{{ translate('Account holder') }} <span class="text-danger">*</span></label>
            <input id="recharge-holder" class="form-control" name="account_holder" maxlength="200" placeholder="{{ translate('Please enter the name') }}" required>
        </div>
        <div class="form-group" id="recharge-bank-fields">
            <label for="recharge-account">{{ translate('Bank Account Number') }} <span class="text-danger">*</span></label>
            <input id="recharge-account" class="form-control" name="bank_account" dir="ltr" maxlength="100" autocomplete="off" placeholder="{{ translate('Please enter the bank account') }}" required>
        </div>
        <div id="recharge-iban-fields" hidden>
            <div class="form-group">
                <label for="recharge-country">{{ translate('Account country') }} <span class="text-danger">*</span></label>
                <select id="recharge-country" name="iban_country" class="form-control" disabled>
                    @foreach(config('iban.lengths') as $code => $length)
                        <option value="{{ $code }}" @if($code === 'AE') selected @endif>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="recharge-iban">IBAN <span class="text-danger">*</span></label>
                <input id="recharge-iban" name="iban_number" class="form-control" dir="ltr" maxlength="64" autocomplete="off" disabled>
            </div>
            <div class="form-group">
                <label for="recharge-bic">SWIFT / BIC ({{ translate('Optional') }})</label>
                <input id="recharge-bic" name="iban_bic" class="form-control" dir="ltr" maxlength="11" disabled>
            </div>
        </div>
        <div class="form-group">
            <label for="recharge-amount">{{ translate('Amount') }} (AED) <span class="text-danger">*</span></label>
            <input id="recharge-amount" class="form-control" dir="ltr" type="number" name="amount" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" placeholder="{{ translate('Please enter the amount') }}" required>
            <div class="mt-2 text-muted">{{ translate('Exchange rate') }}: <bdi dir="ltr"><span id="recharge-rate">1 USD = 3.67 AED</span></bdi></div>
            <div class="mt-1 text-primary" aria-live="polite">{{ translate('Estimated amount') }}: <bdi dir="ltr">≈ <span id="recharge-usd">0.00</span> USD</bdi></div>
        </div>
        <div class="form-group">
            <label for="recharge-transaction">{{ translate('Transaction ID') }} <span class="text-danger">*</span></label>
            <input id="recharge-transaction" name="trx_id" class="form-control" maxlength="200" required>
        </div>
        <div class="form-group">
            <label>{{ translate('Photo') }}</label>
            <div class="input-group" data-toggle="aizuploader" data-type="image">
                <div class="input-group-prepend"><div class="input-group-text">{{ translate('Browse') }}</div></div>
                <div class="form-control file-amount">{{ translate('Choose image') }}</div>
                <input type="hidden" name="photo" class="selected-files">
            </div>
            <div class="file-preview box sm"></div>
        </div>
        <div style="text-align: end"><button type="submit" class="btn btn-primary">{{ translate('Confirm') }}</button></div>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('seller-recharge-form');
    var numberFormat = new Intl.NumberFormat(form.lang, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    form.querySelector('#recharge-rate').textContent = new Intl.NumberFormat(form.lang).format(1) + ' USD = ' + numberFormat.format(3.67) + ' AED';
    if (Intl.DisplayNames) {
        var countries = new Intl.DisplayNames([form.lang], {type: 'region'});
        Array.from(form.elements.iban_country.options).forEach(function (option) {
            option.textContent = countries.of(option.value) + ' (' + option.value + ')';
        });
    }
    function toggleMethod() {
        var iban = form.elements.recharge_method.value === 'IBAN';
        form.querySelector('#recharge-bank-fields').hidden = iban;
        form.elements.bank_account.disabled = iban;
        form.elements.bank_account.required = !iban;
        form.querySelector('#recharge-iban-fields').hidden = !iban;
        ['iban_country', 'iban_number', 'iban_bic'].forEach(function (name) {
            form.elements[name].disabled = !iban;
            form.elements[name].required = iban && name !== 'iban_bic';
        });
    }
    form.elements.recharge_method.addEventListener('change', toggleMethod);
    function updateAmount() {
        var aed = Number(form.elements.amount.value);
        form.querySelector('#recharge-usd').textContent = numberFormat.format(Number.isFinite(aed) && aed > 0
            ? Math.round((aed / 3.67) * 100) / 100 : 0);
    }
    form.elements.amount.addEventListener('input', updateAmount);
    updateAmount();
    toggleMethod();
})();
</script>
