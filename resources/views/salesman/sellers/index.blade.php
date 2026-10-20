@extends('salesman.layouts.app')

@section('panel_content')
<div class="aiz-titlebar mt-2 mb-4">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{translate('All Sellers')}}</h1>
        </div>
    </div>
    <div class="col text-right">
        <a href="{{ route('shops.create') }}" class="btn btn-circle btn-info">
            <span>{{translate('Add Virtual Seller')}}</span>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header row gutters-5">
        <div class="col">
            <h5 class="mb-md-0 h6">{{ translate('Sellers') }}</h5>
        </div>
        <div class="col-md-4">
            <form class="" id="sort_brands" action="" method="GET">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="search" name="search" @isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type name or email & Enter') }}">
                </div>
            </form>
        </div>
    </div>


    <div class="card-body">
        <table class="table aiz-table mb-0">
            <thead>
            <tr>
                <th>{{translate('Name')}}</th>
                <th data-breakpoints="lg">{{translate('Phone')}}</th>
                <th data-breakpoints="lg">{{translate('Email Address')}}</th>
                <th data-breakpoints="lg">{{translate('Verification Info')}}</th>
                <th data-breakpoints="lg">{{translate('Approval')}}</th>
                <th data-breakpoints="lg">{{ translate('Num. of Products') }}</th>
                <th data-breakpoints="lg">{{ translate('Pending Balance') }}</th>
                <th data-breakpoints="lg">{{ translate('Wallet Money') }}</th>
                <th data-breakpoints="lg">{{ translate('Views') }}</th>
                <th width="10%">{{translate('Options')}}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($shops as $key => $shop)
                <tr>
                    <td>@if($shop->user->banned == 1) <i class="fa fa-ban text-danger" aria-hidden="true"></i> @endif {{$shop->name}}@if($shop->user->is_virtual == 1) (<font color="red">{{translate('Virtual')}}</font>) @endif</td>
                    <td>{{$shop->user->phone}}</td>
                    <td>{{$shop->user->email}}</td>
                    <td>
                        @if ($shop->verification_info != null)
                            <a href="{{ route('sellers.show_verification_request', $shop->id) }}">
                                <span class="badge badge-inline badge-info">{{translate('Show')}}</span>
                            </a>
                        @endif
                    </td>
                    <td>
                        <label class="aiz-switch aiz-switch-success mb-0">
                            @if (get_setting('salesman_reviews_switch') == 1)
                                <input onchange="update_approved(this)" value="{{ $shop->id }}" type="checkbox" <?php if($shop->verification_status == 1) echo "checked";?> >
                            @else
                            <input disabled readonly value="{{ $shop->id }}" type="checkbox" <?php if($shop->verification_status == 1) echo "checked";?> >
                            @endif
                            <span class="slider round"></span>
                        </label>
                    </td>
                    <td>{{ $shop->user->products->count() }}</td>
                    <td>
                        @if ($shop->admin_to_pay >= 0)
                            {{ single_price($shop->admin_to_pay) }}
                        @else
                            {{ single_price(abs($shop->admin_to_pay)) }} ({{ translate('Due to Admin') }})
                        @endif
                    </td>
                    <td  >
                        {{single_price($shop->user->balance)}}
                    </td>
                    
                      <td  >
                          {{translate('base num')}}：{{$shop->view_base_num}}
                            <br> 
                           {{translate('inc num')}}：{{$shop->view_inc_num}}
                        </td>
                        
                        
                    <td>
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-circle btn-soft-primary btn-icon dropdown-toggle no-arrow" data-toggle="dropdown" href="javascript:void(0);" role="button" aria-haspopup="false" aria-expanded="false">
                                <i class="las la-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right dropdown-menu-xs">
                                <a href="#" onclick="show_seller_profile('{{$shop->id}}');"  class="dropdown-item">
                                    {{translate('Profile')}}
                                </a>

                                <span onclick="show_chat_modal({{$shop->user->id}})" class="dropdown-item" style="cursor:pointer;">
                                        {{translate('Message Seller')}}
                                    </span>
                                    
                                    <span onclick="show_view({{$shop->id}},{{$shop->view_inc_num}},{{$shop->view_base_num}})" class="dropdown-item" style="cursor:pointer;">
                                        {{translate('Views')}}
                                    </span>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="aiz-pagination">
            {{ $shops->appends(request()->input())->links() }}
        </div>
    </div>
