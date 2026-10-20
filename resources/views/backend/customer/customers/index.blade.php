@extends('backend.layouts.app')

@section('content')

    <div class="aiz-titlebar text-left mt-2 mb-3 row align-items-center">
        <div class="align-items-center">
            <h1 class="h3">{{translate('All Customers')}}</h1>
        </div>
    <!--  <div class="col text-right">
        <a href="{{ route('customers.create') }}" class="btn btn-circle btn-info">
            <span>{{translate('Add Virtual Account')}}</span>
        </a>
    </div> -->

        <div class="ml-auto" style="margin-right: 6px;">
            <button id="create_virtual_user" type="button" class="btn btn-outline-primary btn-block" onclick="">{{translate('Create Virtual Customers')}}</button>
        </div>


    </div>


<div class="card">
    <form class="" id="sort_customers" action="" method="GET">
        <div class="card-header row gutters-5">
            <div class="col">
                <h5 class="mb-0 h6">{{translate('Customers')}}</h5>
            </div>

            <div class="dropdown mb-2 mb-md-0">
                <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                    {{translate('Bulk Action')}}
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" onclick="bulk_delete()">{{translate('Delete selection')}}</a>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="search" name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type email or name & Enter') }}">
                </div>
            </div>
        </div>

        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <!--<th data-breakpoints="lg">#</th>-->
                        <th>
                            <div class="form-group">
                                <div class="aiz-checkbox-inline">
                                    <label class="aiz-checkbox">
                                        <input type="checkbox" class="check-all">
                                        <span class="aiz-square-check"></span>
                                    </label>
                                </div>
                            </div>
                        </th>
                        <th>{{translate('Name')}}</th>
                        <th data-breakpoints="lg">{{translate('Email Address')}}</th>
                        <th data-breakpoints="lg">{{translate('Phone')}}</th>
                        <th data-breakpoints="lg">{{translate('Package')}}</th>
                        <th data-breakpoints="lg">{{translate('Wallet Balance')}}</th>
                        <th>{{translate('Options')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $key => $user)
                        @if ($user != null)
                            <tr>
                                <!--<td>{{ ($key+1) + ($users->currentPage() - 1)*$users->perPage() }}</td>-->
                                <td>
                                    <div class="form-group">
                                        <div class="aiz-checkbox-inline">
                                            <label class="aiz-checkbox">
                                                <input type="checkbox" class="check-one" name="id[]" value="{{$user->id}}">
                                                <span class="aiz-square-check"></span>
                                            </label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($user->is_virtual_user == 1)<span class='badge badge-inline badge-warning' style="margin-right: 4px; ">{{translate('Virtual')}}</span>@endif

                                    @if($user->banned == 1) <i class="fa fa-ban text-danger" aria-hidden="true"></i> @endif {{$user->name}} @if($user->is_virtual_user == 1) (<font color="red">{{translate('Virtual')}}</font>) @endif</td>
                                <td>{{$user->email}}</td>
                                <td>{{$user->phone}}</td>
                                <td>
                                    @if ($user->customer_package != null)
                                    {{$user->customer_package->getTranslation('name')}}
                                    @endif
                                </td>
                                <td>{{single_price($user->balance)}}</td>
                                <td class="text-right">
                                    @if(Auth::user()->user_type == 'admin')
                                        <a href="#" class="btn btn-soft-success btn-icon btn-circle btn-sm" style="display: inline-flex;width: auto" onclick="show_make_wallet_recharge_modal('{{$user->id}}');" title="{{ translate('Ban this Customer') }}">
                                            {{ translate('Recharge') }}
                                        </a>
                                    @endif

                                    <a href="{{route('customers.login', encrypt($user->id))}}" class="btn btn-soft-primary btn-icon btn-circle btn-sm" title="{{ translate('Log in as this Customer') }}">
                                        <i class="las la-edit"></i> </a>


                                    @if(Auth::user()->user_type == 'salesman')
                                    <a href="{{route('customers.login', encrypt($user->id))}}" class="btn btn-soft-primary btn-icon btn-circle btn-sm" title="{{ translate('Log in as this Customer') }}">
                                        <i class="las la-edit"></i>
                                    </a>
                                    @endif
                                    @if($user->banned != 1)
                                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm" onclick="confirm_ban('{{route('customers.ban', encrypt($user->id))}}');" title="{{ translate('Ban this Customer') }}">
                                        <i class="las la-user-slash"></i>
                                    </a>
                                    @else
                                    <a href="#" class="btn btn-soft-success btn-icon btn-circle btn-sm" onclick="confirm_unban('{{route('customers.ban', encrypt($user->id))}}');" title="{{ translate('Unban this Customer') }}">
                                        <i class="las la-user-check"></i>
                                    </a>
                                    @endif
                                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('customers.destroy', $user->id)}}" title="{{ translate('Delete') }}">
                                        <i class="las la-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $users->appends(request()->input())->links() }}
            </div>
        </div>
    </form>
</div>


