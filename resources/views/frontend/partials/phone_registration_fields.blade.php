@php
    $phoneCountry = $phoneCountry ?? 'SG';
    $phoneCountries = $phoneCountry === 'AE' ? [['country'=>'AE', 'name'=>'United Arab Emirates', 'dial_code'=>'+971']] : app(\App\Services\PhoneNumber::class)->countries();
    $savedPhone = old('phone', $phoneValue ?? '');
    $selectedCountry = old('country', preg_match('/^(?:(?:\+|00)90|90[0-9]{10}$)/', $savedPhone) ? 'TR' : $phoneCountry);
    if (!in_array($selectedCountry, array_column($phoneCountries, 'country'), true)) $selectedCountry = $phoneCountries[0]['country'] ?? 'SG';
@endphp
<input type="hidden" name="phone" value="{{ old('phone', $phoneValue ?? '') }}">
<label class="d-block" for="registration-phone">{{ translate('Phone') }}</label>
<div class="phone-number-fields mb-3" dir="ltr">
    <select class="form-control phone-number-country" name="country" aria-label="{{ translate('Country') }}" required>
        @forelse($phoneCountries as $phoneOption)
            <option value="{{ $phoneOption['country'] }}" @if($selectedCountry === $phoneOption['country']) selected @endif>{{ translate($phoneOption['name']) }} {{ $phoneOption['dial_code'] }}</option>
        @empty
            <option value="">{{ translate('No phone countries available') }}</option>
        @endforelse
    </select>
    <input class="form-control phone-number-input" id="registration-phone" type="tel" name="national_phone" value="{{ old('national_phone', old('phone', $phoneValue ?? '')) }}" autocomplete="tel-national" inputmode="tel" maxlength="40" required>
</div>
