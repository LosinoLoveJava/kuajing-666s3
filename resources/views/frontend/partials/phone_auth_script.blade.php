@if($mode === 'register' || ($isSellerPortal ?? false))
<script src="{{ static_asset('assets/js/intlTelutils.js') }}"></script>
@endif
<script>
(function () {
    var form = document.getElementById(@json($phoneFormId)), button = document.getElementById('send-phone-code'), feedback = document.getElementById('phone-feedback');
    var syncPhone = function () { return true; };
    if (form.elements.national_phone) {
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
    if (button) button.addEventListener('click', async function () {
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
