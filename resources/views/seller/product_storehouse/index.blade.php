@extends('seller.layouts.app')

@section('panel_content')

    <section class="gry-bg py-4 profile">
        <div class="container-fluid">
            <form class="" action="" method="POST" enctype="multipart/form-data" onsubmit="return false">
                @csrf
                <div class="row gutters-10">
                    <div class="col-md">
                        <div class="row gutters-5 mb-3">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <div class="form-group mb-0">
                                    <input class="form-control form-control-lg" type="text" name="keyword"
                                           placeholder="Search by Product Name/Barcode" onkeyup="filterProducts()">
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <select name="poscategory" class="form-control form-control-lg aiz-selectpicker"
                                        data-live-search="true" onchange="filterProducts()">
                                    <option value="">{{ translate('All Categories') }}</option>
                                    @foreach (\App\Models\Category::orderBy('order_level', 'desc')->get() as $key => $category)
                                        <option
                                            value="category-{{ $category->id }}">{{ $category->getTranslation('name') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <select name="brand" class="form-control form-control-lg aiz-selectpicker"
                                        data-live-search="true" onchange="filterProducts()">
                                    <option value="">{{ translate('All Brands') }}</option>
                                    @foreach (\App\Models\Brand::all() as $key => $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->getTranslation('name') }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="aiz-pos-product-list c-scrollbar-light">
                            <div class="d-flex flex-wrap justify-content-center" id="product-list">

                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between mt-3" style="gap:12px">
                            <div id="product-page-summary" role="status" aria-live="polite"></div>
                            <div class="aiz-pagination-nav d-flex flex-wrap align-items-center">
                                <nav aria-label="{{ translate('Pagination') }}"><div id="product-pagination" class="d-flex flex-wrap" style="gap:4px"></div></nav>
                                <div id="product-page-jump" class="d-none flex-shrink-0">@include('partials.page_jump')</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-auto w-md-350px w-lg-400px w-xl-500px">
                        <div class="card mb-3">
                            <div class="card-body">

                                <div class="">
                                    <div class="aiz-pos-cart-list mb-4 mt-3 c-scrollbar-light">
                                        <ul class="list-group list-group-flush" id="product-selection">
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pos-footer mar-btm">
                            <div class="d-flex flex-column flex-md-row justify-content-between">
                                <div class="d-flex">
                                    <button id="add-all-btn" type="button" class="btn btn-outline-info btn-block" disabled
                                            onclick="addPost(1)">
                                        <span class="spinner-border spinner-border-sm d-none" role="status"
                                              aria-hidden="true"></span>
                                        {{ translate('Add all to my product') }}
                                    </button>
                                </div>
                                <div class="my-2 my-md-0">
                                    <button id="add-selection-btn" type="button" class="btn btn-primary btn-block"
                                            onclick="addPost(0)">
                                        <span class="spinner-border spinner-border-sm d-none" role="status"
                                              aria-hidden="true"></span>
                                        {{ translate('Add to my product') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

@endsection

@section('script')
    <script type="text/javascript">

        var products = null;
        var productPage = 1, productTotal = 0;
        var productLoadId = 0, productRequest = null, productsLoading = false, productsPosting = false;

        $(document).ready(function () {
            var categorySelect = $('select[name=poscategory]');
            AIZ.plugins.bootstrapSelect();
            var categoryPicker = categorySelect.data('selectpicker');
            var categorySearch = categoryPicker.$searchbox[0];
            function updateCategoryOptions() {
                var searching = categorySearch.value.trim() !== '';
                var categoryIndex = 0;
                var changed = false;
                Array.from(categorySelect[0].options).forEach(function (option) {
                    var hidden = option.value !== '' && ++categoryIndex > 15 && !searching;
                    if (option.hidden !== hidden) { option.hidden = hidden; changed = true; }
                });
                if (changed) categorySelect.selectpicker('refresh');
            }
            // Update available options before bootstrap-select runs its live search.
            categorySearch.addEventListener('input', updateCategoryOptions, true);
            categorySelect.on('shown.bs.select', function () {
                updateCategoryOptions();
                categoryPicker.$searchbox.trigger('input');
            });
            updateCategoryOptions();
            // $('body').addClass('side-menu-closed');
            $('#product-list').on('click', '.add-plus:not(.c-not-allowed)', function () {
                var product_id = $(this).data('product-id');
                var product_name = $(this).data('product-name');
                var product_price = $(this).data('product-price');
                updateSelection(product_id, product_name, product_price);
            });
            $('#product-pagination').on('click', 'button[data-page]', function () {
                if (!productsLoading && !productsPosting) loadProductPage(Number($(this).attr('data-page')));
            });
            filterProducts();
        });

        function updateSelection(product_id, product_name, product_price) {
            let already_selected_ids = getSelectedIds()
            let container = $('#product-selection');

            if (!already_selected_ids.includes(product_id)) {
                container.append(`<li class="list-group-item py-3 pl-2" data-product-id="${product_id}">
                                            <div class="row gutters-5 align-items-center">

                                                <div class="col">
                                                    <div class="text-truncate-2">${product_name}</div>
                                                    <span
                                                        class="span badge badge-inline fs-12 badge-soft-secondary"></span>
                                                </div>
                                                <div class="col-auto">

                                                    <div class="fs-15 fw-600">${product_price}</div>
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button"
                                                            class="btn btn-circle btn-icon btn-sm btn-soft-danger ml-2 mr-0"
                                                            onclick="removeSelected(${product_id})">
                                                        <i class="las la-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </li>`)
            } else {
                container.find("li[data-product-id='" + product_id + "']")
                    .clearQueue().stop()
                    .fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100)
            }
        }

        function getSelectedIds() {
            let already_selected_ids = []
            $('#product-selection').find('li').each(function () {
                already_selected_ids.push($(this).data('product-id'))
            })
            return already_selected_ids
        }

        function removeSelected(product_id) {
            $('#product-selection').find("li[data-product-id='" + product_id + "']").remove()
        }

        function filterProducts() {
            if (productsPosting) return;
            loadProductPage(1);
        }

        async function loadProductPage(page) {
            var loadId = ++productLoadId;
            if (productRequest) productRequest.abort();
            productsLoading = true;
            $('#product-page-jump').addClass('d-none');
            products = null;
            $('#add-all-btn').prop('disabled', true);
            $('#product-pagination').empty();
            $('#product-list').empty().attr('aria-busy', 'true');
            $('#product-page-summary').text('{{ translate('Loading..') }}');
            var filters = {
                keyword: $('input[name=keyword]').val(),
                category: $('select[name=poscategory]').val(),
                brand: $('select[name=brand]').val()
            };
            try {
                productRequest = $.get('{{ route('seller.product_storehouse.search') }}', Object.assign({}, filters, {page: page}));
                var result = await productRequest;
                if (loadId !== productLoadId) return;
                productTotal = Number(result.meta.total);
                var lastPage = Math.max(1, Number(result.meta.last_page));
                if (page > lastPage) { loadProductPage(lastPage); return; }
                products = result;
                productPage = Number(result.meta.current_page);
                setProductList(products);
                if (!products.data.length) $('#product-list').text('{{ translate('Nothing more found.') }}');
                $('#product-page-summary').text('{{ translate('Total') }}: ' + productTotal + ' · ' + productPage + ' / ' + lastPage);
                renderProductPagination(lastPage);
            } catch (error) {
                if (loadId === productLoadId) {
                    products = null;
                    $('#product-page-summary').text('{{ translate('Something went wrong') }}');
                    $('<button>', {type: 'button', class: 'btn btn-soft-primary', text: '{{ translate('Retry') }}'})
                        .on('click', function () { loadProductPage(page); }).appendTo('#product-pagination');
                }
            } finally {
                if (loadId === productLoadId) {
                    productsLoading = false;
                    $('#product-list').attr('aria-busy', 'false');
                    $('#add-all-btn').prop('disabled', productsPosting || !products || !products.data.length);
                }
            }
        }

        function renderProductPagination(lastPage) {
            $('#product-page-jump').toggleClass('d-none', lastPage <= 1);
            window.configurePageJump(document.querySelector('#product-page-jump [data-page-jump]'), productPage, lastPage, function (page) {
                if (!productsLoading && !productsPosting) loadProductPage(page);
            });
            var container = $('#product-pagination').empty();
            function button(page, label, disabled) {
                $('<button>', {type: 'button', class: 'btn btn-sm ' + (page === productPage ? 'btn-primary' : 'btn-soft-primary'), text: label})
                    .attr('data-page', page).attr('aria-current', page === productPage ? 'page' : null)
                    .prop('disabled', disabled || page === productPage).appendTo(container);
            }
            button(productPage - 1, '{{ translate('Previous') }}', productPage <= 1);
            var pages = [1, lastPage];
            for (var page = Math.max(1, productPage - 2); page <= Math.min(lastPage, productPage + 2); page++) pages.push(page);
            pages = Array.from(new Set(pages)).sort(function (a, b) { return a - b; });
            pages.forEach(function (page, index) {
                if (index && page - pages[index - 1] > 1) $('<span>', {class: 'align-self-center px-1', text: '…'}).appendTo(container);
                button(page, page, false);
            });
            button(productPage + 1, '{{ translate('Next') }}', productPage >= lastPage);
        }

        function setProductList(data) {
            for (var i = 0; i < data.data.length; i++) {
                $('#product-list').append(
                    `<div class="w-140px w-xl-180px w-xxl-210px mx-2">
                        <div class="card bg-white c-pointer product-card hov-container">
                            <div class="position-relative">
                                <span class="absolute-top-left mt-1 ml-1 mr-0">
                                    ${data.data[i].qty > 0
                        ? `<span class="badge badge-inline badge-success fs-13">{{ translate('In stock') }}`
                        : `<span class="badge badge-inline badge-danger fs-13">{{ translate('Out of stock') }}`}
                                    : ${data.data[i].qty}</span>
                                </span>
                                ${data.data[i].variant != null
                        ? `<span class="badge badge-inline badge-warning absolute-bottom-left mb-1 ml-1 mr-0 fs-13 text-truncate">${data.data[i].variant}</span>`
                        : ''}
                                <img src="${data.data[i].thumbnail_image}" class="card-img-top img-fit h-120px h-xl-180px h-xxl-210px mw-100 mx-auto" >
                            </div>
                            <div class="card-body p-2 p-xl-3">
                                <div class="text-truncate fw-600 fs-14 mb-2">${data.data[i].name}</div>
                                <div class="">
                                    ${data.data[i].price != data.data[i].base_price
                        ? `<del class="mr-2 ml-0">${data.data[i].base_price}</del><span>${data.data[i].price}</span>`
                        : `<span>${data.data[i].base_price}</span>`
                    }
                                </div>
                            </div>
                            <div class="add-plus absolute-full rounded overflow-hidden hov-box ${data.data[i].qty <= 0 ? 'c-not-allowed' : ''}" data-product-id="${data.data[i].id}" data-product-name="${data.data[i].name}"  data-product-price="${data.data[i].price != data.data[i].base_price ? data.data[i].price : data.data[i].base_price}">
                                <div class="absolute-full bg-dark opacity-50">
                                </div>
                                <i class="las la-plus absolute-center la-6x text-white"></i>
                            </div>
                        </div>
                    </div>`
                );
            }
        }

        function addPost(all) {
            if (productsPosting || (all && productsLoading)) return;
            let addAllBtn = $('#add-all-btn')
            let addSelectionBtn = $('#add-selection-btn')
            if (all == 0) {
                let selected_ids = getSelectedIds()
                if (selected_ids.length) {
                    productsPosting = true;
                    addAllBtn.prop('disabled', true);
                    addSelectionBtn.prop('disabled', true);
                    addSelectionBtn.find('span.spinner-border').removeClass('d-none');
                    doPost(selected_ids)
                }
            } else {
                var pageIds = Array.from(new Set((products ? products.data : []).map(function (product) { return product.id; })));
                if (!pageIds.length) return;
                productsPosting = true;
                addAllBtn.prop('disabled', true);
                addSelectionBtn.prop('disabled', true);
                addAllBtn.find('span.spinner-border').removeClass('d-none');
                doPost(pageIds)
            }
        }

        function doPost(productIds) {
            let addAllBtn = $('#add-all-btn')
            let addSelectionBtn = $('#add-selection-btn')
            $.post('{{ route('seller.product_storehouse.add') }}', {
                _token: AIZ.data.csrf,
                all: 0,
                product_ids: productIds
            }, function (data) {
                if (data.success == 1) {
                    AIZ.plugins.notify('success', data.message ? data.message : '{{ translate('Product has been updated successfully') }}');
                    location.reload();
                } else {
                    AIZ.plugins.notify('danger', data.message ? data.message : '{{ translate('Something went wrong') }}');
                }
            }).fail(function () {
                AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
            }).always(function () {
                productsPosting = false;
                addAllBtn.prop('disabled', productsLoading || !products || !products.data.length);
                addSelectionBtn.prop('disabled', false);
                addAllBtn.find('span.spinner-border').addClass('d-none');
                addSelectionBtn.find('span.spinner-border').addClass('d-none');
            });
        }
    </script>
@endsection
