<form class="form-horizontal" action="{{ route('commissions.pay_to_seller2') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="modal-header">
    	<h5 class="modal-title h6">{{translate('Pay to seller')}}</h5>
    	<button type="button" class="close" data-dismiss="modal">
    	</button>
    </div>
    <div class="modal-body">
@include('partials.iban_details')

      <table class="table table-striped table-bordered" >
          <tbody>
               
              @if( $seller_withdraw_request->t_type ==2 )
              
               <!------------------------------>
              <tr>
                    @if($user2->balance >= 0)
                        <td>{{ translate('Due to seller') }}</td>
                        <td>{{ single_price($user2->balance) }}</td>
                    @endif
                </tr>
                <tr>
                    @if($seller_withdraw_request->amount > $user2->balance )
                        <td>{{ translate('Requested Amount is ') }}</td>
                        <td>{{ single_price($seller_withdraw_request->amount) }}</td>
                    @endif
                </tr>
            
                @if ($user2->bank_payment_status == 1)
                    <tr>
                        <td>{{ translate('Bank Name') }}</td>
                        <td>{{ $user2->bank_name }}</td>
                    </tr>
                    <tr>
                        <td>{{ translate('Bank Account Name') }}</td>
                        <td>{{ $user2->bank_acc_name }}</td>
                    </tr>
                    <tr>
                        <td>{{ translate('Bank Account Number') }}</td>
                        <td>{{ $user2->bank_acc_no }}</td>
                    </tr>
                    <tr>
                        <td>{{ translate('Bank Routing Number') }}</td>
                        <td>{{ $user2->bank_routing_no }}</td>
                    </tr>
                    
                  
                    
                    
                @endif
                
                 @if ($user2->usdt_payment_status == 1)
                
                   <tr>
                        <td>{{ translate('USDT Link') }}</td>
                        <td>{{ $user2->usdt_type }}</td>
                    </tr>
                    
                    
                     <tr>
                        <td>{{ translate('USDT Address') }}</td>
                        <td>{{ $user2->usdt_address  }}</td>
                    </tr>
                    
                    
                @endif
                
                
                
                <!------------------------------>
              
              
              @else
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
                
                @if ($user->shop->usdt_payment_status == 1)
                <tr>
                        <td>{{ translate('USDT Link') }}</td>
                        <td>{{ $user->shop->usdt_type }}</td>
                    </tr>
                    
                    
                     <tr>
                        <td>{{ translate('USDT Address') }}</td>
                        <td>{{ $user->shop->usdt_address  }}</td>
                    </tr>
                    
                @endif
                @endif
            </tbody>
        </table>

        @if ($user->shop->admin_to_pay > 0 || true)
            <input type="hidden" name="shop_id" value="{{ $user->shop->id }}">
            <input type="hidden" name="payment_withdraw" value="withdraw_request">
            <input type="hidden" name="withdraw_request_id" value="{{ $seller_withdraw_request->id }}">
            <div class="form-group row">
                <label class="col-sm-3 col-from-label" for="amount">{{translate('Requested Amount')}}</label>
                <div class="col-sm-9">
                    @if ($seller_withdraw_request->amount > $user->shop->admin_to_pay && false)
                        <input type="number" lang="en" min="0" step="0.01" name="amount" id="amount" value="{{ $user->shop->admin_to_pay }}" class="form-control" required>
                    @else
                        <input type="number" lang="en" min="0" step="0.01" name="amount" id="amount" value="{{ $seller_withdraw_request->amount }}" class="form-control" required>
                    @endif
                </div>
            </div>

            <div class="form-group row">
                <label class="col-sm-3 col-from-label" for="payment_option">{{translate('Payment Method')}}</label>
                <div class="col-sm-9">
                    <select name="payment_option" id="payment_option" class="form-control demo-select2-placeholder" required>
                        <option value="">{{translate('Select Payment Method')}}</option>
@if($seller_withdraw_request->w_type == 4)<option value="iban_payment" selected>IBAN</option>@endif
                        @if($user->shop->cash_on_delivery_status == 1 || true)
                            <option value="cash">{{translate('Cash')}}</option>
                        @endif
                        @if($user->shop->bank_payment_status == 1 || true)
                            <option value="bank_payment">{{translate('Bank Payment')}}</option>
                        @endif
                        @if($user->shop->usdt_payment_status == 1 || true)
                            <option value="usdt_payment">{{translate('USDT Payment')}}</option>
                        @endif
                    </select>
                </div>
            </div>
                        <div class="form-group row">
                <label class="col-sm-3 col-from-label" for="payment_option">{{translate('Remarks')}}</label>
                <div class="col-sm-9">
                    <textarea cols=50 rows="10" class="form-control" name="remarks"></textarea>
                </div>
            </div>
        @endif

    </div>
    <div class="modal-footer">
      @if ($user->shop->admin_to_pay >= 0)
        <button type="submit" class="btn btn-primary">{{translate('Pay')}}</button>
      @endif
      <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
    </div>
    
     <input type="hidden" value="{{$seller_withdraw_request_id}}" name="seller_withdraw_request_id" />
</form>

<script>
$(document).ready(function(){
    $('#payment_option').on('change', function() {
      if ( this.value == 'bank_payment')
      {
        $("#txn_div").show();
      }
      else
      {
        $("#txn_div").hide();
      }
    });
    $("#txn_div").hide();
    AIZ.plugins.bootstrapSelect('refresh');
});
</script>
