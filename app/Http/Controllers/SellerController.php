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
        abort(404);
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
        abort(404);
    }
    public function setviews(Request $request)
    {
        $data = $request->validate([
            'shop_id' => 'required|integer|min:1|exists:shops,id',
            'base_num' => 'required|integer|min:0|max:1000000000',
            'inc_num' => 'required|integer|min:0|max:1000000000',
        ]);

        app(\App\Services\ShopViewCounter::class)->configure(
            (int) $data['shop_id'], (int) $data['base_num'], (int) $data['inc_num']
        );

        return response()->json(['msg' => translate('Success')]);
    }
    
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'approved_status' => 'nullable|in:0,1',
            'is_virtual_user' => 'nullable|in:0,1',
            'user_id' => 'nullable|integer|min:1',
            'date' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2} to \d{4}-\d{2}-\d{2}$/'],
        ]);
        $sort_search = $filters['search'] ?? null;
        $approved = $filters['approved_status'] ?? null;
        $is_virtual_user = $filters['is_virtual_user'] ?? null;
        $user_id = $filters['user_id'] ?? null;
        $date = $filters['date'] ?? null;

        $shops = Shop::whereHas('user', function ($query) use ($sort_search, $is_virtual_user, $user_id) {
            if ($sort_search !== null && $sort_search !== '') {
                $query->where('user_type', 'seller')->where(function ($user) use ($sort_search) {
                    $user->where('name', 'like', '%'.$sort_search.'%')
                        ->orWhere('phone', 'like', '%'.$sort_search.'%')
                        ->orWhere('phone_normalized', 'like', '%'.$sort_search.'%')
                        ->orWhere('email', 'like', '%'.$sort_search.'%');
                });
            }
            if ($is_virtual_user !== null && $is_virtual_user !== '') {
                $query->where('is_virtual', $is_virtual_user);
            }
            if ($user_id !== null && $user_id !== '') {
                $query->where('pid', $user_id);
            }
        })->with(['user' => function ($query) {
            // Only hydrate this page's accounts; catalogue and ledger rows stay in SQL.
            $query->withCount('products')->withSum(['wallets as recharge_total' => function ($wallets) {
                $wallets->where('approval', 1)->where(function ($query) {
                    $query->whereNull('payment_method')->orWhere('payment_method', '!=', 'seller_order_reward');
                });
            }], 'amount')->withSum(['seller_withdraw_requests as withdrawal_total' => function ($withdrawals) {
                $withdrawals->where('status', 1);
            }], 'amount')->with('parent_user:id,name');
        }])->latest()->orderByDesc('id');

        if ($approved !== null && $approved !== '') {
            $shops->where('verification_status', $approved);
        }
        if ($date !== null && $date !== '') {
            [$start, $end] = explode(' to ', $date);
            validator(['start' => $start, 'end' => $end], [
                'start' => 'required|date_format:Y-m-d',
                'end' => 'required|date_format:Y-m-d|after_or_equal:start',
            ])->validate();
            $shops->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59']);
        }
        $shops = $shops->paginate(15);
        $salesmans = User::where('user_type', 'salesman')->latest()->get(['id', 'name', 'email']);

        return view('backend.sellers.index', compact('shops', 'sort_search', 'approved', 'date', 'user_id', 'is_virtual_user', 'salesmans'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.sellers.create', ['countries' => app(\App\Services\PhoneNumber::class)->countries()]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $user = app(\App\Services\ManagedPhoneAccounts::class)->createSeller($request, Auth::user(), false);
            $seller = new Seller;
            $seller->user_id = $user->id;
            $seller->save();
        });
        flash(translate('Seller has been inserted successfully'))->success();
        return redirect()->route(Auth::user()->user_type === 'salesman' ? 'salesman.sellers_index' : 'sellers.index');
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
        app(\App\Services\ManagedPhoneAccounts::class)->authorize(Auth::user(), $shop->user);
        return view('backend.sellers.edit', ['shop' => $shop, 'countries' => app(\App\Services\PhoneNumber::class)->countries()]);
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
        app(\App\Services\ManagedPhoneAccounts::class)->updateSeller($request, $shop, Auth::user());
        flash(translate('Seller has been updated successfully'))->success();
        return redirect()->route(Auth::user()->user_type === 'salesman' ? 'salesman.sellers_index' : 'sellers.index');
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
        app(\App\Services\PortalLogin::class)->login($user, request(), true);

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
                $user->where('name', 'like', '%' . $sort_search . '%')->orWhere('phone', 'like', '%' . $sort_search . '%')->orWhere('phone_normalized', 'like', '%' . $sort_search . '%')->orWhere('email', 'like', '%' . $sort_search . '%');
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
