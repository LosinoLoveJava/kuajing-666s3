@if((int) $approval === 1)
    <span class="badge badge-inline badge-success">{{ translate('Approved') }}</span>
@elseif((int) $approval === 2)
    <span class="badge badge-inline badge-danger">{{ translate('Rejected') }}</span>
@else
    <span class="badge badge-inline badge-info">{{ translate('Pending') }}</span>
@endif
