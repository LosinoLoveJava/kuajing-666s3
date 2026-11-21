@once
<style>
    .pos-price-filters ~ .aiz-pos-product-list { height: calc(100vh - 300px); max-height: calc(100vh - 300px); min-height: 280px; }
    @media (max-width: 991.98px) {
        .pos-price-filters ~ .aiz-pos-product-list { height: calc(100vh - 360px); max-height: calc(100vh - 360px); }
    }
    @media (max-width: 767.98px) {
        .pos-price-filters ~ .aiz-pos-product-list { height: 60vh; max-height: 60vh; }
    }
</style>
@endonce
<div class="pos-price-filters row gutters-5 mb-3 align-items-end">
    <div class="col-6 col-lg-3 mb-2 mb-lg-0">
        <label for="pos-min-price" class="fs-12 mb-1">{{ __('pos.min_price') }} ({{ currency_symbol() }})</label>
        <input id="pos-min-price" name="min_price" type="number" min="0" step="0.01" inputmode="decimal"
               class="form-control" placeholder="0.00" onchange="filterProducts()"
               onkeydown="if(event.key === 'Enter'){event.preventDefault();this.blur();}">
    </div>
    <div class="col-6 col-lg-3 mb-2 mb-lg-0">
        <label for="pos-max-price" class="fs-12 mb-1">{{ __('pos.max_price') }} ({{ currency_symbol() }})</label>
        <input id="pos-max-price" name="max_price" type="number" min="0" step="0.01" inputmode="decimal"
               class="form-control" placeholder="{{ __('pos.no_limit') }}" onchange="filterProducts()"
               onkeydown="if(event.key === 'Enter'){event.preventDefault();this.blur();}">
    </div>
    <div class="col-8 col-lg-4">
        <label for="pos-price-sort" class="fs-12 mb-1">{{ __('pos.sort') }}</label>
        <select id="pos-price-sort" name="sort" class="form-control" onchange="filterProducts()">
            <option value="price_asc">{{ __('pos.price_low_to_high') }}</option>
            <option value="newest">{{ __('pos.newest') }}</option>
        </select>
    </div>
    <div class="col-4 col-lg-2">
        <button type="button" class="btn btn-soft-secondary btn-block px-1" onclick="resetPosPriceFilters()">{{ __('pos.clear_price') }}</button>
    </div>
    <div id="pos-price-error" class="col-12 text-danger fs-12 mt-1 d-none" role="alert"></div>
</div>
