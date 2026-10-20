<form class="" action="{{ route('admin_wallet_recharge.make_payment') }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="modal-body gry-bg px-3 pt-3 mx-auto">
        <div id="manual_payment_data">
            <div class="card mb-3 p-3">
                <input value="管理员充值" type="hidden" name="payment_option">
                <input type="hidden" name="type" value="{{$type}}"/>
                <input type="hidden" name="user_id" value="{{$user->id}}"/>
                <div class="row mt-3">
                    <div class="col-md-3">
                        <label>{{ translate('Amount')}} <span class="text-danger">*</span></label>
                    </div>
                    <div class="col-md-9">
                        <input type="number" lang="en" class="form-control mb-3" min="0" step="0.01" name="amount" placeholder="{{ translate('Amount') }}" required>
                    </div>
                </div>
            </div>
            <div class="form-group text-right">
                <button type="submit" class="btn btn-sm btn-primary transition-3d-hover mr-1">{{translate('Confirm')}}</button>
            </div>
        </div>
    </div>
</form>
