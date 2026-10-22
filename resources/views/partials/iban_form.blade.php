@php($manualReview = $manualReview ?? false)
<div class="bank-recipient-fields mb-3" @if(!($fixedBank ?? false)) hidden @endif>
    <div class="form-group"><label for="withdraw-holder">{{ translate('Account holder') }} @unless($manualReview)<span class="text-danger">*</span>@endunless</label><input id="withdraw-holder" class="form-control" name="iban_holder" value="{{ old('iban_holder') }}" maxlength="{{ $manualReview ? 2000 : 200 }}" @unless($manualReview) data-bank-required @endunless></div>
    <div class="form-group"><label for="withdraw-bank">{{ translate('Bank Name') }} @unless($manualReview)<span class="text-danger">*</span>@endunless</label><input id="withdraw-bank" class="form-control" name="iban_bank" value="{{ old('iban_bank') }}" maxlength="{{ $manualReview ? 2000 : 200 }}" @unless($manualReview) data-bank-required @endunless></div>
    <div class="form-group"><label for="withdraw-account">{{ translate('Bank Account Number') }}</label><input id="withdraw-account" class="form-control" name="bank_account" value="{{ old('bank_account') }}" maxlength="{{ $manualReview ? 2000 : 100 }}" dir="ltr"></div>
</div>
<div class="iban-fields mb-3" @if(!($fixedBank ?? false)) hidden @endif>
    <div class="form-group"><label for="withdraw-iban-country">{{ translate('Account country') }} @unless($manualReview)<span class="text-danger">*</span>@endunless</label>
        @if($manualReview)
        <input id="withdraw-iban-country" name="iban_country" class="form-control" value="{{ old('iban_country') }}" maxlength="2000">
        @else
        <select id="withdraw-iban-country" name="iban_country" class="form-control" data-iban-required>
            @foreach(config('iban.lengths') as $code => $length)<option value="{{ $code }}" @if($code === old('iban_country', 'AE')) selected @endif>{{ $code }}</option>@endforeach
        </select>
        @endif
    </div>
    <div class="form-group"><label for="withdraw-iban-number">IBAN @unless($manualReview)<span class="text-danger">*</span>@endunless</label><input id="withdraw-iban-number" class="form-control" name="iban_number" value="{{ old('iban_number') }}" maxlength="{{ $manualReview ? 2000 : 64 }}" autocomplete="off" @unless($manualReview) data-iban-required @endunless></div>
    <div class="form-group">
        <label for="withdraw-iban-bic">SWIFT / BIC ({{ translate('Optional') }})</label>
        <input id="withdraw-iban-bic" class="form-control" name="iban_bic" value="{{ old('iban_bic') }}" maxlength="{{ $manualReview ? 2000 : 11 }}" dir="ltr" autocomplete="off" spellcheck="false" @if($manualReview) aria-describedby="withdraw-bic-hint" @endif>
        @if($manualReview)<small id="withdraw-bic-hint" class="form-text text-muted">{{ __('seller_wallet.manual_review_hint') }}</small>@endif
        @if(old('wallet_form') === 'withdraw')
            @error('iban_bic')<div class="text-danger" role="alert">{{ $message }}</div>@enderror
        @endif
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if(($fixedBank ?? false) && !$manualReview)
    var fixedForm = document.querySelector('#request_modal form');
    if (fixedForm) {
        fixedForm.querySelectorAll('[data-bank-required], [data-iban-required], [name="bank_account"]').forEach(function (input) { input.required = true; });
    }
    @endif
    document.querySelectorAll('select[name="w_type"]').forEach(function (select) {
        var form = select.closest('form'), fields = form.querySelector('.iban-fields'), bank = form.querySelector('.bank-recipient-fields');
        if (!fields) return;
        function toggle() {
            var shown = select.value === '4';
            var bankShown = shown || select.value === '2';
            bank.hidden = !bankShown;
            bank.querySelectorAll('input').forEach(function (el) {
                el.disabled = !bankShown;
                el.required = @json(!$manualReview) && bankShown && (el.hasAttribute('data-bank-required') || (el.name === 'bank_account' && select.value === '2'));
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
