<section class="pt-4 mb-4">
    <div class="container text-center">
        <div class="row">
        </div>
    </div>
</section>
<section class="pt-4 mb-4">
    <div class="container">
        <div class="row">
            <div class="col-xxl-5 col-xl-6 col-md-8 mx-auto">
                <form id="shop" class="" action="{{ route($sellerStoreRoute ?? 'shops.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @if($errors->any())
                        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <div class="bg-white rounded shadow-sm mb-3">
                        <div class="fs-15 fw-600 p-3 border-bottom">
                            {{ translate('Personal Info')}}
                        </div>
                        <div class="p-3">
                            <div class="form-group">
                                <label>{{ translate('Name')}} <span class="text-primary">*</span></label>
                                <input type="text" class="form-control{{ $errors->has('account_name') ? ' is-invalid' : '' }}" value="{{ old('account_name') }}" placeholder="{{  translate('Name') }}" name="account_name" required>
                            </div>
                            <div class="form-group">
                                @include('frontend.partials.phone_registration_fields', ['phoneFormId' => 'shop'])
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Password')}} <span class="text-primary">*</span></label>
                                <input type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="{{  translate('Password') }}" name="password" minlength="8" maxlength="100" required>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Repeat Password')}} <span class="text-primary">*</span></label>
                                <input type="password" class="form-control" placeholder="{{  translate('Confirm Password') }}" name="password_confirmation" minlength="8" maxlength="100" required>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded shadow-sm mb-4">
                        <div class="fs-15 fw-600 p-3 border-bottom">
                            {{ translate('Basic Info')}}
                        </div>
                        <div class="p-3">
                            <div class="form-group">
                                <label>{{ translate('Shop Name')}} <span class="text-primary">*</span></label>
                                <input type="text" class="form-control" placeholder="{{ translate('Shop Name')}}" name="name" value="{{ old('name') }}" required>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Address')}} <span class="text-primary">*</span></label>
                                <input type="text" class="form-control mb-3" placeholder="{{ translate('Address')}}" name="address" value="{{ old('address') }}" required>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Identity Card Front')}} <span class="text-primary">*</span></label>
                                <div class="input-group" data-toggle="aizuploader" data-type="image">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                    </div>
                                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                    <input type="hidden" name="identity_card_front" value="" class="selected-files" required>
                                </div>
                                <div class="file-preview box sm">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Identity Card Back')}}</label>
                                <div class="input-group" data-toggle="aizuploader" data-type="image">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                    </div>
                                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                    <input type="hidden" name="identity_card_back" value="" class="selected-files">
                                </div>
                                <div class="file-preview box sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary fw-600">{{ translate('Add New Seller')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

