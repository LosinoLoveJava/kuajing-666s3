@php($methods = \App\Services\WalletService::methods())
<form class="" action="{{ route('wallet_recharge.make_payment', ['portal' => 'customer']) }}" method="post" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="wallet_form" value="recharge">
    <div class="modal-body gry-bg px-3 pt-3 mx-auto">
        <div class="align-items-center gutters-5 row">
            @foreach($methods as $method)
              @if (stripos((string) $method->heading, 'usdt') !== false || stripos((string) $method->heading, 'tether') !== false)
                @continue
              @endif
              <div class="col-6 col-md-4">
                <label class="aiz-megabox d-block mb-3">
                    <input value="{{ $method->heading }}" type="radio" name="payment_option" onchange="toggleManualPaymentData({{ $method->id }})" data-id="{{ $method->id }}" @if($loop->first) checked @endif required>
                    <span class="d-block p-3 aiz-megabox-elem">
                        <img src="{{ uploaded_asset($method->photo) }}" class="img-fluid mb-2">
                        <span class="d-block text-center">
                            <span class="d-block fw-600 fs-15">{{ $method->heading }}</span>
                        </span>
                    </span>
                </label>
              </div>
            @endforeach
        </div>

        @if($methods->isEmpty())<div class="alert alert-warning">{{ translate('No bank transfer instructions are configured. Please contact support.') }}</div>@endif
        <div id="manual_payment_data">

            <div class="card mb-3 p-3 d-none">
                <div id="manual_payment_description">

                </div>
            </div>

            <div class="card mb-3 p-3">
                
                 <input type="hidden" name="type" value="{{$type}}" />
                
                <div class="row mt-3">
                    <div class="col-md-3">
                        <label>{{ translate('Amount')}} <span class="text-danger">*</span></label>
                    </div>
                    <div class="col-md-9">
                        <input type="number" lang="en" class="form-control mb-3" min="0.01" step="0.01" name="amount" placeholder="{{ translate('Amount') }}" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <label>{{ translate('Transaction ID')}} <span class="text-danger">*</span></label>
                    </div>
                    <div class="col-md-9">
                        <input type="text" class="form-control mb-3" name="trx_id" placeholder="{{ translate('Transaction ID') }}" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-3 col-form-label">{{ translate('Photo') }}</label>
                    <div class="col-md-9">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ translate('Choose image') }}</div>
                            <input type="hidden" name="photo" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group text-right">
                <button @if($methods->isEmpty()) disabled @endif type="submit" class="btn btn-sm btn-primary transition-3d-hover mr-1">{{translate('Confirm')}}</button>
            </div>
        </div>
    </div>
</form>

@foreach($methods as $method)
  @if (stripos((string) $method->heading, 'usdt') !== false || stripos((string) $method->heading, 'tether') !== false)
    @continue
  @endif
  <div id="manual_payment_info_{{ $method->id }}" class="d-none">
      <div>{!! $method->description !!}</div>
      @if ($method->bank_info != null)
          <ul>
              @foreach (json_decode($method->bank_info) as $key => $info)
                  <li>{{ translate('Bank Name') }} - {{ $info->bank_name }}, {{ translate('Account Name') }} - {{ $info->account_name }}, {{ translate('Account Number') }} - {{ $info->account_number}}, {{ translate('Routing Number') }} - {{ $info->routing_number }}</li>
              @endforeach
          </ul>
      @endif
  </div>
@endforeach

<script type="text/javascript">
    $(document).ready(function(){
        var form = document.querySelector('#offline_wallet_recharge_modal_body form');
        var previous = @json(old('wallet_form') === 'recharge' ? old() : []);
        Object.keys(previous).forEach(function (name) { if (form.elements[name] && name !== '_token') form.elements[name].value = previous[name]; });
        toggleManualPaymentData($('input[name=payment_option]:checked').data('id'));
    });

    function toggleManualPaymentData(id){
        $('#manual_payment_description').parent().removeClass('d-none');
        $('#manual_payment_description').html($('#manual_payment_info_'+id).html());
    }
</script>
