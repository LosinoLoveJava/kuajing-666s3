<form method="POST" action="{{ route('commissions.refuse') }}">@csrf
    <div class="modal-header"><h5 class="modal-title">{{ translate('Reject withdrawal and return balance') }}</h5><button type="button" class="close" data-dismiss="modal"></button></div>
    <div class="modal-body">
        <p>{{ translate('Requested Amount') }}: {{ single_price($seller_withdraw_request->amount) }}</p>
        @include('partials.iban_details')
        <input type="hidden" name="withdraw_request_id" value="{{ $seller_withdraw_request->id }}">
        <label class="d-block">{{ translate('Remarks') }}<textarea class="form-control" name="remarks" maxlength="2000" required></textarea></label>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">{{ translate('Confirm') }}</button><button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button></div>
</form>
