@extends('seller.layouts.app')

@section('panel_content')
    <div class="aiz-titlebar mt-2 mb-4">
      <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{ translate('Transaction Password') }}</h1>
            @if ($user->tpwd != "") 
            {{ translate('If you forget the transaction password, please contact customer service to retrieve the transaction password')}}
            @endif
        </div>
      </div>
    </div>
    <form action="{{ route('seller.transaction.update') }}" method="POST" enctype="multipart/form-data">
        <input name="_method" type="hidden" value="POST">
        @csrf
        
        @if ($user->tpwd != "") 
        <input name="type" type="hidden"  value="2">
        @else
        <input name="type" type="hidden"  value="1">
        @endif

        <!-- Basic Info-->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{ translate('Transaction Password')}}</h5>
            </div>
            <div class="card-body">
@if ($user->tpwd != "") 
                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="spwd">{{ translate('original password') }}</label>
                    <div class="col-md-10">
                        <input type="password" name="spwd" id="spwd" class="form-control"  placeholder="{{ translate('original password') }}" required>

                    </div>
                </div>
  @endif               
                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="password">{{ translate('Transaction Password') }}</label>
                    <div class="col-md-10">
                        <input type="password" name="password" id="password" class="form-control"  placeholder="{{ translate('Transaction Password') }}" required>

                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="confirm_password">{{ translate('Confirm Password') }}</label>
                    <div class="col-md-10">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="{{ translate('Confirm Password') }}" >
  
                    </div>
                </div>
         

         
        <div class="form-group text-right">
            <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
        </div>
            </div>
            
        </div>
</form>

@endsection



