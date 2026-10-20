<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SellerSpreadPackage;
use App\Models\Payment;
use App\Models\SellerSpreadPackageTranslation;
use App\Models\SellerSpreadPackagePayment;
use App\Models\Shop;
use Auth;
use Session;
use Carbon\Carbon;
use function back;
use function class_exists;
use function collect;
use function date;
use function dd;
use function flash;
use function print_r;
use function redirect;
use function str_replace;
use function strtotime;
use function time;
use function translate;
use function ucwords;
use DB;

class SellerSpreadPackageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $seller_spread_packages = SellerSpreadPackage::all();
        return view('seller_spread_packages.index', compact('seller_spread_packages'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('seller_spread_packages.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $seller_spread_package = new SellerSpreadPackage;
        $seller_spread_package->name = $request->name;
        $seller_spread_package->amount = $request->amount;
        $seller_spread_package->product_upload_limit = $request->product_upload_limit;
        $seller_spread_package->duration = $request->duration;
        $seller_spread_package->logo = $request->logo;
        $seller_spread_package->max_profit =  min(255, (int)$request->max_profit);
        if ($seller_spread_package->save()) {

            $seller_spread_package_translation = SellerSpreadPackageTranslation::firstOrNew(['lang' => env('DEFAULT_LANGUAGE'), 'seller_spread_package_id' => $seller_spread_package->id]);
            $seller_spread_package_translation->name = $request->name;
            $seller_spread_package_translation->save();

            flash(translate('Package has been inserted successfully'))->success();
            return redirect()->route('seller_spread_packages.index');
        } else {
            flash(translate('Something went wrong'))->error();
            return back();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $lang = $request->lang;
        $seller_spread_package = SellerSpreadPackage::findOrFail($id);
        return view('seller_spread_packages.edit', compact('seller_spread_package', 'lang'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $seller_spread_package = SellerSpreadPackage::findOrFail($id);
        if ($request->lang == env("DEFAULT_LANGUAGE")) {
            $seller_spread_package->name = $request->name;
            $seller_spread_package->max_profit = $request->max_profit;
        }
        $seller_spread_package->amount = $request->amount;
        $seller_spread_package->product_upload_limit = $request->product_upload_limit;
        $seller_spread_package->duration = $request->duration;
        $seller_spread_package->max_profit = $request->max_profit;
        $seller_spread_package->logo = $request->logo;
        if ($seller_spread_package->save()) {
            $seller_spread_package_translation = SellerSpreadPackageTranslation::firstOrNew(['lang' => $request->lang, 'seller_spread_package_id' => $seller_spread_package->id]);
            $seller_spread_package_translation->name = $request->name;
            $seller_spread_package_translation->max_profit = $request->max_profit;
            $seller_spread_package_translation->save();
            flash(translate('Package has been inserted successfully'))->success();
            return redirect()->route('seller_spread_packages.index');
        } else {
            flash(translate('Something went wrong'))->error();
            return back();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $seller_spread_package = SellerSpreadPackage::findOrFail($id);
        foreach ($seller_spread_package->seller_spread_package_translations as $key => $seller_spread_package_translation) {
            $seller_spread_package_translation->delete();
        }
        SellerSpreadPackage::destroy($id);
        flash(translate('Package has been deleted successfully'))->success();
        return redirect()->route('seller_spread_packages.index');
    }


    //FrontEnd
    //@index
    public function spread_packages_payment_list()
    {
        $seller_spread_packages_payment = SellerSpreadPackagePayment::with('seller_spread_package')->where('user_id', Auth::user()->id)->latest()->paginate(10);
        return view('seller_spread_packages.frontend.spread_packages_payment_list', compact('seller_spread_packages_payment'));
    }

    public function seller_spread_packages_list()
    {
        $seller_spread_packages = SellerSpreadPackage::all();
        return view('seller_spread_packages.frontend.seller_spread_packages_list', compact('seller_spread_packages'));
    }



    public function purchase_spread_package(Request $request)
    {
        $data['seller_spread_package_id'] = $request->seller_spread_package_id;
        $data['payment_method'] = $request->payment_option;

        $request->session()->put('payment_type', 'seller_spread_package_payment');
        $request->session()->put('payment_data', $data);

        $seller_spread_package = SellerSpreadPackage::findOrFail(Session::get('payment_data')['seller_spread_package_id']);
//        print_r(collect($seller_spread_package));dd();

        if ( $seller_spread_package->amount == 0 )
        {
            $this->spread_purchase_payment_done(Session::get('payment_data'), NULL);
            flash(translate('Package purchasing successful'))->success();
            return redirect()->route('seller.dashboard');
        }
        else if ( Auth::user()->shop->seller_spread_package != NULL && $seller_spread_package->product_upload_limit < Auth::user()->shop->seller_spread_package->product_upload_limit )
        {
            flash(translate('You have more uploaded products than this package limit. You need to remove excessive products to downgrade.'))->warning();
            return back();
        }

        $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
        if (class_exists($decorator)) {
            return (new $decorator)->pay($request);
        }
    }

    public function buy_spread_cash(Request $request)
    {
       $package_id = $request->package_id;
        $seller_package = SellerSpreadPackage::findOrFail( $package_id );

    $name = $seller_package->name;
        $user = Auth::user();

        $shop_id = $user->shop->id;



       DB::beginTransaction();



        $amount = $seller_package->amount;


        if ($user->balance >= $seller_package->amount)
        {
            $user->balance -= $seller_package->amount;
            $user->save();

            $shop = Shop::findOrFail($shop_id );
            $shop->seller_spread_package_id = $package_id;

            $shop->product_upload_limit = $seller_package->product_upload_limit;
            $shop->package_invalid_at = date('Y-m-d', strtotime($seller->package_invalid_at . ' +' . $seller_package->duration . 'days'));
            $res = $shop->save();


           /* $seller_package = new SellerSpreadPackagePayment;
            $seller_package->user_id = $shop->user_id;
            $seller_package->seller_spread_package_id =  $package_id;
            $seller_package->payment_method = 'wallet';
            $seller_package->payment_details = '';
            $seller_package->approval = 1;
            $seller_package->offline_payment = 0;
            $seller_package->save();*/
            $sellerSpreadPackagePaymentModel = new SellerSpreadPackagePayment;
            $sellerSpreadPackagePaymentModel->user_id = $shop->user_id;
            $sellerSpreadPackagePaymentModel->seller_spread_package_id =  $package_id;
            $sellerSpreadPackagePaymentModel->product_spread_limit =  $seller_package->product_upload_limit;
            $sellerSpreadPackagePaymentModel->payment_method = 'wallet';
            $sellerSpreadPackagePaymentModel->payment_details = '';
            $sellerSpreadPackagePaymentModel->approval = 1;
            $sellerSpreadPackagePaymentModel->offline_payment = 0;
            $sellerSpreadPackagePaymentModel->expire_at = (time() + 3600*24*(int)$seller_package->duration);
            $sellerSpreadPackagePaymentModel->save();




            $payment = new Payment;
            $payment->seller_id = $user->id;
            $payment->amount = -1* abs($amount);
            $payment->payment_method = 'wallet';
            $payment->txn_code = date("YmdHis");
            $payment->payment_details = $name;
            $payment->save();





            DB::commit();


            return response()->json(['success' => 1, 'message' => translate('Payment completed')]);
        }
        DB::rollBack();
        return response()->json(['success' => 0, 'message' => translate('Insufficient balance')]);





    }
    public function spread_purchase_payment_done($payment_data, $payment)
    {
        $seller = Auth::user()->shop;
        $seller->seller_spread_package_id = Session::get('payment_data')['seller_spread_package_id'];
        $seller_spread_package = SellerSpreadPackage::findOrFail(Session::get('payment_data')['seller_spread_package_id']);
        $seller->product_spread_limit = $seller_spread_package->product_upload_limit;
        $seller->spread_package_invalid_at = date('Y-m-d', strtotime($seller->spread_package_invalid_at . ' +' . $seller_spread_package->duration . 'days'));
        $seller->save();

        $seller_package = new SellerSpreadPackagePayment;
        $seller_package->user_id = Auth::user()->id;
        $seller_package->seller_spread_package_id = Session::get('payment_data')['seller_spread_package_id'];
        $seller_package->payment_method = Session::get('payment_data')['payment_method'];
        $seller_package->payment_details = $payment;
        $seller_package->approval = 1;
        $seller_package->product_spread_limit = $seller_spread_package->duration;
        $seller_package->expire_at = time()+$seller_spread_package->duration*86400;
        $seller_package->offline_payment = 2;
        $seller_package->save();
    }

    public function unpublish_products(Request $request)
    {
        foreach (Shop::all() as $shop) {
            if ($shop->package_invalid_at != null && Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) <= 0) {
                foreach ($shop->user->products as $product) {
                    $product->published = 0;
                    $product->save();
                }
                $shop->seller_spread_package_id = null;
                $shop->save();
            }
        }
    }

    public function purchase_spread_package_offline(Request $request)
    {
        $seller_spread_package = SellerSpreadPackage::findOrFail($request->package_id);

        if (Auth::user()->shop->seller_spread_package != null && $seller_spread_package->product_upload_limit < Auth::user()->shop->seller_spread_package->product_upload_limit) {
            flash(translate('You have more uploaded products than this package limit. You need to remove excessive products to downgrade.'))->warning();
            return redirect()->route('seller.seller_spread_packages_list');
        }
        $seller_spread_package = new SellerSpreadPackagePayment;
        $seller_spread_package->user_id = Auth::user()->id;
        $seller_spread_package->seller_spread_package_id = $request->package_id;
        $seller_spread_package->payment_method = $request->payment_option;
        $seller_spread_package->payment_details = $request->trx_id;
        $seller_spread_package->approval = 0;
        $seller_spread_package->offline_payment = 1;
        $seller_spread_package->reciept = $request->photo;
        $seller_spread_package->save();
        flash(translate('Offline payment has been done. Please wait for response.'))->success();
        return redirect()->route('seller.products');
    }
}
