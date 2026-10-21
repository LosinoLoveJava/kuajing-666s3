@auth
<script src="https://plugin-code.salesmartly.com/js/project_840494_873536_1791129077.js"></script>
<script>
    if (window.ssq) {
        // Use the authenticated account ID, never a value supplied by the browser.
        window.ssq.push('setLoginInfo', {
            user_id: @json((string) Auth::id()),
            user_name: @json('User #' . Auth::id())
        });
    }
</script>
@endauth
