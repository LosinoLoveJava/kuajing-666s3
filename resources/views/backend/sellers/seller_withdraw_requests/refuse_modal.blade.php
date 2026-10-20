<form class="form-horizontal" action="{{ route('commissions.refuse') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="modal-header">
    	<h5 class="modal-title h6">{{translate('Refuse')}}</h5>
    	<button type="button" class="close" data-dismiss="modal">
    	</button>
    </div>
    <input type="hidden" value="{{$id}}" name="withdraw_request_id"  />
    <div class="modal-body">
@include('partials.iban_details')

      <table class="table table-striped table-bordered" >
          <tbody>
                <tr>
                    @if($user->shop->admin_to_pay >= 0)
                        <td>{{ translate('Due to seller') }}</td>
                        <td>{{ single_price($user->shop->admin_to_pay) }}</td>
                    @endif
                </tr>
                <tr>
                    @if($seller_withdraw_request->amount > $user->shop->admin_to_pay)
                        <td>{{ translate('Requested Amount is ') }}</td>
                        <td>{{ single_price($seller_withdraw_request->amount) }}</td>
                    @endif
                </tr>
                @if ($user->shop->bank_payment_status == 1)
                    <tr>
                        <td>{{ translate('Bank Name') }}</td>
                        <td>{{ $user->shop->bank_name }}</td>
                    </tr>
                    <tr>
                        <td>{{ translate('Bank Account Name') }}</td>
                        <td>{{ $user->shop->bank_acc_name }}</td>
                    </tr>
                    <tr>
                        <td>{{ translate('Bank Account Number') }}</td>
                        <td>{{ $user->shop->bank_acc_no }}</td>
                    </tr>
                    <tr>
                        <td>{{ translate('Bank Routing Number') }}</td>
                        <td>{{ $user->shop->bank_routing_no }}</td>
                    </tr>
                 
                @endif
                
                   <tr>
                       <td colspan="2">
                           <div class="form-group row">
                <label class="col-sm-3 col-from-label"  for="payment_option">{{translate('Remarks')}}</label>
                <div class="col-sm-9">
                    <textarea cols=50 rows="10" class="form-control" name="remarks"></textarea>
                </div>
            </div></td>
            </tr>
            
            </tbody>
        </table>

    </div>
    <div class="modal-footer">
      <button type="submit" class="btn btn-primary">{{translate('Confirm')}}</button>
      <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
    </div>
</form>
