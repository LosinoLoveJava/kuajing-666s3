@extends('salesman.layouts.app')

@section('panel_content')

    <div class="row">
        <div style="width: 100%;">
            <div class="card shadow-none mb-4 bg-white py-4" style="width: 100%;">
                <div class="card-body"  style="width: 100%;">
                    <div class="row align-items-center"  style="width: 100%;">
                        <div class="col" style="width: 100%;">
                            <p class="small text-muted mb-0" style="width: 100%;">
                                <span class="fe fe-arrow-down fe-12"></span>
                                <span class="fs-16 text-info">{{ translate('Invitation link') }}</span>
                            </p>
                            <h3 class="mt-2 text-primary fs-30" style="width: 100%;">
                                {{ $url }}
                            </h3>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
