@extends(($isSellerPortal ?? false) ? 'backend.layouts.layout' : 'frontend.layouts.app')
@section('content')
<section class="py-5 {{ ($isSellerPortal ?? false) ? 'bg-cover bg-center d-flex align-items-center' : '' }}"
    @if($isSellerPortal ?? false) style="min-height:100vh; background-image:url({{ uploaded_asset(get_setting('admin_login_background')) }}); background-size:auto; background-position:center top;" @endif>
    <div class="container">
        <div class="row">
            <div class="{{ ($isSellerPortal ?? false) ? 'col-lg-6 col-xl-4 mx-auto' : 'col-12 mx-auto' }}" @if(!($isSellerPortal ?? false)) style="max-width:510px" @endif>
                <div class="card text-left mb-0"><div class="card-body p-4">
    @if($isSellerPortal ?? false)
        <div class="mb-5 text-center">
            <img src="{{ get_setting('system_logo_black') != null ? uploaded_asset(get_setting('system_logo_black')) : static_asset('assets/img/logo.png') }}" class="mw-100 mb-4" height="40" alt="{{ config('app.name') }}">
            <h1 class="h3 text-primary mb-0">{{ translate('Welcome to') }} {{ config('app.name') }}</h1>
            <p>{{ translate('Login to your account.') }}</p>
        </div>
    @else
        <h1 class="h4">{{ translate($mode === 'register' ? 'Register by phone' : ($mode === 'reset_password' ? 'Reset password by phone' : 'Log in by phone')) }}</h1>
    @endif
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <p id="phone-feedback" role="status" class="small"></p>
    <form method="POST" action="{{ route(($isSellerPortal ?? false) ? 'seller.phone.submit' : 'phone.submit') }}" id="phone-auth-form">@csrf
        <input type="hidden" name="mode" value="{{ $mode }}">
        @include('frontend.partials.phone_registration_fields', ['phoneFormId' => 'phone-auth-form'])
        @if($mode === 'register')<label class="d-block">{{ translate('Name') }}<input class="form-control" name="name" maxlength="191" value="{{ old('name') }}" required></label>@endif
        @if($mode !== 'login')
        <div id="phone-code-fields"><label class="d-block">{{ translate('Verification code') }}<input class="form-control" name="code" inputmode="numeric" pattern="[0-9]{5,6}" minlength="5" maxlength="6" autocomplete="one-time-code" required></label><button type="button" class="btn btn-outline-primary btn-sm mb-3" id="send-phone-code">{{ translate('Send code') }}</button></div>
        @endif
        <div id="phone-password-fields">
            <label class="d-block">{{ translate('Password') }}<input class="form-control" name="password" type="password" minlength="{{ $mode === 'login' ? 1 : 8 }}" maxlength="100" autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}" required></label>
            @if($mode !== 'login')<label class="d-block">{{ translate('Confirm password') }}<input class="form-control" name="password_confirmation" type="password" minlength="8" maxlength="100" autocomplete="new-password" required></label>@endif
        </div>
        <button class="btn btn-primary btn-block mt-3">{{ translate($mode === 'register' ? 'Create Account' : ($mode === 'reset_password' ? 'Reset password' : 'Login')) }}</button>
    </form>
    <div class="mt-3 d-flex flex-wrap" style="gap:12px"><a href="{{ $isSellerPortal ? route('login', ['portal' => 'seller']) : route('user.login', ['portal' => 'customer']) }}">{{ translate('Phone login') }}</a><a href="{{ $isSellerPortal ? route('shops.create', ['source' => 'seller', 'portal' => 'seller']) : route('phone.access', ['mode'=>'register', 'portal'=>'customer']) }}">{{ translate('Register') }}</a><a href="{{ $isSellerPortal ? route('seller.phone.reset') : route('phone.access', ['mode'=>'reset_password', 'portal'=>'customer']) }}">{{ translate('Forgot password?') }}</a></div>
    @if($mode === 'register')<p class="small text-muted mt-3">{{ translate('Merchant accounts must complete the existing shop application and approval process.') }}</p>@endif
                </div></div>
            </div>
        </div>
    </div>
</section>
@endsection
@section('script')
@include('frontend.partials.phone_auth_script', ['phoneFormId' => 'phone-auth-form', 'phoneSendRoute' => ($isSellerPortal ?? false) ? 'seller.phone.send' : 'phone.send'])
@endsection
