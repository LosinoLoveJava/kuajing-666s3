<script src="{{ static_asset('assets/js/intlTelutils.js') }}"></script>
<script>
(function () {
    var form = document.getElementById(@json($phoneFormId)), button = document.getElementById('send-phone-code'), feedback = document.getElementById('phone-feedback');
    var syncPhone = function () { return true; };
    if (form.elements.national_phone) {
        var input = form.elements.national_phone;
        var country = form.elements.country.value;
        var dialCode = country === 'SG' ? '65' : '971';
        syncPhone = function () {
            var number = input.value.replace(/[\s().-]+/g, '');
            if (number.indexOf('00') === 0) number = '+' + number.slice(2);
            if (number && number.charAt(0) !== '+') {
                var hasDialCode = country === 'SG' ? number.length === 10 && number.indexOf(dialCode) === 0 : number.indexOf(dialCode) === 0;
                number = hasDialCode ? '+' + number : '+' + dialCode + (country === 'SG' ? number : number.replace(/^0+/, ''));
            }
            form.elements.country.value = country;
            form.elements.phone.value = number;
            var pattern = country === 'SG' ? /^\+65[3689][0-9]{7}$/ : /^\+971[1-9][0-9]{7,8}$/;
            var valid = pattern.test(number) && intlTelInputUtils.isValidNumber(number, country);
            input.setCustomValidity(input.value && !valid ? @json(translate('Invalid phone number')) : '');
            return valid;
        };
        function readPhone() { return syncPhone(); }
        input.addEventListener('input', readPhone);
        input.addEventListener('change', readPhone);
        input.addEventListener('blur', function () {
            if (syncPhone()) input.value = intlTelInputUtils.formatNumber(form.elements.phone.value, country, intlTelInputUtils.numberFormat.NATIONAL);
        });
        form.addEventListener('submit', function (event) {
            if (!syncPhone()) { event.preventDefault(); input.reportValidity(); }
        });
        if (readPhone()) input.value = intlTelInputUtils.formatNumber(form.elements.phone.value, country, intlTelInputUtils.numberFormat.NATIONAL);
    }
    if (button) button.addEventListener('click', async function () {
        if (!syncPhone()) { form.elements.national_phone.reportValidity(); return; }
        button.disabled = true; feedback.textContent = @json(translate('Sending...'));
        try {
            var response = await fetch(@json(route($phoneSendRoute ?? 'phone.send')), {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.elements._token.value}, body:JSON.stringify({phone:form.elements.phone.value,country:form.elements.country.value,purpose:form.elements.mode.value})});
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