<div class="modal fade" id="confirm-ban">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title h6">{{translate('Confirmation')}}</h5>
                <button type="button" class="close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>{{translate('Do you really want to ban this Customer?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
                <a type="button" id="confirmation" class="btn btn-primary">{{translate('Proceed!')}}</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirm-unban">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title h6">{{translate('Confirmation')}}</h5>
                <button type="button" class="close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>{{translate('Do you really want to unban this Customer?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
                <a type="button" id="confirmationunban" class="btn btn-primary">{{translate('Proceed!')}}</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('modal')
    @include('modals.delete_modal')
    <div class="modal fade" id="virtual_user_form" data-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title h6">{{translate('Create Virtual Customers')}}</h5>
                <button type="button" class="close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 16px; font-size: 14px; ">
                    <i>{{translate('N:B: You can create virtual customers here, with a maximum of 100 people')}}</i>
                </div>
                <form class="form-horizontal" action="{{ route('customers.create_virtual_user') }}" method="POST">
                    <!--<div class="form-group row">
                        <div class="col-lg-2">{{translate('Name Prefix')}}</div>
                        <div class="col-lg-6">
                           <input type="text" class="form-control" name="name_prefix" value="" placeholder="Prefix of Name" required>
                        </div>
                        <div class="col-lg-4"><span>{{translate('Optional')}}</span></div>
                    </div>-->

                    <div class="form-group row" style="display:none;">
                        <div class="col-lg-2">{{translate('Referrel User')}}</div>
                        <div class="col-lg-6">
                           <input type="number" class="form-control" name="referred_by" value="" placeholder="Referrel User" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-2">{{translate('Quantity')}}</div>
                        <div class="col-lg-6">
                           <input type="number" min="1" step="1" max="100" class="form-control" name="quantity" value="1" placeholder="Quantity of generate" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-2">{{translate('Initial Balance')}}</div>
                        <div class="col-lg-6">
                           <input type="number" min="0" class="form-control" name="balance" value="0.00" placeholder="Initial Balance of Accounts" required>
                        </div>
                    </div>
                     <div class="form-group row">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-6">
                            <div style="display: flex; align-items: center;">

                            <div class="aiz-checkbox-inline" style="display: inline-block;">
                                <label class="aiz-checkbox">
                                    <input type="checkbox" class="check-one" name="disable_login" value="1">
                                    <span class="aiz-square-check"></span>
                                </label>
                            </div>
                            <span style="margin-top: 14px;">{{translate('Disable Log in')}}</span>


                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
                <a type="button" id="submitVirtualCustomer" class="btn btn-primary">{{translate('Submit')}}</a>
            </div>
        </div>
    </div>
</div>

    <!-- offline payment Modal -->
    <div class="modal fade" id="offline_wallet_recharge_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ translate('Recharge') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="offline_wallet_recharge_modal_body"></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script type="text/javascript">

        function show_make_wallet_recharge_modal(user_id){
            $.post('{{ route('admin.admin_wallet_recharge_modal') }}', {_token:'{{ csrf_token() }}', user_id: user_id}, function(data){
                $('#offline_wallet_recharge_modal_body').html(data);
                $('#offline_wallet_recharge_modal').modal('show');
            });
        }

        $( function ()
        {
            $( '#create_virtual_user' ).bind( 'click', function ()
            {
                $( '#virtual_user_form' ).modal( 'show' )
            } )
            $( '#submitVirtualCustomer' ).bind( 'click', function ()
            {
                let target = $( this )
                if ( target.hasClass( 'disabled' ) ) return false
                target.addClass( 'disabled' );
                let max = $( 'input[name=quantity]' ).val()
                let balance = $( 'input[name=balance]' ).val()
                let referred_by = $( 'input[name=referred_by]' ).val()
                fetch( '{{route('customers.create_virtual_user')}}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'fetch'
                    },
                    body: JSON.stringify( {
                        _token: '{{csrf_token()}}',
                        max,
                        balance,
                        referred_by
                    } )
                } )
                    .then( resp => resp.text() )
                    .then( res =>
                    {
                        if ( res == 1 ) {
                            AIZ.plugins.notify( 'success', '{{translate('Successfully created virtual customer')}}' )
                            setTimeout( () =>
                            {
                                window.location.reload()
                            }, 500 )
                        }
                        else {
                            AIZ.plugins.notify( 'danger', '{{translate('Executed failure Try again')}}' )
                            target.removeClass( 'disabled' )
                        }
                    } )
                    .catch( err => null ).finally( () =>
                {
                    //target.removeClass('disabled')
                } )
            } )
        } )


        $( document ).on( "change", ".check-all", function ()
        {
            if ( this.checked ) {
                // Iterate each checkbox
                $( '.check-one:checkbox' ).each( function ()
                {
                    this.checked = true;
                } );
            }
            else {
                $( '.check-one:checkbox' ).each( function ()
                {
                    this.checked = false;
                } );
            }
        } );

        function sort_customers(el) {
            $( '#sort_customers' ).submit();
        }

        function confirm_ban(url) {
            $( '#confirm-ban' ).modal( 'show', { backdrop: 'static' } );
            document.getElementById( 'confirmation' ).setAttribute( 'href', url );
        }

        function confirm_unban(url) {
            $( '#confirm-unban' ).modal( 'show', { backdrop: 'static' } );
            document.getElementById( 'confirmationunban' ).setAttribute( 'href', url );
        }

        function bulk_delete() {
            var data = new FormData( $( '#sort_customers' )[0] );
            $.ajax( {
                headers: {
                    'X-CSRF-TOKEN': $( 'meta[name="csrf-token"]' ).attr( 'content' )
                },
                url: "{{route('bulk-customer-delete')}}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function (response)
                {
                    if ( response == 1 ) {
                        location.reload();
                    }
                }
            } );
        }
    </script>
@endsection
