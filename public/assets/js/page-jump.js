(function () {
    'use strict';
    function configure(container, current, last, navigate) {
        var input = container.querySelector('input');
        input.value = current;
        input.max = last;
        container.querySelector('[data-page-jump-total]').textContent = '/ ' + last;
        container.classList.toggle('d-none', last <= 1);
        function jump(event) {
            if (event) { event.preventDefault(); event.stopPropagation(); }
            if (!input.reportValidity()) return;
            var page = Number(input.value);
            if (Number.isInteger(page) && page >= 1 && page <= last && page !== Number(current)) navigate(page);
        }
        container.querySelector('button').onclick = jump;
        input.onkeydown = function (event) { if (event.key === 'Enter') jump(event); };
    }
    window.configurePageJump = configure;
    function initialize(root, navigate) {
        (root || document).querySelectorAll('[data-pagination-jump-source]').forEach(function (source) {
            configure(source.querySelector('[data-page-jump]'), Number(source.dataset.current), Number(source.dataset.last), function (page) {
                var target = new URL(source.dataset.url, window.location.href);
                var current = new URL(window.location.href);
                var pageName = source.dataset.pageName;
                // Preserve current filters, other paginators and tab fragments.
                current.searchParams.forEach(function (value, key) {
                    if (key !== pageName && !target.searchParams.has(key)) {
                        current.searchParams.getAll(key).forEach(function (item) { target.searchParams.append(key, item); });
                    }
                });
                target.searchParams.set(pageName, page);
                if (!target.hash) target.hash = current.hash;
                if (navigate) navigate(page, target.href);
                else window.location.assign(target.href);
            });
        });
    }
    window.initializePageJumps = initialize;
    initialize(document);
})();
