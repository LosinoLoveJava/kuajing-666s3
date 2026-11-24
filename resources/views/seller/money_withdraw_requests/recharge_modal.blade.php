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
    <div class="modal-body p-4" style="max-height:calc(100vh - 180px);overflow-y:auto">
        <div class="form-group">
            <label for="recharge-method">{{ translate('Payment method') }} <span class="text-danger">*</span></label>
            <select id="recharge-method" name="recharge_method" class="form-control"><option value="Bank">{{ __('crypto_wallet.bank') }}</option><option value="Crypto">{{ __('crypto_wallet.crypto') }}</option></select>
        </div>
        <div id="recharge-bank-fields">
        <div class="form-group">
            <label for="recharge-currency">{{ translate('Currency') }} <span class="text-danger">*</span></label>
            <div class="d-flex align-items-center"><img id="recharge-fiat-logo" src="{{ static_asset('assets/img/currencies/myr.svg') }}" width="28" height="28" class="mr-2" alt="MYR">
            <select id="recharge-currency" name="currency" class="form-control aiz-selectpicker" required>
                <option value="MYR" data-content="&lt;img src='{{ static_asset('assets/img/currencies/myr.svg') }}' width='24' height='24' alt=''&gt; MYR — {{ translate('Malaysian Ringgit') }}" @if(!$rechargeRates['MYR']) disabled @endif>MYR — {{ translate('Malaysian Ringgit') }}</option>
            </select>
            </div>
        </div>
        <div class="form-group">
            <label for="recharge-amount">{{ translate('Amount') }} (<span id="recharge-currency-label">MYR</span>) <span class="text-danger">*</span></label>
            <input id="recharge-amount" class="form-control" dir="ltr" type="number" name="amount_local" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" placeholder="{{ translate('Please enter the amount') }}" required>
            <input type="hidden" name="amount" value="0.00">
            <div class="mt-2 text-muted">{{ translate('Exchange rate') }}: <bdi dir="ltr"><span id="recharge-rate"></span></bdi></div>
            <div class="mt-1 text-primary" aria-live="polite">{{ translate('Estimated amount') }}: <bdi dir="ltr">≈ <span id="recharge-usd">0.00</span> USD</bdi></div>
        </div>
        <div class="form-group">
            <label for="recharge-holder">{{ translate('Account holder') }} <span class="text-danger">*</span></label>
            <input id="recharge-holder" name="account_holder" class="form-control" maxlength="200" autocomplete="name" required>
        </div>
        <div class="form-group">
            <label for="recharge-bank">{{ translate('Bank Name') }} <span class="text-danger">*</span></label>
            <input id="recharge-bank" name="bank_name" class="form-control" maxlength="200" required>
        </div>
        <div class="form-group">
            <label for="recharge-account">{{ translate('Bank Account Number') }} <span class="text-danger">*</span></label>
            <input id="recharge-account" name="bank_account" class="form-control" dir="ltr" maxlength="100" autocomplete="off" required>
        </div>
        @include('partials.wallet_bank_instructions')
        </div>
        <div id="recharge-crypto-fields" hidden>
            <fieldset class="form-group"><legend class="fs-14">{{ __('crypto_wallet.asset') }}</legend><div class="row">@include('partials.crypto_asset_options', ['deposit' => true])</div></fieldset>
            <div class="alert alert-info">{{ __('crypto_wallet.deposit_hint') }}</div>
            <div class="form-group"><label>{{ __('crypto_wallet.network') }}</label><div id="recharge-crypto-network" class="fw-600"></div></div>
            <div class="form-group"><label for="recharge-crypto-address">{{ __('crypto_wallet.deposit_address') }}</label><input id="recharge-crypto-address" class="form-control" dir="ltr" readonly><button type="button" id="recharge-copy-address" class="btn btn-sm btn-light mt-2">{{ __('crypto_wallet.copy') }}</button><small id="recharge-copy-status" class="ml-2" role="status"></small></div>
            <div class="form-group"><label for="recharge-crypto-amount">{{ __('crypto_wallet.quantity') }} <span id="recharge-crypto-coin"></span> *</label><input id="recharge-crypto-amount" name="crypto_amount" type="number" class="form-control" min="0.00000001" max="9999999999.99999999" step="0.00000001" inputmode="decimal" disabled>
            <small id="recharge-crypto-rate" class="form-text text-muted"></small><div class="text-primary mt-1">≈ <span id="recharge-crypto-usd">0.00</span> USD</div></div>
            <div id="recharge-crypto-unavailable" class="alert alert-warning" hidden>{{ __('crypto_wallet.unavailable') }}</div>
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
        <div style="text-align: end"><button type="submit" class="btn btn-primary">{{ translate('Confirm') }}</button></div>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('seller-recharge-form');
    var rates = @json($rechargeRates);
    var assets = @json(\App\Services\CryptoWallet::assets());
    var hasBank = @json($bankMethods->isNotEmpty());
    var numberFormat = new Intl.NumberFormat(form.lang, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    function updateAmount() {
        var crypto = form.elements.recharge_method.value === 'Crypto';
        var selected = form.querySelector('input[name="crypto_asset"]:checked');
        var asset = selected && assets[selected.value];
        if (crypto) {
            form.querySelector('#recharge-crypto-network').textContent = asset ? asset.network : '';
            form.querySelector('#recharge-crypto-address').value = asset ? asset.address : '';
            form.querySelector('#recharge-crypto-coin').textContent = asset ? asset.coin : '';
            form.querySelector('#recharge-crypto-rate').textContent = asset ? '1 ' + asset.coin + ' = ' + asset.usd_price + ' USD' : '';
            var quantity = Number(form.elements.crypto_amount.value);
            form.querySelector('#recharge-crypto-usd').textContent = numberFormat.format(asset && quantity > 0 ? quantity * Number(asset.usd_price) : 0);
            form.querySelector('#recharge-crypto-unavailable').hidden = !!asset;
            form.querySelector('button[type="submit"]').disabled = !asset;
            return;
        }
        var currency = form.elements.currency.value, rate = Number(rates[currency]);
        form.querySelector('#recharge-fiat-logo').alt = currency;
        form.querySelector('button[type="submit"]').disabled = !hasBank || !(rate > 0);
        form.querySelector('#recharge-currency-label').textContent = currency;
        form.querySelector('#recharge-rate').textContent = rate > 0 ? '1 USD = ' + numberFormat.format(rate) + ' ' + currency : @json(translate('Exchange rate is not configured. Please contact support.'));
        form.elements.amount_local.setCustomValidity(rate > 0 ? '' : @json(translate('Exchange rate is not configured. Please contact support.')));
        var local = Number(form.elements.amount_local.value);
        var usd = rate > 0 && Number.isFinite(local) && local > 0 ? Math.round(Math.round(local * 100) / rate) / 100 : 0;
        form.elements.amount.value = usd.toFixed(2);
        form.querySelector('#recharge-usd').textContent = numberFormat.format(usd);
    }
    form.elements.amount_local.addEventListener('input', updateAmount);
    form.elements.crypto_amount.addEventListener('input', updateAmount);
    form.querySelectorAll('[name="crypto_asset"]').forEach(function (input) { input.addEventListener('change', function () { form.querySelector('#recharge-copy-status').textContent = ''; updateAmount(); }); });
    form.elements.currency.addEventListener('change', updateAmount);
    form.addEventListener('submit', updateAmount);
    var previous = @json(old('wallet_form') === 'recharge' ? old() : []);
    if (previous.currency && previous.currency !== 'MYR') delete previous.amount_local;
    if (previous.recharge_method === 'IBAN') previous.recharge_method = 'Bank';
    Object.keys(previous).forEach(function (name) { if (form.elements[name] && name !== '_token' && name !== 'crypto_asset' && name !== 'currency') form.elements[name].value = previous[name]; });
    var firstAsset = form.querySelector('[name="crypto_asset"]:not(:disabled)');
    if (previous.crypto_asset) firstAsset = form.querySelector('[name="crypto_asset"][value="' + previous.crypto_asset + '"]:not(:disabled)') || firstAsset;
    if (firstAsset) firstAsset.checked = true;
    function toggleMethod() {
        var crypto = form.elements.recharge_method.value === 'Crypto';
        form.querySelector('#recharge-bank-fields').hidden = crypto;
        form.querySelector('#recharge-crypto-fields').hidden = !crypto;
        form.querySelectorAll('#recharge-bank-fields input, #recharge-bank-fields select').forEach(function (el) { el.disabled = crypto; });
        form.elements.crypto_amount.disabled = !crypto;
        form.elements.crypto_amount.required = crypto;
        form.elements.trx_id.required = crypto;
        form.elements.trx_id.pattern = crypto ? '(0x)?[a-fA-F0-9]{64}' : '.*';
        form.querySelectorAll('[name="crypto_asset"]').forEach(function (el) { el.disabled = !crypto || !assets[el.value].deposit_enabled; el.required = crypto; });
        updateAmount();
    }
    form.elements.recharge_method.addEventListener('change', toggleMethod);
    form.querySelector('#recharge-copy-address').addEventListener('click', async function () {
        var input = form.querySelector('#recharge-crypto-address');
        if (!input.value) return;
        try { await navigator.clipboard.writeText(input.value); } catch (e) { input.select(); document.execCommand('copy'); }
        form.querySelector('#recharge-copy-status').textContent = @json(__('crypto_wallet.copied'));
    });
    if (window.jQuery && jQuery.fn.selectpicker) jQuery(form).find('.aiz-selectpicker').selectpicker();
    toggleMethod();
})();
</script>
