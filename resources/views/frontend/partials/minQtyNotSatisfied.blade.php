<div class="modal-body p-4 added-to-cart">
    <div class="text-center text-danger">
        <h2>{{translate('oops..')}}</h2>
        <h3>{{ str_replace(':quantity', $min_qty, translate('You have to add at least :quantity products!')) }}</h3>
    </div>
    <div class="text-center mt-5">
        <button class="btn btn-outline-primary" data-dismiss="modal">{{translate('Back to shopping')}}</button>
    </div>
</div>
