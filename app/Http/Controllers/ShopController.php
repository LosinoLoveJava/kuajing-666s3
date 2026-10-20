<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use Cookie;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\User;
use App\Models\SellerPackage;
use App\Models\BusinessSetting;
use App\Models\SellerPackagePayment;
use Auth;
use Hash;
use App\Notifications\EmailVerificationNotification;
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
         Upload::where('user_id', 0)->delete();
        if ( Auth::check() )
        {
            if ( Auth::user()->user_type == 'admin' )
            {
                return view('backend.sellers.seller_form');
            }
            else if ( Auth::user()->user_type == 'salesman' )
            {
                return view('salesman.sellers.seller_form');
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
            return view('frontend.seller_form');
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
        
         $package = SellerPackage::where(['is_default' => 1 ])->first();
         $package_id = $package['id'];
         
         
        $user = NULL;
        if ( $request->identity_card_front == NULL )
        {
            flash(translate('Identity Card Front Not Allow Empty!'))->error();
            return back();
        }
        if ( $request->identity_card_back == NULL )
        {
            flash(translate('Identity Card Back Not Allow Empty!'))->error();
            return back();
        }
        if ( !Auth::check() )
        {
            if ( User::where('email', $request->email)->first() != NULL )
            {
                flash(translate('Email already exists!'))->error();
                return back();
            }
            if ( $request->password == $request->password_confirmation )
            {
                $user = new User;
                $user->name = $request->name;
                $user->email = $request->email;
                $user->user_type = "seller";
                $user->password = Hash::make($request->password);
                $user->save();
            }
            else
            {
                flash(translate('Sorry! Password did not match.'))->error();
                return back();
            }
        }
        else
        {//如果有登录用户
            if ( Auth::user()->user_type == 'admin' )
            {//管理后台
                if ( User::where('email', $request->email)->first() != NULL )
                {
                    flash(translate('Email already exists!'))->error();
                    return back();
                }
                if ( $request->password == $request->password_confirmation )
                {
                    $user = new User;
                    $user->name = $request->name;
                    $user->email = $request->email;
                    $user->user_type = "seller";
                    $user->is_virtual = "1";
                    $user->password = Hash::make($request->password);
                    $user->email_verified_at = date('Y-m-d H:m:s');
                    $user->save();
                }
                else
                {
                    flash(translate('Sorry! Password did not match.'))->error();
                    return back();
                }
            }
            else if ( Auth::user()->user_type == 'salesman' )
            {//推销员
                if ( User::where('email', $request->email)->first() != NULL )
                {
                    flash(translate('Email already exists!'))->error();
                    return back();
                }
                if ( $request->password == $request->password_confirmation )
                {
                    $user = new User;
                    $user->name = $request->name;
                    $user->email = $request->email;
                    $user->user_type = "seller";
                    $user->is_virtual = "1";
                    $user->pid = Auth::user()->id;
                    $user->password = Hash::make($request->password);
                    $user->email_verified_at = date('Y-m-d H:m:s');
                    $user->save();
                }
                else
                {
                    flash(translate('Sorry! Password did not match.'))->error();
                    return back();
                }
            }
            else
            {
                $user = Auth::user();
                if ( $user->customer != NULL )
                {
                    $user->customer->delete();
                }
                $user->user_type = "seller";
                $user->save();
            }
        }

        if ( Shop::where('user_id', $user->id)->first() == NULL )
        {
            $shop = new Shop;
            $shop->user_id = $user->id;
            $shop->name = $request->name;
            $shop->address = $request->address;
            $shop->slug = preg_replace('/\s+/', '-', $request->name);
            $user->identity_card_front = $request->identity_card_front;
            $user->identity_card_back = $request->identity_card_back;
            $user->certtype = $request->certtype;
            $shop->seller_package_id = $package_id;
           
           
           
               
               
            if ( Cookie::get('invitation_code') )
            {
                $user->pid = Cookie::get('invitation_code');
            }
            Upload::where('user_id', 0)->where('id', 'NOTIN', [ $user->identity_card_front, $user->identity_card_back ])->update([ 'user_id' => $user->id ]);
            if ( $shop->save() )
            {
                
                
                #####################################
                
          
            $shop->seller_package_id = $package_id;
            $seller_package = SellerPackage::findOrFail( $package_id );
            $shop->product_upload_limit = $seller_package->product_upload_limit;
            $shop->package_invalid_at = date('Y-m-d', strtotime($seller->package_invalid_at . ' +' . $seller_package->duration . 'days'));
            $shop->save();
    
            $seller_package = new SellerPackagePayment;
            $seller_package->user_id = $user->id;
            $seller_package->seller_package_id =  $package_id;
            $seller_package->payment_method = 'free';
            $seller_package->payment_details = '';
            $seller_package->approval = 1;
            $seller_package->offline_payment = 0;
            $seller_package->save();
            
        
                #####################################
                
                
                
                
                if ( Auth::check() )
                {
                    if ( Auth::user()->user_type == 'admin' )
                    {//管理后台
                        flash(translate('Virtual Seller has been created successfully!'))->success();
                        return redirect()->route('sellers.index');
                    }
                    else if ( Auth::user()->user_type == 'salesman' )
                    {//推销员
                        flash(translate('Virtual Seller has been created successfully!'))->success();
                        return redirect()->route('salesman.sellers_index');
                    }
                }
                else
                {
                    auth()->login($user, false);
                }
                if ( BusinessSetting::where('type', 'email_verification')->first()->value != 1 )
                {
                    $user->email_verified_at = date('Y-m-d H:m:s');
                    $user->save();
                }
                else
                {
                    $user->notify(new EmailVerificationNotification());
                }
                flash(translate('Your Shop has been created successfully!'))->success();
                return redirect()->route('shops.index');
            }
            else
            {
                $user->user_type == 'customer';
                $user->save();
            }
        }

        flash(translate('Sorry! Something went wrong.'))->error();
        return back();
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
