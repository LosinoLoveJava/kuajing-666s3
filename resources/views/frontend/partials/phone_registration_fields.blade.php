@php $selectedCountry = old('country', 'AE'); @endphp
<style>
    #{{ $phoneFormId }} .iti { display: block; width: 100%; }
    #{{ $phoneFormId }} .iti__flag-container { display: none; }
    #{{ $phoneFormId }} #registration-phone { padding-left: 12px; padding-right: 12px; }
</style>
<label class="d-block">{{ translate('Country') }}<select class="form-control" name="country" required>
    @foreach($countries as $country)<option value="{{ $country['country'] }}" data-dial-code="{{ $country['dial_code'] }}" @if($selectedCountry === $country['country']) selected @endif>{{ $country['name'] }} ({{ $country['dial_code'] }})</option>@endforeach
</select></label>
<input type="hidden" name="phone" value="{{ old('phone') }}">
<label class="d-block" for="registration-phone">{{ translate('Phone') }}</label>
<input class="form-control mb-3" id="registration-phone" type="tel" name="national_phone" value="{{ old('national_phone', old('phone')) }}" autocomplete="tel-national" maxlength="40" required>
