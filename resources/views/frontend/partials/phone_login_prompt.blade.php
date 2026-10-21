<a class="btn btn-primary btn-block" href="{{ route('phone.access') }}">{{ translate('Phone login') }}</a>
<div class="text-right mt-3">
    <a href="{{ route('phone.access', ['mode' => 'reset_password']) }}">{{ translate('Forgot password?') }}</a>
</div>
