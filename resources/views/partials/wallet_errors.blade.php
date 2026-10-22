@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<div class="alert alert-info">{{ translate('Recharges are credited after approval. Withdrawal amounts are reserved immediately and returned if rejected.') }}</div>
