@extends('frontend.layouts.app')
@section('content')
@php
    $selectedCountry = old('country', $mode === 'register' ? 'AE' : null);
@endphp
<section class="py-5"><div class="container"><div class="card mx-auto" style="max-width:480px"><div class="card-body p-4">
    <h1 class="h4">{{ translate($mode === 'register' ? 'Register by phone' : ($mode === 'reset_password' ? 'Reset password by phone' : 'Log in by phone')) }}</h1>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <p id="phone-feedback" role="status" class="small"></p>
    <form method="POST" action="{{ route('phone.submit') }}" id="phone-auth-form">@csrf
        <input type="hidden" name="mode" value="{{ $mode }}">
        @if($mode === 'register' || $isSellerPortal)
        @include('frontend.partials.phone_registration_fields', ['phoneFormId' => 'phone-auth-form'])
        @else
        <label class="d-block">{{ translate('Country') }}<select class="form-control" name="country" required>
            @foreach($countries as $country)<option value="{{ $country['country'] }}" data-dial-code="{{ $country['dial_code'] }}" @if($selectedCountry === $country['country']) selected @endif>{{ $country['name'] }} ({{ $country['dial_code'] }})</option>@endforeach
        </select></label>
        <label class="d-block">{{ translate('International phone number') }}<input class="form-control" type="tel" name="phone" value="{{ old('phone') }}" placeholder="+971501234567" autocomplete="tel" maxlength="40" required></label>
        @endif
        @if($mode === 'register')<label class="d-block">{{ translate('Name') }}<input class="form-control" name="name" maxlength="191" value="{{ old('name') }}" required></label>@endif
        @if($mode === 'login')
        <label class="d-block">{{ translate('Login method') }}<select class="form-control" id="phone-login-method"><option value="code">{{ translate('SMS verification code') }}</option><option value="password">{{ translate('Password') }}</option></select></label>
        @endif
        <div id="phone-code-fields"><label class="d-block">{{ translate('Verification code') }}<input class="form-control" name="code" inputmode="numeric" pattern="[0-9]{5,6}" minlength="5" maxlength="6" autocomplete="one-time-code" required></label><button type="button" class="btn btn-outline-primary btn-sm mb-3" id="send-phone-code">{{ translate('Send code') }}</button></div>
        <div id="phone-password-fields" @if($mode === 'login') hidden @endif>
            <label class="d-block">{{ translate('Password') }}<input class="form-control" name="password" type="password" minlength="8" maxlength="100" autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}" @if($mode === 'login') disabled @else required @endif></label>
            @if($mode !== 'login')<label class="d-block">{{ translate('Confirm password') }}<input class="form-control" name="password_confirmation" type="password" minlength="8" maxlength="100" autocomplete="new-password" required></label>@endif
        </div>
        <button class="btn btn-primary btn-block mt-3">{{ translate($mode === 'register' ? 'Create Account' : ($mode === 'reset_password' ? 'Reset password' : 'Login')) }}</button>
    </form>
    <div class="mt-3 d-flex flex-wrap" style="gap:12px"><a href="{{ route('phone.access') }}">{{ translate('Phone login') }}</a><a href="{{ $isSellerPortal ? route('shops.create') : route('phone.access', ['mode'=>'register']) }}">{{ translate('Register') }}</a><a href="{{ route('phone.access', ['mode'=>'reset_password']) }}">{{ translate('Forgot password?') }}</a><a href="{{ route('user.login') }}">{{ translate('Email login') }}</a></div>
    @if($mode === 'register')<p class="small text-muted mt-3">{{ translate('Merchant accounts must complete the existing shop application and approval process.') }}</p>@endif
</div></div></div></section>
@endsection
@section('script')
@include('frontend.partials.phone_auth_script', ['phoneFormId' => 'phone-auth-form'])
@endsection
