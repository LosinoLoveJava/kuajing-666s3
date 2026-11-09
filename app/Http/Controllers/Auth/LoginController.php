<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Socialite;
use App\Models\User;
use App\Models\Customer;
use App\Models\Cart;
use Session;
use Illuminate\Http\Request;
use CoreComponentRepository;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers { login as protected originalLogin; }

    public function adminPhoneLogin(Request $request)
    {
        $user = app(\App\Services\PhoneAuth::class)->loginAdmin($request);
        app(\App\Services\PortalLogin::class)->login($user, $request, $request->boolean('remember'));
        return redirect(\App\Services\PortalContext::dashboardUrl($user));
    }

    public function adminPhoneCode(Request $request)
    {
        app(\App\Services\PhoneAuth::class)->sendAdminCode($request);
        return response()->json(['result' => true, 'message' => 'SMS submitted. Code expires in 5 minutes.']);
    }

    public function adminPhoneSubmit(Request $request)
    {
        $request->validate(['mode' => 'required|in:login']);
        $user = app(\App\Services\PhoneAuth::class)->loginAdmin($request);
        app(\App\Services\PortalLogin::class)->login($user, $request, $request->boolean('remember'));
        return redirect(\App\Services\PortalContext::dashboardUrl($user));
    }

    public function showLoginForm()
    {
        if (in_array(\App\Services\PortalContext::current(), ['admin', 'sales', 'delivery'], true)) {
            return view('auth.login');
        }
        return view('frontend.phone_access', [
            'mode' => 'login',
            'isSellerPortal' => \App\Services\PortalContext::current() === 'seller',
        ]);
    }

    public function login(Request $request)
    {
        if ($request->filled('phone')) {
            $phone = (string) $request->phone;
            $request->validate(['country_code' => 'nullable|in:971']);
            $request->merge(['mode' => 'login', 'phone' => app(\App\Services\PhoneNumber::class)->normalize($phone, $request->country), 'country' => 'AE']);
            return app(\App\Http\Controllers\PhoneAccessController::class)->submit($request);
        }
        return $this->originalLogin($request);
    }

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    /*protected $redirectTo = '/';*/


    /**
     * Redirect the user to the Google authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider($provider)
    {
        if(request()->get('query') == 'mobile_app'){
            request()->session()->put('login_from', 'mobile_app');
        }
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from Google.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback(Request $request, $provider)
    {
        if (session('login_from') == 'mobile_app') {
            return $this->mobileHandleProviderCallback($request, $provider);
        }
        try {
            if ($provider == 'twitter') {
                $user = Socialite::driver('twitter')->user();
            } else {
                $user = Socialite::driver($provider)->stateless()->user();
            }
        } catch (\Exception $e) {
            flash("Something Went wrong. Please try again.")->error();
            return redirect()->route('user.login');
        }

        //check if provider_id exist
        $existingUserByProviderId = User::where('provider_id', $user->id)->first();

        if ($existingUserByProviderId) {
            //proceed to login
            $account = $existingUserByProviderId;
        }
        else {
            //check if email exist
            $existingUser = User::where('email', $user->email)->first();

            if ($existingUser) {
                //update provider_id
                $existing_User = $existingUser;
                $existing_User->provider_id = $user->id;
                $existing_User->save();

                //proceed to login
                $account = $existing_User;
            } else {
                //create a new user
                $newUser = new User;
                $newUser->name = $user->name;
                $newUser->email = $user->email;
                $newUser->email_verified_at = date('Y-m-d Hms');
                $newUser->provider_id = $user->id;
                $newUser->save();

                //make user a customer
                // $customer = new Customer;
                // $customer->user_id = $newUser->id;
                // $customer->save();

                //proceed to login
                $account = $newUser;
            }
        }

        app(\App\Services\PortalLogin::class)->login($account, $request, true);

        if (session('temp_user_id') != null) {
            Cart::where('temp_user_id', session('temp_user_id'))
                ->update([
                    'user_id' => $account->id,
                    'temp_user_id' => null
                ]);

            Session::forget('temp_user_id');
        }

        if (session('link') != null) {
            return redirect(session('link'));
        } else {
            return redirect(\App\Services\PortalContext::dashboardUrl($account));
        }
    }

    public function mobileHandleProviderCallback($request, $provider)
    {
        $return_provider = '';
        $result = false;
        if($provider) {
            $return_provider = $provider;
            $result = true;
        }
        return response()->json([
            'result' => $result,
            'provider' => $return_provider
        ]);
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        // Enforce the role in the authentication query, not after login.
        // Customer/seller email credentials cannot bypass the phone-only UI
        // by posting directly to the management login endpoint.
        return array_merge($request->only('email', 'password'), [
            'user_type' => ['admin', 'staff', 'salesman', 'delivery_boy'],
        ]);
    }

    /**
     * Check user's role and redirect user based on their role
     * @return
     */
    public function authenticated()
    {
        $user = auth()->user();
        if (\App\Services\PortalContext::forUser($user) !== \App\Services\PortalContext::current()) {
            app(\App\Services\PortalLogin::class)->login($user, request(), request()->boolean('remember'));
            $this->guard()->logoutCurrentDevice();
            return redirect(\App\Services\PortalContext::dashboardUrl($user));
        }
        if (session('temp_user_id') != null) {
            Cart::where('temp_user_id', session('temp_user_id'))
                ->update(
                    [
                        'user_id' => auth()->user()->id,
                        'temp_user_id' => null
                    ]
                );

            Session::forget('temp_user_id');
        }

        if (auth()->user()->user_type == 'admin' || auth()->user()->user_type == 'staff') {
            CoreComponentRepository::instantiateShopRepository();
            return redirect()->route('admin.dashboard');
        } elseif (auth()->user()->user_type == 'seller') {
            return redirect()->route('seller.dashboard');
        } else {

            if (session('link') != null) {
                return redirect(session('link'));
            } else {
                return redirect(\App\Services\PortalContext::dashboardUrl(auth()->user()));
            }
        }
    }

    /**
     * Get the failed login response instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        flash(translate('Invalid login credentials'))->error();
        return back();
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $redirect_url = \App\Services\PortalContext::loginUrl();

        //User's Cart Delete
        if (auth()->user()) {
            Cart::where('user_id', auth()->user()->id)->delete();
        }

        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->loggedOut($request) ?: redirect($redirect_url);
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
}
