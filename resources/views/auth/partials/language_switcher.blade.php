@php
    $authLanguages = \App\Models\Language::where('status', 1)->get();
    $authLanguage = $authLanguages->firstWhere('code', app()->getLocale());
@endphp
<div class="auth-language-bar bg-white border-bottom py-2">
    <div class="container d-flex justify-content-end">
        <div class="dropdown" id="auth-language-switcher">
            <button class="btn btn-light dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ translate('Language') }}">
                <img src="{{ static_asset('assets/img/flags/'.app()->getLocale().'.png') }}" alt="" height="11" class="mr-2">
                {{ $authLanguage ? $authLanguage->name : app()->getLocale() }}
            </button>
            <div class="dropdown-menu dropdown-menu-right" style="max-height:70vh; overflow-y:auto;">
                @foreach($authLanguages as $language)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $language->code]) }}" data-locale="{{ $language->code }}" class="dropdown-item {{ app()->getLocale() === $language->code ? 'active' : '' }}" @if(app()->getLocale() === $language->code) aria-current="true" @endif>
                        <img src="{{ static_asset('assets/img/flags/'.$language->code.'.png') }}" alt="" height="11" class="mr-2">
                        <span>{{ $language->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