</div>

@endsection

@section('modal')
	<!-- Seller Profile Modal -->
	<div class="modal fade" id="profile_modal">
		<div class="modal-dialog">
			<div class="modal-content" id="profile-modal-content">

			</div>
		</div>
	</div>
    <div class="modal fade" id="chat_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom product-modal" id="modal-size" role="document">
            <div class="modal-content position-relative">
                <div class="modal-header">
                    <h5 class="modal-title fw-600 h5">{{ translate('Any query about this seller') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('conversations.salesman_store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="receiver_id" id="receiver_id" value="">
                    <div class="modal-body gry-bg px-3 pt-3">
                        {{--<div class="form-group">
                            <input type="text" class="form-control mb-3" name="title"
                                value="" placeholder="{{ translate('Title') }}"
                                required>
                        </div>--}}
                        <div class="form-group">
                            <textarea class="form-control" rows="8" name="title" required
                                placeholder="{{ translate('Title') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary fw-600"
                            data-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary fw-600">{{ translate('Send') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script src="https://cdn.bootcdn.net/ajax/libs/layer/3.5.1/layer.min.js"></script>
    <script type="text/javascript">
    
    function show_view(shop_id,view_inc_num,view_base_num) {
         
          
          var content = ' <div class="row" style="width: 420px;  margin-left:7px; margin-top:10px;">'
            +'<div class="col-sm-12">'
            +'<div class="input-group">'
            +'<span class="input-group-addon"> 基础访问量：</span>'
            +'<input id="base_num" type="text" value="'+view_base_num+'" class="form-control" placeholder="基础访问量">'
            +'</div>'
            +'</div>'
             
               +'<div class="col-sm-12" style="margin-top:3px;">'
            +'<div class="input-group">'
            +'<span class="input-group-addon"> 每日递增量：</span>'
            +'<input id="inc_num" type="text" value="'+view_inc_num+'" class="form-control" placeholder="每日递增">'
            +'</div>'
            +'</div>'
           
              +'</div>';  
            
            layer.open({
      
        type: 1,
        title:'访问量',
        skin:'layui-layer-rim',
        area:['450px', 'auto'],
        
        content: content,
        btn:['保存','取消'],
        btn1: function (index,layero) {
            var inc_num = $("#inc_num").val();
            var base_num = $("#base_num").val();
            
             $.post('{{ route('sellers.setviews') }}',{_token:'{{ @csrf_token() }}', shop_id:shop_id,inc_num:inc_num,base_num:base_num}, function(data){
                layer.msg(data.msg,function(){
                    location.reload();
                });
                },'json');
            
        },
        btn2:function (index,layero) {
             layer.close(index);
        }
 
    });
    } 
    
    
    
        function show_seller_profile(id){
            $.post('{{ route('salesman.sellers_profile_modal') }}',{_token:'{{ @csrf_token() }}', id:id}, function(data){
                $('#profile_modal #profile-modal-content').html(data);
                $('#profile_modal').modal('show', {backdrop: 'static'});
            });
        }
        function sort_sellers(el){
            $('#sort_sellers').submit();
        }

        function show_chat_modal(receiver_id) {
            $('#receiver_id').val(receiver_id);
            $('#chat_modal').modal('show');
        }

        function update_approved(el){
            if(el.checked){
                var status = 1;
            }
            else{
                var status = 0;
            }
            $.post('{{ route('salesman.sellers.approved') }}', {_token:'{{ csrf_token() }}', id:el.value, status:status}, function(data){
                if(data == 1){
                    AIZ.plugins.notify('success', '{{ translate('Approved sellers updated successfully') }}');
                }
                else{
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }

    </script>
@endsection
