@extends(auth()->user()->user_type === 'salesman' ? 'salesman.layouts.app' : 'backend.layouts.app')

@section(auth()->user()->user_type === 'salesman' ? 'panel_content' : 'content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{translate('Edit Seller Information')}}</h5>
</div>

<div class="col-lg-6 mx-auto">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{translate('Seller Information')}}</h5>
        </div>

        <div class="card-body">
          <form id="seller-edit" action="{{ route(auth()->user()->user_type === 'salesman' ? 'salesman.sellers.update' : 'sellers.update', $shop->id) }}" method="POST">
                <input name="_method" type="hidden" value="PATCH">
                @csrf
                <div class="form-group row">
                    <label class="col-sm-3 col-from-label" for="name">{{translate('Name')}}</label>
                    <div class="col-sm-9">
                        <input type="text" placeholder="{{translate('Name')}}" id="name" name="name" class="form-control" value="{{$shop->user->name}}" required>
                    </div>
                </div>
                @if($errors->any())
                    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <div class="form-group">
                    @include('frontend.partials.phone_registration_fields', ['phoneFormId' => 'seller-edit', 'phoneValue' => $shop->user->phone_normalized ?: $shop->user->phone])
                </div>
                <div class="form-group row">
                    <label class="col-sm-3 col-from-label" for="password">{{translate('Password')}}</label>
                    <div class="col-sm-9">
                        <input type="password" placeholder="{{translate('Password')}}" id="password" name="password" class="form-control">
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-sm-3 col-from-label" for="tpwd">{{translate('Transaction Password')}}</label>
                    <div class="col-sm-9">
                        <input type="password" placeholder="{{translate('Transaction Password')}}" id="tpwd" name="tpwd" class="form-control" />
                    </div>
                </div>
              <div class="form-group row" style="display:none;">
                  <label class="col-sm-3 col-from-label" for="password">{{translate('Views')}}</label>
                  <div class="col-sm-9">
                      <input type="number" placeholder="{{translate('Views')}}" value="{{$shop->views}}" id="views" name="views" class="form-control">
                  </div>
              </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-primary">{{translate('Save')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('script')
@include('frontend.partials.phone_auth_script', ['mode' => 'register', 'phoneFormId' => 'seller-edit'])
@endsection
