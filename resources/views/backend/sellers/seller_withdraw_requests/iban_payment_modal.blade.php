<form method="POST" action="{{ route('commissions.pay_to_seller2') }}">@csrf
    <div class="modal-header"><h5 class="modal-title">{{ translate('Confirm withdrawal payment') }}</h5><button type="button" class="close" data-dismiss="modal"></button></div>
    <div class="modal-body">
        <p>{{ translate('Requested Amount') }}: {{ single_price($seller_withdraw_request->amount) }}</p>
        @include('partials.iban_details')
        <input type="hidden" name="seller_withdraw_request_id" value="{{ $seller_withdraw_request->id }}">
        <div class="alert alert-info">{{ translate('Confirm only after sending the funds to the recipient. This action records a completed payment.') }}</div>
        <label class="d-block">{{ translate('Transaction ID') }}<input class="form-control" name="txn_code" maxlength="200" required></label>
        <label class="d-block">{{ translate('Remarks') }}<textarea class="form-control" name="remarks" maxlength="2000"></textarea></label>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">{{ translate('Confirm payment completed') }}</button><button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button></div>
</form>
