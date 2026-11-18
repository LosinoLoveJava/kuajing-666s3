@foreach (['reject' => ['danger', 'Reject'], 'approve' => ['success', 'Accept']] as $action => $button)
    <form action="{{ route('sellers.'.$action, $shop->id) }}" method="POST" class="d-inline-block">
        @csrf
        <input type="hidden" name="version" value="{{ (int) $shop->approval_version }}">
        <button type="submit" class="btn btn-circle btn-{{ $button[0] }}">{{ translate($button[1]) }}</button>
    </form>
@endforeach
