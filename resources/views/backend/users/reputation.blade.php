@extends('backend.layouts.app')
@section('content')
<h1 class="h4">{{ translate('Reputation scores') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card p-3">
    <form method="GET" class="mb-3"><label>{{ translate('Search') }} <input name="q" class="form-control" value="{{ request('q') }}" placeholder="{{ translate('Name, email or phone') }}"></label> <button class="btn btn-primary">{{ translate('Search') }}</button></form>
    <div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('ID') }}</th><th>{{ translate('User') }}</th><th>{{ translate('Score') }}</th><th>{{ translate('Adjustment') }}</th></tr></thead><tbody>
    @foreach($users as $user)
    <tr><td>{{ $user->id }}</td><td>{{ $user->name }}<br>{{ $user->email ?: $user->phone }}<br>{{ translate(ucfirst($user->user_type)) }}</td><td>{{ $user->reputation_score }}/100</td><td>
        <form method="POST" action="{{ route('reputation.update', $user->id) }}">@csrf
            <label>{{ translate('New score') }}<input class="form-control" name="score" type="number" min="0" max="100" value="{{ $user->reputation_score }}" required></label>
            <label>{{ translate('Reason') }}<input class="form-control" name="reason" maxlength="500" required></label>
            <button class="btn btn-primary btn-sm">{{ translate('Save') }}</button>
        </form>
    </td></tr>
    @endforeach
    </tbody></table></div>{{ $users->links() }}
</div>
<div class="card p-3"><h2 class="h5">{{ translate('Adjustment history') }}</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('User') }}</th><th>{{ translate('Operator') }}</th><th>{{ translate('Score') }}</th><th>{{ translate('Reason') }}</th><th>{{ translate('Time') }}</th></tr></thead><tbody>
    @foreach($logs as $log)<tr><td>{{ $log->user_name }} (#{{ $log->user_id }})</td><td>{{ $log->operator_name }} (#{{ $log->operator_id }})</td><td>{{ $log->old_score }} → {{ $log->new_score }}</td><td>{{ $log->reason }}</td><td>{{ $log->created_at }}</td></tr>@endforeach
    </tbody></table></div>{{ $logs->links() }}
</div>
@endsection
