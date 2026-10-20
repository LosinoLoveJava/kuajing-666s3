<form action="{{ route('sellers.setbzj') }}" id="app" method="POST">
    @csrf
    <input type="hidden" name="shop_id" value="{{ $shop->id }}">
    <div class="modal-header">
    	<h5 class="modal-title h6">{{translate('Guarantee Money')}}</h5>
    	<button type="button" class="close" data-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="form-group row">
            <label class="col-md-3 col-from-label" for="amount">强制交纳保证金额度</label>
            <div class="col-md-9">
                <input type="number" min="0" step="0.01" value="{{$shop->compulsory_margin_amount}}" name="compulsory_margin_amount" id="compulsory_margin_amount" class="form-control" required>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-md-3 col-from-label" for="bzj">{{translate('Money')}}</label>
            <div class="col-md-9">
                <input type="number" value="{{$shop->bzj_money}}" min="0" step="0.01" name="bzj" id="bzj" class="form-control" required>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-md-3 col-from-label" for="mandatory_payment_switch">是否强制缴纳</label>
            <div class="col-md-9">
{{--                <input type="checkbox" name="mandatory_payment_switch" id="mandatory_payment_switch" value="{{$shop->mandatory_payment_switch}}">--}}
                <div class="aiz-checkbox-inline">
                    <label class="aiz-checkbox" style="display: flex">
                        <input type="checkbox" onclick="changeA()" @if($shop->mandatory_payment_switch)checked @endif value="{{$shop->mandatory_payment_switch}}">
                        <span class="aiz-square-check"></span>
                    </label>
                </div>

                <input type="hidden" name="mandatory_payment_switch" id="mandatory_payment_switch" value="{{$shop->mandatory_payment_switch}}">
{{--                <el-switch--}}
{{--                    name="mandatory_payment_switch"--}}
{{--                    v-model="value"--}}
{{--                    active-color="#13ce66">--}}
{{--                </el-switch>--}}
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary">{{translate('Submit')}}</button>
        <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
    </div>
</form>

<script>
    $(document).ready(function(){
        $('#app').bind('submit', function (e) {
        });

    });
    function changeA(){
        var v = $("#mandatory_payment_switch").val()
        console.log(v)
        if(v==1){
            $("#mandatory_payment_switch").val('0')
            /*$('#mandatory_payment_switch').each(function() {
                this.checked = false;
            });*/
            // $("#mandatory_payment_switch").checked=false
        }else{
            $("#mandatory_payment_switch").val('1')
            /*$('#mandatory_payment_switch').each(function() {
                this.checked = true;
            });*/
            // $("#mandatory_payment_switch").checked=true
        }
    }
</script>
