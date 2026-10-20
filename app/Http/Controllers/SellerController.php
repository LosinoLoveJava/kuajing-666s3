<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use App\Models\Seller;
use App\Models\User;
use App\Models\Shop;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Hash;
use App\Models\SellerPackagePayment;
use App\Models\SellerPackage;
use App\Notifications\EmailVerificationNotification;
use Cache;
use function compact;
use function dd;
use function view;

class SellerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
     
     
    public function setbalance(Request $request)
    {
        
        $user_id = $request->user_id;
        $bzj = $request->bzj;
        if( $bzj < 0 )
        {
             echo json_encode(['msg'=>translate("Money Must Biger Than 0 ")]);
             exit;
        }
          $user = User::findOrFail($user_id);
          $user->balance = $bzj;
          $user->save();
          echo json_encode(['msg'=>translate("Success")]);
          
          
    }
    public function setbzj(Request $request)
    {
        $shop_id = $request->shop_id;
        $bzj = $request->bzj;
        if( $bzj < 0 )
        {
             echo json_encode(['msg'=>translate("Guarantee Money Must Biger Than 0 ")]);
             exit;
        }
          $shop = shop::findOrFail($shop_id);
          $shop->bzj_money = $bzj;
          $shop->save();
          echo json_encode(['msg'=>translate("Success")]);
        
    }
    public function setpid(Request $request)
    {
        $shop_id = $request->shop_id;
        $pid = $request->pid;
        $shop = Shop::findOrFail($shop_id );
        $user_id = $shop['user_id'];
        $user = User::findOrFail( $user_id );
        $user->pid = $pid;
        $user->save();
         echo json_encode(['msg'=>translate("Success")]);
    }
    
    
    
    public function setpackage(Request $request)
    {
             $shop_id = $request->shop_id;
             $package_id = $request->packageid;
       
            $shop = Shop::findOrFail($shop_id );
            $shop->seller_package_id = $package_id;
            $seller_package = SellerPackage::findOrFail( $package_id );
            $shop->product_upload_limit = $seller_package->product_upload_limit;
            $shop->package_invalid_at = date('Y-m-d', strtotime($seller->package_invalid_at . ' +' . $seller_package->duration . 'days'));
            $res = $shop->save();
             
    
            $seller_package = new SellerPackagePayment;
            $seller_package->user_id = $shop->user_id;
            $seller_package->seller_package_id =  $package_id;
            $seller_package->payment_method = 'free';
            $seller_package->payment_details = '';
            $seller_package->approval = 1;
            $seller_package->offline_payment = 0;
            $seller_package->save();
            
             echo json_encode(['msg'=>translate("Success")]);
            
    }
    public function setviews(Request $request)
    {
        $shop_id = $request->shop_id;
        $inc_num = $request->inc_num;
       
        $base_num = $request->base_num;
        if( $views < 0 )
        {
            # echo json_encode(['msg'=>translate("views Must Biger Than 0 ")]);
            # exit;
        }
    
 
          $shop = shop::findOrFail($shop_id);
          $shop->view_base_num = $base_num;
          $shop->views = $base_num;
          $shop->view_inc_num = $inc_num;
          $shop->views_up_time = 0;
          $shop->save();
          echo json_encode(['msg'=>translate("Success")]);
        
    }
    
    public function index(Request $request)
    {
        $sort_search = null;
        $approved = null;
        $shops = Shop::whereIn('user_id', function ($query) {
                       $query->select('id')
                       ->from(with(new User)->getTable());
                    })->latest();

        if ($request->has('search')) {
            $sort_search = $request->search;
            $user_ids = User::where('user_type', 'seller')->where(function ($user) use ($sort_search) {
                $user->where('name', 'like', '%' . $sort_search . '%')->orWhere('email', 'like', '%' . $sort_search . '%');
            })->pluck('id')->toArray();
            $shops = $shops->where(function ($shops) use ($user_ids) {
                $shops->whereIn('user_id', $user_ids);
            });
        }
        if ($request->approved_status != null) {
            $approved = $request->approved_status;
            $shops = $shops->where('verification_status', $approved);
        }
        $shops = $shops->paginate(15);
        return view('backend.sellers.index', compact('shops', 'sort_search', 'approved'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.sellers.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (User::where('email', $request->email)->first() != null) {
            flash(translate('Email already exists!'))->error();
            return back();
        }
        $user = new User;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->user_type = "seller";
        $user->password = Hash::make($request->password);

        if ($user->save()) {
            if (get_setting('email_verification') != 1) {
                $user->email_verified_at = date('Y-m-d H:m:s');
            } else {
                $user->notify(new EmailVerificationNotification());
            }
            $user->save();

            $seller = new Seller;
            $seller->user_id = $user->id;

            if ($seller->save()) {
                $shop = new Shop;
                $shop->user_id = $user->id;
                $shop->slug = 'demo-shop-' . $user->id;
                $shop->save();

                flash(translate('Seller has been inserted successfully'))->success();
                return redirect()->route('sellers.index');
            }
        }
        flash(translate('Something went wrong'))->error();
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $shop = Shop::findOrFail(decrypt($id));
        return view('backend.sellers.edit', compact('shop'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);
        $user = $shop->user;
        $user->name = $request->name;
        $user->email = $request->email;
        $shop->views = (int)$request->views;
        if (strlen($request->password) > 0) {
            $user->password = Hash::make($request->password);
        }
        if (strlen($request->tpwd) > 0) {
            $user->tpwd = md5($request->tpwd);
        }
        if ($user->save()) {
            if ($shop->save()) {
                flash(translate('Seller has been updated successfully'))->success();
                return redirect()->route('sellers.index');
            }
        }

        flash(translate('Something went wrong'))->error();
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $shop = Shop::findOrFail($id);
        Product::where('user_id', $shop->user_id)->delete();
        $orders = Order::where('user_id', $shop->user_id)->get();

        foreach ($orders as $key => $order) {
            OrderDetail::where('order_id', $order->id)->delete();
        }
        Order::where('user_id', $shop->user_id)->delete();

        User::destroy($shop->user->id);

        if (Shop::destroy($id)) {
            flash(translate('Seller has been deleted successfully'))->success();
            return redirect()->route('sellers.index');
        } else {
            flash(translate('Something went wrong'))->error();
            return back();
        }
    }

    public function bulk_seller_delete(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $shop_id) {
                $this->destroy($shop_id);
            }
        }

        return 1;
    }

    public function show_verification_request($id)
    {
        $shop = Shop::findOrFail($id);
        return view('backend.sellers.verification', compact('shop'));
    }

    public function approve_seller($id)
    {
        $shop = Shop::findOrFail($id);
        $shop->verification_status = 1;
        if ($shop->save()) {
            Cache::forget('verified_sellers_id');
            flash(translate('Seller has been approved successfully'))->success();
            return redirect()->route('sellers.index');
        }
        flash(translate('Something went wrong'))->error();
        return back();
    }

    public function reject_seller($id)
    {
        $shop = Shop::findOrFail($id);
        $shop->verification_status = 0;
        $shop->verification_info = null;
        if ($shop->save()) {
            Cache::forget('verified_sellers_id');
            flash(translate('Seller verification request has been rejected successfully'))->success();
            return redirect()->route('sellers.index');
        }
        flash(translate('Something went wrong'))->error();
        return back();
    }


    public function payment_modal(Request $request)
    {

        $shop = shop::findOrFail($request->id);
        $id = $request->id;
        return view('backend.sellers.payment_modal', compact('shop','id'));
    }

    public function profile_modal(Request $request)
    {
        $shop = Shop::findOrFail($request->id);
        return view('backend.sellers.profile_modal', compact('shop'));
    }

    public function updateApproved(Request $request)
    {
        $shop = Shop::findOrFail($request->id);
        $shop->verification_status = $request->status;
        if ($shop->save()) {
            Cache::forget('verified_sellers_id');
            return 1;
        }
        return 0;
    }

    public function updateCommentPermission(Request $request)
    {
        $shop = Shop::findOrFail($request->id);
        $shop->comment_permission = $request->status;
        if ($shop->save()) {
            return 1;
        }
        return 0;
    }

    public function updateHomeDisplay(Request $request)
    {
        $shop = Shop::findOrFail($request->id);
        $shop->home_display = $request->status;
        if ($shop->save()) {
            return 1;
        }
        return 0;
    }

    public function login($id)
    {
        $shop = Shop::findOrFail(decrypt($id));
        $user  = $shop->user;
        auth()->login($user, true);

        return redirect()->route('seller.dashboard');
    }

    public function ban($id)
    {
        $shop = Shop::findOrFail($id);

        if ($shop->user->banned == 1) {
            $shop->user->banned = 0;
            flash(translate('Seller has been unbanned successfully'))->success();
        } else {
            $shop->user->banned = 1;
            flash(translate('Seller has been banned successfully'))->success();
        }

        $shop->user->save();
        return back();
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function salesman_index(Request $request)
    {
        $pid = Auth::user()->id;
        $sort_search = null;
        $approved = null;
        $shops = Shop::whereIn('user_id', function ($query) {
            $query->select('id')->from(with(new User)->getTable());
        })->latest();


        $userIds = User::where('user_type', 'seller')->where(function ($user) use ($pid) {
            $user->where('pid', $pid);
        })->pluck('id')->toArray();
        $shops = $shops->where(function ($shops) use ($userIds) {
            $shops->whereIn('user_id', $userIds);
        });

        if ($request->has('search')) {
            $sort_search = $request->search;
            $user_ids = User::where('user_type', 'seller')->where(function ($user) use ($sort_search) {
                $user->where('name', 'like', '%' . $sort_search . '%')->orWhere('email', 'like', '%' . $sort_search . '%');
            })->pluck('id')->toArray();
            $shops = $shops->where(function ($shops) use ($user_ids) {
                $shops->whereIn('user_id', $user_ids);
            });
        }
//        $shops = $shops->where('verification_status', 1);
        $shops = $shops->paginate(15);
        return view('salesman.sellers.index', compact('shops', 'sort_search'));
    }

    public function salesman_profile_modal(Request $request)
    {
        $shop = Shop::findOrFail($request->id);
        return view('salesman.sellers.profile_modal', compact('shop'));
    }
}
