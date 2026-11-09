<input type="hidden" name="country" value="AE">
<input type="hidden" name="phone" value="{{ old('phone', $phoneValue ?? '') }}">
<label class="d-block" for="registration-phone">{{ translate('Phone') }}</label>
<div class="input-group mb-3" dir="ltr">
    <div class="input-group-prepend"><span class="input-group-text" aria-label="{{ translate('Country') }}: +971">+971</span></div>
    <input class="form-control" id="registration-phone" type="tel" name="national_phone" value="{{ old('national_phone', old('phone', $phoneValue ?? '')) }}" autocomplete="tel-national" inputmode="tel" maxlength="40" required>
</div>
