<script>
(function () {
    var form = document.getElementById('shop');
    var front = form && form.querySelector('[name="identity_card_front"]');
    if (!front) return;
    var uploader = front.closest('.input-group');
    var error = document.createElement('div');
    error.id = 'identity-front-error';
    error.className = 'text-danger small mt-1';
    error.setAttribute('role', 'alert');
    error.textContent = @json(translate('Identity Card Front Not Allow Empty!'));
    error.hidden = true;
    uploader.parentNode.insertBefore(error, uploader.nextSibling);
    uploader.tabIndex = 0;
    uploader.setAttribute('aria-required', 'true');
    uploader.setAttribute('aria-describedby', error.id);
    // Native required validation ignores hidden uploader fields.
    form.addEventListener('submit', function (event) {
        var missing = !front.value.trim();
        error.hidden = !missing;
        uploader.setAttribute('aria-invalid', missing ? 'true' : 'false');
        if (missing) {
            event.preventDefault();
            event.stopImmediatePropagation();
            uploader.focus();
        }
    }, true);
})();
</script>
