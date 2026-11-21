<div id="pos-pagination-status" class="text-center mt-2" role="status"></div>
<div class="aiz-pagination-nav d-flex flex-wrap align-items-center mt-2 mb-3">
    <nav id="pos-pagination" class="d-flex flex-wrap" style="gap: 4px;" aria-label="{{ __('pagination.page') }}"></nav>
    <div id="pos-page-jump" class="d-none flex-shrink-0">@include('partials.page_jump')</div>
</div>
@once
<script>
    var posPageRequest = null, posPageRequestId = 0;
    function resetPosPriceFilters() {
        $('input[name=min_price], input[name=max_price]').val('');
        loadPosProductPage(1);
    }
    function loadPosProductPage(page) {
        var requestId = ++posPageRequestId;
        if (posPageRequest) posPageRequest.abort();
        $('#pos-pagination').empty();
        $('#pos-page-jump').addClass('d-none');
        $('#product-list').empty().attr('aria-busy', 'true');
        var minPrice = $('input[name=min_price]'), maxPrice = $('input[name=max_price]');
        var invalidPrice = (minPrice.length && !minPrice[0].validity.valid) || (maxPrice.length && !maxPrice[0].validity.valid);
        var invalidRange = minPrice.val() !== '' && maxPrice.val() !== '' && Number(minPrice.val()) > Number(maxPrice.val());
        $('#pos-price-error').toggleClass('d-none', !invalidPrice && !invalidRange)
            .text(invalidPrice ? @json(__('pos.invalid_price')) : @json(__('pos.invalid_range')));
        if (invalidPrice || invalidRange) {
            $('#pos-pagination-status').empty();
            $('#product-list').attr('aria-busy', 'false');
            return;
        }
        $('#pos-pagination-status').text(@json(translate('Loading..')));
        posPageRequest = $.get(@json(route('pos.search_product')), {
            page: page, keyword: $('input[name=keyword]').val(),
            category: $('select[name=poscategory]').val(), brand: $('select[name=brand]').val(),
            user_id: $('select[name=shop_id]').val(),
            min_price: minPrice.val(), max_price: maxPrice.val(), sort: $('select[name=sort]').val() || 'price_asc'
        }).done(function (data) {
            if (requestId !== posPageRequestId) return;
            var last = Math.max(1, Number(data.meta.last_page));
            if (page > last) { loadPosProductPage(last); return; }
            products = data;
            setProductList(data);
            $('#pos-pagination-status').text(data.data.length ? '' : @json(translate('Nothing found')));
            renderPosPagination(data.meta);
        }).fail(function (xhr, status) {
            if (requestId !== posPageRequestId || status === 'abort') return;
            var errors = xhr.responseJSON && xhr.responseJSON.errors;
            $('#pos-pagination-status').text(errors ? Object.values(errors)[0][0] : @json(translate('Something went wrong')));
            $('<button>', {type: 'button', class: 'btn btn-sm btn-primary', text: @json(translate('Retry'))})
                .on('click', function () { loadPosProductPage(page); }).appendTo('#pos-pagination');
        }).always(function () {
            if (requestId === posPageRequestId) $('#product-list').attr('aria-busy', 'false');
        });
    }
    function renderPosPagination(meta) {
        var current = Number(meta.current_page), last = Math.max(1, Number(meta.last_page));
        var container = $('#pos-pagination').empty();
        if (last <= 1) return;
        function button(page, label, disabled) {
            $('<button>', {type: 'button', class: 'btn btn-sm ' + (page === current ? 'btn-primary' : 'btn-soft-primary'), text: label})
                .prop('disabled', disabled || page === current).attr('aria-current', page === current ? 'page' : null)
                .on('click', function () { loadPosProductPage(page); }).appendTo(container);
        }
        button(current - 1, @json(__('pagination.previous')), current <= 1);
        var pages = [1, last];
        for (var page = Math.max(1, current - 2); page <= Math.min(last, current + 2); page++) pages.push(page);
        pages = Array.from(new Set(pages)).sort(function (a, b) { return a - b; });
        pages.forEach(function (page, index) {
            if (index && page - pages[index - 1] > 1) $('<span>', {class: 'align-self-center px-1', text: '…'}).appendTo(container);
            button(page, page, false);
        });
        button(current + 1, @json(__('pagination.next')), current >= last);
        $('#pos-page-jump').removeClass('d-none');
        window.configurePageJump(document.querySelector('#pos-page-jump [data-page-jump]'), current, last, loadPosProductPage);
    }
</script>
@endonce
