<form method="POST" action="{{ route('commissions.pay_to_seller2') }}">@csrf
    <div class="modal-header"><h5 class="modal-title">{{ translate('IBAN withdrawal') }}</h5><button type="button" class="close" data-dismiss="modal"></button></div>
    <div class="modal-body">
        <p>{{ translate('Requested Amount') }}: {{ single_price($seller_withdraw_request->amount) }}</p>
        @include('partials.iban_details')
        <input type="hidden" name="seller_withdraw_request_id" value="{{ $seller_withdraw_request->id }}">
        <input type="hidden" name="amount" value="{{ $seller_withdraw_request->amount }}">
        <input type="hidden" name="payment_option" value="iban_payment">
        <label class="d-block">{{ translate('Remarks') }}<textarea class="form-control" name="remarks" maxlength="2000"></textarea></label>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">{{ translate('Confirm payment completed') }}</button><button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button></div>
</form>
