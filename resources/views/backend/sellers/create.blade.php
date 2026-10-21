@extends(auth()->user()->user_type === 'salesman' ? 'salesman.layouts.app' : 'backend.layouts.app')
@section(auth()->user()->user_type === 'salesman' ? 'panel_content' : 'content')
@include('backend.sellers.phone_form', ['sellerStoreRoute' => 'sellers.store'])
@endsection
@section('script')
@include('frontend.partials.seller_document_validation')
@include('frontend.partials.phone_auth_script', ['mode' => 'register', 'phoneFormId' => 'shop'])
@endsection
