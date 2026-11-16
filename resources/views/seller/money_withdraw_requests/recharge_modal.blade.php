@php
    $rechargeLocales = ['cn' => 'zh-CN', 'hk' => 'zh-HK', 'sa' => 'ar-SA', 'bd' => 'bn-BD', 'jp' => 'ja-JP', 'kr' => 'ko-KR', 'in' => 'hi-IN', 'be' => 'be-BY'];
    $rechargeLocale = $rechargeLocales[app()->getLocale()] ?? str_replace('_', '-', app()->getLocale());
    $bankMethods = \App\Services\WalletService::methods();
    $rechargeRates = app(\App\Services\ExchangeRate::class)->availableRates();
@endphp
<form id="seller-recharge-form" lang="{{ $rechargeLocale }}" action="{{ route('wallet_recharge.make_payment', ['portal' => 'seller']) }}" method="post" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="wallet_form" value="recharge">
    <input type="hidden" name="request_token" value="{{ old('request_token', (string) \Illuminate\Support\Str::uuid()) }}">
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="modal-body p-4">
        <div class="form-group">
            <label for="recharge-method">{{ translate('Payment method') }} <span class="text-danger">*</span></label>
            <input type="hidden" name="recharge_method" value="IBAN">
            <input id="recharge-method" class="form-control" value="BANK" readonly>
        </div>
        <div class="form-group">
            <label for="recharge-currency">{{ translate('Currency') }} <span class="text-danger">*</span></label>
            <select id="recharge-currency" name="currency" class="form-control" required>
                <option value="SGD" @if(!$rechargeRates['SGD']) disabled @endif>SGD — {{ translate('Singapore Dollar') }}</option>
                <option value="TRY" @if(!$rechargeRates['TRY']) disabled @endif>TRY — {{ translate('Turkish Lira') }}</option>
            </select>
        </div>
        <div class="form-group">
            <label for="recharge-amount">{{ translate('Amount') }} (<span id="recharge-currency-label">SGD</span>) <span class="text-danger">*</span></label>
            <input id="recharge-amount" class="form-control" dir="ltr" type="number" name="amount_local" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" placeholder="{{ translate('Please enter the amount') }}" required>
            <input type="hidden" name="amount" value="0.00">
            <div class="mt-2 text-muted">{{ translate('Exchange rate') }}: <bdi dir="ltr"><span id="recharge-rate"></span></bdi></div>
            <div class="mt-1 text-primary" aria-live="polite">{{ translate('Estimated amount') }}: <bdi dir="ltr">≈ <span id="recharge-usd">0.00</span> USD</bdi></div>
        </div>
        <div class="form-group">
            <label for="recharge-transaction">{{ translate('Transaction ID') }}</label>
            <input id="recharge-transaction" name="trx_id" class="form-control" maxlength="200">
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
        <div style="text-align: end"><button type="submit" class="btn btn-primary" @if($bankMethods->isEmpty()) disabled @endif>{{ translate('Confirm') }}</button></div>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('seller-recharge-form');
    var rates = @json($rechargeRates);
    var numberFormat = new Intl.NumberFormat(form.lang, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    function updateAmount() {
        var currency = form.elements.currency.value, rate = Number(rates[currency]);
        form.querySelector('#recharge-currency-label').textContent = currency;
        form.querySelector('#recharge-rate').textContent = rate > 0 ? '1 USD = ' + numberFormat.format(rate) + ' ' + currency : @json(translate('Exchange rate is not configured. Please contact support.'));
        form.elements.amount_local.setCustomValidity(rate > 0 ? '' : @json(translate('Exchange rate is not configured. Please contact support.')));
        var local = Number(form.elements.amount_local.value);
        var usd = rate > 0 && Number.isFinite(local) && local > 0 ? Math.round(Math.round(local * 100) / rate) / 100 : 0;
        form.elements.amount.value = usd.toFixed(2);
        form.querySelector('#recharge-usd').textContent = numberFormat.format(usd);
    }
    form.elements.amount_local.addEventListener('input', updateAmount);
    form.elements.currency.addEventListener('change', updateAmount);
    form.addEventListener('submit', updateAmount);
    var previous = @json(old('wallet_form') === 'recharge' ? old() : []);
    Object.keys(previous).forEach(function (name) { if (form.elements[name] && name !== '_token') form.elements[name].value = previous[name]; });
    if (!previous.currency && previous.amount_sgd) form.elements.amount_local.value = previous.amount_sgd;
    form.elements.recharge_method.value = 'IBAN';
    updateAmount();
})();
</script>
