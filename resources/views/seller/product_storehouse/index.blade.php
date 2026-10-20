@extends('seller.layouts.app')

@section('panel_content')

    <section class="gry-bg py-4 profile">
        <div class="container-fluid">
            <form class="" action="" method="POST" enctype="multipart/form-data">
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
                                    @foreach (\App\Models\Category::all() as $key => $category)
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
                            <div id="load-more" class="text-center">
                                <div class="fs-14 d-inline-block fw-600 btn btn-soft-primary c-pointer"
                                     onclick="loadMoreProduct()">{{ translate('Loading..') }}</div>
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
                                    <button id="add-all-btn" type="button" class="btn btn-outline-info btn-block"
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

        $(document).ready(function () {
            // $('body').addClass('side-menu-closed');
            $('#product-list').on('click', '.add-plus:not(.c-not-allowed)', function () {
                var product_id = $(this).data('product-id');
                var product_name = $(this).data('product-name');
                var product_price = $(this).data('product-price');
                updateSelection(product_id, product_name, product_price);
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
            var keyword = $('input[name=keyword]').val();
            var category = $('select[name=poscategory]').val();
            var brand = $('select[name=brand]').val();
            $.get('{{ route('seller.product_storehouse.search') }}', {
                keyword: keyword,
                category: category,
                brand: brand
            }, function (data) {
                products = data;
                $('#product-list').html(null);
                setProductList(data);
            });
        }

        function loadMoreProduct() {
            if (products != null && products.links.next != null) {
                $('#load-more').find('.btn').html('{{ translate('Loading..') }}');
                $.get(products.links.next, {}, function (data) {
                    products = data;
                    setProductList(data);
                });
            }
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
            if (data.links.next != null) {
                $('#load-more').find('.btn').html('{{ translate('Load More.') }}');
            } else {
                $('#load-more').find('.btn').html('{{ translate('Nothing more found.') }}');
            }
        }

        function addPost(all) {
            let addAllBtn = $('#add-all-btn')
            let addSelectionBtn = $('#add-selection-btn')
            if (all == 0) {
                let selected_ids = getSelectedIds()
                if (selected_ids.length) {
                    addAllBtn.prop('disabled', true);
                    addSelectionBtn.prop('disabled', true);
                    addSelectionBtn.find('span.spinner-border').removeClass('d-none');
                    doPost(0, selected_ids)
                }
            } else {
                addAllBtn.prop('disabled', true);
                addSelectionBtn.prop('disabled', true);
                addAllBtn.find('span.spinner-border').removeClass('d-none');
                doPost(1, [])
            }
        }

        function doPost(all, productIds) {
            let addAllBtn = $('#add-all-btn')
            let addSelectionBtn = $('#add-selection-btn')
            $.post('{{ route('seller.product_storehouse.add') }}', {
                _token: AIZ.data.csrf,
                all: all,
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
                addAllBtn.prop('disabled', false);
                addSelectionBtn.prop('disabled', false);
                addAllBtn.find('span.spinner-border').addClass('d-none');
                addSelectionBtn.find('span.spinner-border').addClass('d-none');
            });
        }
    </script>
@endsection
