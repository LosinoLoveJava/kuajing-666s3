@extends('frontend.layouts.app')

@section('content')
<section class="pt-4 mb-4">
    <div class="container text-center">
        <div class="row">
            <div class="col-lg-6 text-center text-lg-left">
                <h1 class="fw-600 h4">{{ translate('Register your shop')}}</h1>
            </div>
            <div class="col-lg-6">
                <ul class="breadcrumb bg-transparent p-0 justify-content-center justify-content-lg-end">
                    <li class="breadcrumb-item opacity-50">
                        <a class="text-reset" href="{{ route('home') }}">{{ translate('Home')}}</a>
                    </li>
                    <li class="text-dark fw-600 breadcrumb-item">
                        <a class="text-reset" href="{{ route('shops.create') }}">"{{ translate('Register your shop')}}"</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
<section class="pt-4 mb-4">
    <div class="container">
        <div class="row">
            <div class="col-xxl-5 col-xl-6 col-md-8 mx-auto">
                <form id="shop" class="" action="{{ route('shops.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="mode" value="register">
                    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
                    @if (!Auth::check())
                        <div class="bg-white rounded shadow-sm mb-3">
                            <div class="fs-15 fw-600 p-3 border-bottom">
                                {{ translate('Personal Info')}}
                            </div>
                            <div class="p-3">
                                <div class="form-group">
                                    <label>{{ translate('Your Name')}}<span class="text-primary">*</span></label>
                                    <input type="text" class="form-control{{ $errors->has('account_name') ? ' is-invalid' : '' }}" value="{{ old('account_name') }}" placeholder="{{  translate('Name') }}" name="account_name" maxlength="191" autocomplete="name" required>
                                </div>
                                <div class="form-group">
                                    @include('frontend.partials.phone_registration_fields', ['phoneFormId' => 'shop'])
                                </div>
                                <div class="form-group" id="phone-code-fields">
                                    <label class="d-block">{{ translate('Verification code') }}<input class="form-control" name="code" inputmode="numeric" pattern="[0-9]{5,6}" minlength="5" maxlength="6" autocomplete="one-time-code" required></label>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="send-phone-code">{{ translate('Send code') }}</button>
                                    <p id="phone-feedback" role="status" class="small mt-2"></p>
                                </div>
                                <div class="form-group">
                                    <label>{{ translate('Your Password')}} <span class="text-primary">*</span></label>
                                    <input type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="{{  translate('Password') }}" name="password" minlength="8" maxlength="100" autocomplete="new-password" required>
                                </div>
                                <div class="form-group">
                                    <label>{{ translate('Repeat Password')}} <span class="text-primary">*</span></label>
                                    <input type="password" class="form-control" placeholder="{{  translate('Confirm Password') }}" name="password_confirmation" minlength="8" maxlength="100" autocomplete="new-password" required>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="bg-white rounded shadow-sm mb-4">
                        <div class="fs-15 fw-600 p-3 border-bottom">
                            {{ translate('Basic Info')}}
                        </div>
                        <div class="p-3">
                            <div class="form-group">
                                <label>{{ translate('Shop Name')}} <span class="text-primary">*</span></label>
                                <input type="text" class="form-control" placeholder="{{ translate('Shop Name')}}" name="name" value="{{ old('name') }}" maxlength="191" required>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Address')}} <span class="text-primary">*</span></label>
                                <input type="text" class="form-control mb-3" placeholder="{{ translate('Address')}}" name="address" value="{{ old('address') }}" maxlength="500" required>
                            </div>
                            
                             <div class="form-group">
                                <label>{{ translate('Certificates Type')}} <span class="text-primary">*</span></label>
                                 <select class="form-control" name="certtype">
                                     <option value="idcard"> {{translate('id card')}}</option>
                                     <option value="passport"> {{translate('passport')}}</option>
                                     <option value="driving license"> {{translate('driving license')}}</option>
                                     <option value="social security card"> {{translate('Social Security Card')}}</option>
                                 </select>
                                 
                                
                                </div>
                            <div class="form-group">
                                <label>{{ translate('Certificates Front')}} <span class="text-primary">*</span></label>
                                <div class="input-group" data-toggle="aizuploader" data-type="image">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                    </div>
                                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                    <input type="hidden" name="identity_card_front" value="{{ old('identity_card_front') }}" class="selected-files" required>
                                </div>
                                <div class="file-preview box sm">
                                </div>
                            </div>
                            
                            
                            <div class="form-group">
                                <label>{{ translate('Certificates Back')}}</label>
                             
                                <div class="input-group" data-toggle="aizuploader" data-type="image">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                    </div>
                                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                    <input type="hidden" name="identity_card_back" value="{{ old('identity_card_back') }}" class="selected-files">
                                </div>
                                <div class="file-preview box sm">
                                </div>
                            </div>

                        </div>
                    </div>

                    @if(get_setting('google_recaptcha') == 1)
                        <div class="form-group mt-2 mx-auto row">
                            <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_KEY') }}"></div>
                        </div>
                    @endif

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary fw-600">{{ translate('Register Your Shop')}}</button>
                    </div>
                    <div class="mt-3 text-center"><a href="{{ route('phone.access') }}">{{ translate('Phone login') }}</a></div>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection

@section('script')
@include('frontend.partials.seller_document_validation')
@include('frontend.partials.phone_auth_script', ['mode' => 'register', 'phoneFormId' => 'shop'])
@if(get_setting('google_recaptcha') == 1)
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script type="text/javascript">
    // making the CAPTCHA  a required field for form submission
    $(document).ready(function(){
        // alert('helloman');
        $("#shop").on("submit", function(evt)
        {
            var response = grecaptcha.getResponse();
            if(response.length == 0)
            {
            //reCaptcha not verified
                alert("please verify you are humann!");
                evt.preventDefault();
                return false;
            }
            //captcha verified
            //do the rest of your validations here
        });
    });
</script>
@endif
@endsection
