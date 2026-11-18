@if(Auth::check() || ($allowGuest ?? false))
<script>
    (function () {
        var script = document.createElement('script');
        script.src = 'https://plugin-code.salesmartly.com/js/project_840494_873536_1791129077.js';
        script.async = true;
        @auth
        script.onload = function () {
            if (window.ssq) {
                // Use the authenticated account ID after the chat SDK is ready.
                window.ssq.push('setLoginInfo', {
                    user_id: @json((string) Auth::id()),
                    user_name: @json('User #' . Auth::id())
                });
            }
        };
        @endauth
        document.head.appendChild(script);
    }());
</script>
@endif
