(function ($) {
    'use strict';

    var element = document.getElementById('seller-notification-config');
    if (!element) return;
    var config = JSON.parse(element.textContent);
    var pollers = [];
    var stopped = false;
    var audio;

    function poll(url, delay, receive) {
        var timer;
        var pending;
        var disabled = false;
        function schedule() {
            clearTimeout(timer);
            if (!stopped && !disabled && !document.hidden) timer = setTimeout(run, delay);
        }
        function run() {
            clearTimeout(timer);
            if (stopped || disabled || document.hidden || pending) return;
            pending = $.ajax({
                url: url,
                dataType: 'json',
                timeout: 10000,
                success: receive,
                error: function (xhr, status) {
                    // A signed-out tab must not continuously request the login page.
                    if ([401, 403, 404, 419].indexOf(xhr.status) !== -1 || status === 'parsererror') disabled = true;
                },
                complete: function () { pending = null; schedule(); }
            });
        }
        pollers.push({
            resume: run,
            pause: function () { clearTimeout(timer); },
            stop: function () { clearTimeout(timer); if (pending) pending.abort(); }
        });
        schedule();
    }

    $(function () {
        if (config.showModal) $('#conversations-modal').modal('show');
        // The conversation menu can be disabled; do not poll for a missing badge.
        if (document.getElementById('conversations')) {
            poll(config.countUrl, 10000, function (data) {
                $('#conversations').toggle(Number(data.result) > 0);
            });
        }
        poll(config.messageUrl, 3000, function (data) {
            if (Number(data.code) !== 1) return;
            audio = audio || new Audio(config.audioUrl);
            audio.currentTime = 0;
            var playback = audio.play();
            if (playback && playback.catch) playback.catch(function () {});
        });
    });

    document.addEventListener('visibilitychange', function () {
        pollers.forEach(function (poller) { document.hidden ? poller.pause() : poller.resume(); });
    });
    window.addEventListener('pagehide', function () {
        stopped = true;
        pollers.forEach(function (poller) { poller.stop(); });
    });
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) return;
        stopped = false;
        pollers.forEach(function (poller) { poller.resume(); });
    });
})(jQuery);
