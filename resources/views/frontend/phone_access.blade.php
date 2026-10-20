@extends('frontend.layouts.app')
@section('content')
@php
    $selectedCountry = old('country', $mode === 'register' ? 'AE' : null);
@endphp
@if($mode === 'register')
<style>
    #phone-auth-form .iti { display: block; width: 100%; }
    #phone-auth-form .iti__flag-container { display: none; }
    #phone-auth-form #registration-phone { padding-left: 12px; padding-right: 12px; }
</style>
@endif
<section class="py-5"><div class="container"><div class="card mx-auto" style="max-width:480px"><div class="card-body p-4">
    <h1 class="h4">{{ translate($mode === 'register' ? 'Register by phone' : ($mode === 'reset_password' ? 'Reset password by phone' : 'Log in by phone')) }}</h1>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <p id="phone-feedback" role="status" class="small"></p>
    <form method="POST" action="{{ route('phone.submit') }}" id="phone-auth-form">@csrf
        <input type="hidden" name="mode" value="{{ $mode }}">
        <label class="d-block">{{ translate('Country') }}<select class="form-control" name="country" required>
            @foreach($countries as $country)<option value="{{ $country['country'] }}" data-dial-code="{{ $country['dial_code'] }}" @if($selectedCountry === $country['country']) selected @endif>{{ $country['name'] }} ({{ $country['dial_code'] }})</option>@endforeach
        </select></label>
        @if($mode === 'register')
        <input type="hidden" name="phone" value="{{ old('phone') }}">
        <label class="d-block" for="registration-phone">{{ translate('Phone') }}</label>
        <input class="form-control mb-3" id="registration-phone" type="tel" name="national_phone" value="{{ old('national_phone', old('phone')) }}" autocomplete="tel-national" maxlength="40" required>
        @else
        <label class="d-block">{{ translate('International phone number') }}<input class="form-control" type="tel" name="phone" value="{{ old('phone') }}" placeholder="+971501234567" autocomplete="tel" maxlength="40" required></label>
        @endif
        @if($mode === 'register')<label class="d-block">{{ translate('Name') }}<input class="form-control" name="name" maxlength="191" value="{{ old('name') }}" required></label>@endif
        @if($mode === 'login')
        <label class="d-block">{{ translate('Login method') }}<select class="form-control" id="phone-login-method"><option value="code">{{ translate('SMS verification code') }}</option><option value="password">{{ translate('Password') }}</option></select></label>
        @endif
        <div id="phone-code-fields"><label class="d-block">{{ translate('Verification code') }}<input class="form-control" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><button type="button" class="btn btn-outline-primary btn-sm mb-3" id="send-phone-code">{{ translate('Send code') }}</button></div>
        <div id="phone-password-fields" @if($mode === 'login') hidden @endif>
            <label class="d-block">{{ translate('Password') }}<input class="form-control" name="password" type="password" minlength="8" maxlength="100" autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}" @if($mode === 'login') disabled @else required @endif></label>
            @if($mode !== 'login')<label class="d-block">{{ translate('Confirm password') }}<input class="form-control" name="password_confirmation" type="password" minlength="8" maxlength="100" autocomplete="new-password" required></label>@endif
        </div>
        <button class="btn btn-primary btn-block mt-3">{{ translate($mode === 'register' ? 'Create Account' : ($mode === 'reset_password' ? 'Reset password' : 'Login')) }}</button>
    </form>
    <div class="mt-3 d-flex flex-wrap" style="gap:12px"><a href="{{ route('phone.access') }}">{{ translate('Phone login') }}</a><a href="{{ route('phone.access', ['mode'=>'register']) }}">{{ translate('Register') }}</a><a href="{{ route('phone.access', ['mode'=>'reset_password']) }}">{{ translate('Forgot password?') }}</a><a href="{{ route('user.login') }}">{{ translate('Email login') }}</a></div>
    @if($mode === 'register')<p class="small text-muted mt-3">{{ translate('Merchant accounts must complete the existing shop application and approval process.') }}</p>@endif
</div></div></div></section>
@endsection
@section('script')
@if($mode === 'register')
<script src="{{ static_asset('assets/js/intlTelutils.js') }}"></script>
@endif
<script>
(function () {
    var form = document.getElementById('phone-auth-form'), button = document.getElementById('send-phone-code'), feedback = document.getElementById('phone-feedback');
    var syncPhone = function () { return true; };
    if (form.elements.mode.value === 'register') {
        var input = form.elements.national_phone;
        var iti = window.intlTelInput(input, {
            allowDropdown: false,
            nationalMode: true,
            initialCountry: form.elements.country.value.toLowerCase(),
            onlyCountries: Array.from(form.elements.country.options, function (option) { return option.value.toLowerCase(); }),
            preferredCountries: []
        });
        syncPhone = function () {
            var country = iti.getSelectedCountryData();
            if (country.iso2) form.elements.country.value = country.iso2.toUpperCase();
            form.elements.phone.value = iti.getNumber();
            var valid = !!country.iso2 && iti.isValidNumber();
            input.setCustomValidity(input.value && !valid ? @json(translate('Invalid phone number')) : '');
            return valid;
        };
        function readPhone() {
            var value = input.value.trim();
            if (/^(\+|00)/.test(value)) iti.setNumber(value.replace(/^00/, '+'));
            syncPhone();
        }
        form.elements.country.addEventListener('change', function () {
            iti.setCountry(this.value.toLowerCase());
            syncPhone();
        });
        input.addEventListener('input', readPhone);
        input.addEventListener('change', readPhone);
        input.addEventListener('blur', function () {
            readPhone();
            if (iti.isValidNumber()) input.value = iti.getNumber(intlTelInputUtils.numberFormat.NATIONAL);
        });
        input.addEventListener('countrychange', syncPhone);
        form.addEventListener('submit', function (event) {
            readPhone();
            if (!syncPhone()) { event.preventDefault(); input.reportValidity(); }
        });
        readPhone();
    }
    var method = document.getElementById('phone-login-method');
    if (method) method.addEventListener('change', function () {
        var useCode = method.value === 'code';
        document.getElementById('phone-code-fields').hidden = !useCode;
        document.getElementById('phone-password-fields').hidden = useCode;
        form.elements.code.disabled = !useCode; form.elements.code.required = useCode;
        form.elements.password.disabled = useCode; form.elements.password.required = !useCode;
        form.elements.password.minLength = 1;
    });
    button.addEventListener('click', async function () {
        if (!syncPhone()) { form.elements.national_phone.reportValidity(); return; }
        button.disabled = true; feedback.textContent = @json(translate('Sending...'));
        try {
            var response = await fetch(@json(route('phone.send')), {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.elements._token.value}, body:JSON.stringify({phone:form.elements.phone.value,country:form.elements.country.value,purpose:form.elements.mode.value})});
            var data = await response.json();
            if (!response.ok || !data.result) { throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'SMS submission failed.')); }
            feedback.textContent = data.message;
            var remaining = 60;
            button.textContent = remaining + 's';
            var timer = setInterval(function () { remaining--; button.textContent = remaining + 's'; if (!remaining) { clearInterval(timer); button.disabled=false; button.textContent=@json(translate('Send code')); } }, 1000);
        } catch (error) { feedback.textContent=error.message; button.disabled=false; }
    });
})();
</script>
@endsection
