<?php

namespace App\Http\Controllers;

use App\Models\AffiliateConfig;
use App\Models\Customer;
use App\Models\Address;
use App\Models\User;
use Auth;
use DB;
use Cookie;
use Illuminate\Http\Request;
use App\Models\City;
use App\Models\SellerWithdrawRequest;
use function dd;


class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index( Request $request ) {
        $sort_search = NULL;

        $users = User::where('user_type', 'customer')->where('email_verified_at', '!=', NULL)->where('is_virtual', 0)->orderBy('created_at', 'desc');
        if ( $request->has('search') )
        {
            $sort_search = $request->search;
            $users->where(function ( $q ) use ( $sort_search )
            {
                $q->where('name', 'like', '%' . $sort_search . '%')->orWhere('email', 'like', '%' . $sort_search . '%');
            });
        }
        $users = $users->paginate(15);
        return view('backend.customer.customers.index', compact('users', 'sort_search'));
    }


    public function create_virtual_user( Request $request ) {
        try
        {
            \DB::beginTransaction();

            $max = \intval($request->input('max')) < 1 ? 1 : ( \intval($request->input('max')) > 100 ? 100 : \intval($request->input('max')) );
            for ( $i = 0; $i < $max; $i++ )
            {
                $faker = \Faker\Factory::create();
                $city = City::skip(mt_rand(0, City::count() - 1))->take(1)->first();
                $user = new User();
                $user->name = $faker->name;
                $user->is_virtual_user = 1;
                //$user->password = bcrypt('test');
                $user->email = $faker->email;
                $user->email_verified_at = \date('Y-m-d H:i:s');
                $user->balance = \sprintf('%0.2f', $request->balance);
                $user->saveOrFail();

                $referred_by = User::where('id', $request->referred_by)->first();

                if ( $referred_by )
                {
                    $user->referred_by = $request->referred_by;
                    $user->node = $referred_by->node . ',' . $user->id;
                    $user->deep = $referred_by->deep + 1;
                }
                else
                {
                    $user->node = $user->id;
                }
                $user->saveOrFail();

                $address = new Address();
                $address->user_id = $user->id;
                $address->country_id = $city->state->country->id;
                $address->state_id = $city->state->id;
                $address->city_id = $city->id;
                $address->address = $faker->address;
                $address->postal_code = $faker->postcode;
                $address->phone = $faker->e164PhoneNumber;
                $address->saveOrFail();
            }
            \DB::commit();
            return 1;
        }
        catch ( \Throwable $ex )
        {
            \DB::rollBack();
            throw $ex;
        }
        return 0;

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function salesman_index( Request $request ) {
        $sort_search = NULL;
        $users = User::where('user_type', 'customer')->where('referred_by', Auth::user()->id)->orderBy('created_at', 'desc');
        if ( $request->has('search') )
        {
            $sort_search = $request->search;
            $users->where(function ( $q ) use ( $sort_search )
            {
                $q->where('name', 'like', '%' . $sort_search . '%')->orWhere('email', 'like', '%' . $sort_search . '%');
            });
        }
        $users = $users->paginate(15);
        return view('salesman.customers.index', compact('users', 'sort_search'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create( Request $request ) {
        if ( $request->has('referral_code') && addon_is_activated('affiliate_system') )
        {
            try
            {
                $affiliate_validation_time = AffiliateConfig::where('type', 'validation_time')->first();
                $cookie_minute = 30 * 24;
                if ( $affiliate_validation_time )
                {
                    $cookie_minute = $affiliate_validation_time->value * 60;
                }

                Cookie::queue('referral_code', $request->referral_code, $cookie_minute);
                $referred_by_user = User::where('referral_code', $request->referral_code)->first();

                $affiliateController = new AffiliateController;
                $affiliateController->processAffiliateStats($referred_by_user->id, 1, 0, 0, 0);
            }
            catch ( \Exception $e )
            {
            }
        }
        return view('backend.customer.customers.create');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function salesman_create( Request $request ) {
        if ( $request->has('referral_code') && addon_is_activated('affiliate_system') )
        {
            try
            {
                $affiliate_validation_time = AffiliateConfig::where('type', 'validation_time')->first();
                $cookie_minute = 30 * 24;
                if ( $affiliate_validation_time )
                {
                    $cookie_minute = $affiliate_validation_time->value * 60;
                }

                Cookie::queue('referral_code', $request->referral_code, $cookie_minute);
                $referred_by_user = User::where('referral_code', $request->referral_code)->first();

                $affiliateController = new AffiliateController;
                $affiliateController->processAffiliateStats($referred_by_user->id, 1, 0, 0, 0);
            }
            catch ( \Exception $e )
            {
            }
        }
        return view('salesman.customers.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store( Request $request ) {
        if ( filter_var($request->email, FILTER_VALIDATE_EMAIL) )
        {
            if ( User::where('email', $request->email)->first() != NULL )
            {
                flash(translate('Email or Phone already exists.'));
                return back();
            }
        }
        else if ( User::where('phone', '+' . $request->country_code . $request->phone)->first() != NULL )
        {
            flash(translate('Phone already exists.'));
            return back();
        }
        $user = User::create($request->all());
        $user->email_verified_at = date('Y-m-d H:m:s');
        $user->user_type = 'customer';
        $user->is_virtual = '1';
        if ( $user->balance != $request->balance ) $user->balance = (float)$request->balance;
        $user->save();
        flash(translate('Add successful.'))->success();

        return redirect()->route('customers.index');


        /*$request->validate([
            'name' => 'required',
            'email' => 'required|unique:users|email',
            'phone' => 'required|unique:users',
        ]);

        $response['status'] = 'Error';

        $user = User::create($request->all());

        $customer = new Customer;

        $customer->user_id = $user->id;
        $customer->save();

        if ( isset($user->id) )
        {
            $html = '';
            $html .= '<option value="">
                        ' . translate("Walk In Customer") . '
                    </option>';
            foreach ( Customer::all() as $key => $customer )
            {
                if ( $customer->user )
                {
                    $html .= '<option value="' . $customer->user->id . '" data-contact="' . $customer->user->email . '">
                                ' . $customer->user->name . '
                            </option>';
                }
            }

            $response['status'] = 'Success';
            $response['html'] = $html;
        }

        echo json_encode($response);*/
    }

    public function salesman_store( Request $request ) {
        if ( filter_var($request->email, FILTER_VALIDATE_EMAIL) )
        {
            if ( User::where('email', $request->email)->first() != NULL )
            {
                flash(translate('Email or Phone already exists.'));
                return back();
            }
        }
        else if ( User::where('phone', '+' . $request->country_code . $request->phone)->first() != NULL )
        {
            flash(translate('Phone already exists.'));
            return back();
        }
        $user = User::create($request->all());
        $user->email_verified_at = date('Y-m-d H:m:s');
        $user->user_type = 'customer';
        $user->is_virtual = '1';
        if ( $user->balance != $request->balance ) $user->balance = (float)$request->balance;
        $user->save();
        flash(translate('Add successful.'))->success();

        return redirect()->route('salesman.customers.index');
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

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update( Request $request, $id ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy( $id ) {
        User::destroy($id);
        flash(translate('Customer has been deleted successfully'))->success();
        return redirect()->route('customers.index');
    }

    public function bulk_customer_delete( Request $request ) {
        if ( $request->id )
        {
            foreach ( $request->id as $customer_id )
            {
                $this->destroy($customer_id);
            }
        }

        return 1;
    }

    public function login( $id ) {
        $user = User::findOrFail(decrypt($id));

        auth()->login($user, true);

        return redirect()->route('dashboard');
    }

    public function ban( $id ) {
        $user = User::findOrFail(decrypt($id));

        if ( $user->banned == 1 )
        {
            $user->banned = 0;
            flash(translate('Customer UnBanned Successfully'))->success();
        }
        else
        {
            $user->banned = 1;
            flash(translate('Customer Banned Successfully'))->success();
        }

        $user->save();

        return back();
    }
}
