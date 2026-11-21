@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar mt-2 mb-3"><h1 class="h3">{{ __('crypto_wallet.settings') }}</h1></div>
@if(session('crypto_saved'))<div class="alert alert-success" role="status">{{ __('crypto_wallet.saved') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<form action="{{ route('crypto_wallet.settings.update') }}" method="post">
    @csrf
    <div class="alert alert-info">{{ __('crypto_wallet.settings_hint') }}</div>
    <div class="row">
    @foreach($assets as $code => $asset)
        <div class="col-lg-6"><div class="card"><div class="card-header">
            <h2 class="h6 mb-0"><img src="{{ static_asset('assets/img/currencies/'.$asset['logo'].'.svg') }}" width="28" height="28" alt=""> {{ $asset['label'] }}</h2>
        </div><div class="card-body">
            <div class="form-group"><label for="crypto-enabled-{{ $code }}"><input id="crypto-enabled-{{ $code }}" type="checkbox" name="assets[{{ $code }}][enabled]" value="1" @if(old('assets.'.$code.'.enabled', session()->hasOldInput() ? false : $asset['enabled'])) checked @endif> {{ __('crypto_wallet.enabled') }}</label></div>
            <p class="text-muted">{{ __('crypto_wallet.network') }}: {{ $asset['network'] }}</p>
            <div class="form-group"><label for="crypto-address-{{ $code }}">{{ __('crypto_wallet.deposit_address') }}</label>
                <input id="crypto-address-{{ $code }}" class="form-control" dir="ltr" name="assets[{{ $code }}][address]" value="{{ old('assets.'.$code.'.address', $asset['address']) }}" maxlength="128" autocomplete="off" spellcheck="false">
                @error('assets.'.$code.'.address')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group"><label for="crypto-price-{{ $code }}">1 {{ $asset['coin'] }} = USD</label>
                <input id="crypto-price-{{ $code }}" class="form-control" type="number" min="0.00000001" max="9999999999.99999999" step="0.00000001" name="assets[{{ $code }}][usd_price]" value="{{ old('assets.'.$code.'.usd_price', $asset['usd_price']) }}">
                @error('assets.'.$code.'.usd_price')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
        </div></div></div>
    @endforeach
    </div>
    <div class="text-right mb-4"><button class="btn btn-primary" type="submit">{{ translate('Save') }}</button></div>
</form>
@endsection
