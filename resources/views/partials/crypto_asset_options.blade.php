@foreach(\App\Services\CryptoWallet::assets() as $assetCode => $cryptoAsset)
<label class="col-6 mb-2" style="cursor:pointer">
    <span class="d-flex align-items-center border rounded p-2 h-100">
        <input type="radio" name="crypto_asset" value="{{ $assetCode }}" class="mr-2" @if(!$cryptoAsset[($deposit ?? false) ? 'deposit_enabled' : 'enabled']) disabled @endif>
        <img src="{{ static_asset('assets/img/currencies/'.$cryptoAsset['logo'].'.svg') }}" width="28" height="28" alt="" class="mr-2">
        <span><span class="d-block fw-600">{{ $cryptoAsset['label'] }}</span><small class="text-muted">{{ $cryptoAsset['network'] }}</small></span>
    </span>
</label>
@endforeach
