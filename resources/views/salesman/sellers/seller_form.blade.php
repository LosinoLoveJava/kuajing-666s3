@extends('salesman.layouts.app')
@section('panel_content')
@include('backend.sellers.phone_form', ['sellerStoreRoute' => 'shops.store'])
@endsection
@section('script')
@include('frontend.partials.seller_document_validation')
@include('frontend.partials.phone_auth_script', ['mode' => 'register', 'phoneFormId' => 'shop'])
@endsection
