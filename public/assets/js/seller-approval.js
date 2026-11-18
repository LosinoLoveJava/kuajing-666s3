(function (window, $) {
    'use strict';
    var options;
    var pending = Object.create(null);

    function inputs(id) {
        return $('[data-seller-approval]').filter(function () { return String(this.value) === String(id); });
    }

    function apply(state) {
        if (!state || (state.status !== 0 && state.status !== 1) || !Number.isInteger(state.version)) return false;
        inputs(state.id).each(function () {
            this.checked = state.status === 1;
            this.setAttribute('data-approved', state.status);
            this.setAttribute('data-approval-version', state.version);
        });
        return true;
    }

    function restore(id) {
        inputs(id).each(function () { this.checked = this.getAttribute('data-approved') === '1'; });
    }

    function lock(id, busy) {
        inputs(id).each(function () {
            this.disabled = busy || this.getAttribute('data-approval-readonly') === '1';
            this.setAttribute('aria-busy', busy ? 'true' : 'false');
        });
    }

    function read(id, url) {
        return $.ajax({url: url, method: 'GET', dataType: 'json', cache: false, timeout: 15000})
            .done(function (data) { if (data.success) apply(data.approval); });
    }

    window.SellerApproval = {
        configure: function (settings) {
            options = settings;
            $('[data-seller-approval]').each(function () {
                restore(this.value);
                lock(this.value, false);
            });
        },
        update: function (el) {
            var id = String(el.value);
            if (pending[id] || el.getAttribute('data-approval-readonly') === '1') {
                restore(id);
                return;
            }
            var status = el.checked ? 1 : 0;
            var version = Number(el.getAttribute('data-approval-version'));
            // Keep showing the last confirmed state until the server commits the change.
            restore(id);
            pending[id] = true;
            lock(id, true);
            function finish() { delete pending[id]; lock(id, false); }
            function failed(xhr) {
                var data = xhr && xhr.responseJSON;
                if (data && data.conflict && apply(data.approval)) {
                    AIZ.plugins.notify('warning', options.messages.conflict);
                    finish();
                    return;
                }
                restore(id);
                // A response may be lost after commit: reconcile without resubmitting the write.
                read(id, el.getAttribute('data-approval-status-url')).always(function () {
                    AIZ.plugins.notify('danger', options.messages.failed);
                    finish();
                });
            }
            $.ajax({url: options.url, method: 'POST', dataType: 'json', timeout: 20000,
                data: {_token: options.token, id: id, status: status, version: version}})
                .done(function (data) {
                    if (!data.success || !apply(data.approval)) { failed(); return; }
                    AIZ.plugins.notify('success', options.messages.saved);
                    finish();
                }).fail(failed);
        }
    };

    window.addEventListener('pageshow', function (event) {
        if (!options || !event.persisted) return;
        $('[data-seller-approval]').each(function () {
            var id = String(this.value);
            if (pending[id]) return;
            restore(id);
            lock(id, true);
            read(id, this.getAttribute('data-approval-status-url')).always(function () { lock(id, false); });
        });
    });
})(window, jQuery);
