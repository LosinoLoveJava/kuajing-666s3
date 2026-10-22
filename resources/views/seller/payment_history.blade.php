@extends('seller.layouts.app')

@section('panel_content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Payment History') }}</h5>
        </div>
            <div class="card-body">
                <div class="aiz-table-scroll" tabindex="0">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Date')}}</th>
                            <th>{{ translate('Amount')}}</th>
                            <th>{{ translate('Payment Method')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $key => $payment)
                            <tr>
                                <td>
                                    {{ $payments->firstItem() + $key }}
                                </td>
                                <td>{{ date('d-m-Y', strtotime($payment->created_at)) }}</td>
                                <td>
                                    {{ single_price($payment->amount) }}
                                </td>
                                <td>
                                    {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }} @if ($payment->txn_code != null) ({{  translate('TRX ID') }} : {{ $payment->txn_code }}) @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">{{ translate('Nothing found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                <div class="aiz-pagination">
                	{{ $payments->links() }}
              	</div>
            </div>
    </div>

@endsection
