<?php

namespace App\Http\Controllers;

use Cookie;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\User;
use Auth;
use function dd;
use function view;

class ShopController extends Controller
{

    public function __construct() {
        $this->middleware('user', [ 'only' => [ 'index' ] ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $shop = Auth::user()->shop;
        return view('seller.shop', compact('shop'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create( Request $request ) {
        if ( Auth::check() )
        {
            if ( in_array(Auth::user()->user_type, ['admin', 'staff'], true) )
            {
                return view('backend.sellers.seller_form', ['countries' => app(\App\Services\PhoneNumber::class)->countries()]);
            }
            else if ( Auth::user()->user_type == 'salesman' )
            {
                return view('salesman.sellers.seller_form', ['countries' => app(\App\Services\PhoneNumber::class)->countries()]);
            }
            else if ( Auth::user()->user_type == 'customer' )
            {
                flash(translate('Customer can not be a seller'))->error();
                return back();
            }
            else if ( Auth::user()->user_type == 'seller' )
            {
                flash(translate('This user already a seller'))->error();
                return back();
            }

        }
        else
        {
            if ( $request->has('invitation_code') )
            {
                Cookie::queue('invitation_code', $request->invitation_code, 720);
            }
            $countries = app(\App\Services\PhoneNumber::class)->countries();
            return view('frontend.seller_form', compact('countries'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store( Request $request ) {
        if (!Auth::check()) {
            // The public shop application uses the same verified phone registration as customers.
            $user = app(\App\Services\SellerRegistration::class)->register($request);
            app(\App\Services\PortalLogin::class)->login($user, $request);
            flash(translate('Your Shop has been created successfully!'))->success();
            return redirect()->route('shops.index', ['portal' => 'seller']);
        }

        app(\App\Services\ManagedPhoneAccounts::class)->createSeller($request, Auth::user());
        flash(translate('Virtual Seller has been created successfully!'))->success();
        return redirect()->route(Auth::user()->user_type === 'salesman' ? 'salesman.sellers_index' : 'sellers.index');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show( $id ) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit( $id ) {
        //
    }

    public function destroy( $id ) {
        //
    }
}
