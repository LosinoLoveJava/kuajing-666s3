@extends('frontend.layouts.app')

@section('content')
<section class="gry-bg py-5">
    <div class="container"><div class="row"><div class="col-lg-5 mx-auto">
        <div class="card"><div class="card-body">
            @include('frontend.partials.phone_login_prompt')
            <a class="d-block mt-3" href="{{ route('phone.access', ['mode' => 'register']) }}">{{ translate('Register by phone') }}</a>
        </div></div>
    </div></div></div>
</section>
@endsection
