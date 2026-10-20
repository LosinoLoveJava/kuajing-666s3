<?php

namespace App\Http\Controllers;

use App\Models\SellerSpreadPackage;
use App\Models\SellerSpreadPackagePayment;
use Illuminate\Http\Request;
use App\Models\SellerPackage;
use App\Models\Payment;
use App\Models\SellerPackageTranslation;
use App\Models\SellerPackagePayment;
use App\Models\Shop;
use Auth;
use DB;
use Session;
use Carbon\Carbon;
use function back;
use function class_exists;
use function date;
use function flash;
use function redirect;
use function str_replace;
use function strtotime;
use function translate;
use function ucwords;

class SellerPackageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    
    
    public function buy_package_by_cash(Request $request)
    {
        $package_id = $request->package_id;
        $seller_packages = SellerPackage::findOrFail( $package_id );
         
        $name = $seller_packages->name;
        $user = Auth::user();
        
        $shop_id = $user->shop->id;
           
            
         #print_r($seller_packages->toArray());
        #exit;
        
       DB::beginTransaction();
        
  
        
        $amount = $seller_packages->amount;
        

        if ($user->balance >= $seller_packages->amount) 
        {
            $user->balance -= $seller_packages->amount;
            $user->save();
          
            $shop = Shop::findOrFail($shop_id );
            $shop->seller_package_id = $package_id;
            $seller_package = SellerPackage::findOrFail( $package_id );
            $shop->product_upload_limit = $seller_package->product_upload_limit;
            $shop->package_invalid_at = date('Y-m-d', strtotime($seller->package_invalid_at . ' +' . $seller_package->duration . 'days'));
            $res = $shop->save();
             
    
            $seller_package2 = new SellerPackagePayment;
            $seller_package2->user_id = $shop->user_id;
            $seller_package2->seller_package_id =  $package_id;
            $seller_package2->payment_method = 'wallet';
            $seller_package2->payment_details = '';
            $seller_package2->approval = 1;
            $seller_package2->offline_payment = 0;
            $seller_package2->save();
            
            
            
                 
            $payment = new Payment;
            $payment->seller_id = $user->id;
            $payment->amount =  -1* abs($amount);
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
    
    
    
    
    public function index()
    {
        $seller_packages = SellerPackage::all();
        return view('seller_packages.index', compact('seller_packages'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('seller_packages.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
     
        public function set_default(Request $request, $id)
    {
          DB::table('seller_packages')->update(['is_default'=>0]);
          $seller_package = SellerPackage::findOrFail($id);
          $seller_package->is_default = 1;
          $seller_package->save();
        
            flash(translate('successfully'))->success();
            return redirect()->route('seller_packages.index');
            
            
    }
    
    
    public function store(Request $request)
    {
        $seller_package = new SellerPackage;
        $seller_package->name = $request->name;
        $seller_package->amount = $request->amount;
        $seller_package->product_upload_limit = $request->product_upload_limit;
        $seller_package->duration = $request->duration;
        $seller_package->logo = $request->logo;
        $seller_package->max_profit =  min(255, (int)$request->max_profit);
        if ($seller_package->save()) {

            $seller_package_translation = SellerPackageTranslation::firstOrNew(['lang' => env('DEFAULT_LANGUAGE'), 'seller_package_id' => $seller_package->id]);
            $seller_package_translation->name = $request->name;
            $seller_package_translation->save();

            flash(translate('Package has been inserted successfully'))->success();
            return redirect()->route('seller_packages.index');
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
        $seller_package = SellerPackage::findOrFail($id);
        return view('seller_packages.edit', compact('seller_package', 'lang'));
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
        $seller_package = SellerPackage::findOrFail($id);
        if ($request->lang == env("DEFAULT_LANGUAGE")) {
            $seller_package->name = $request->name;
        }
        $seller_package->amount = $request->amount;
        $seller_package->product_upload_limit = $request->product_upload_limit;
        $seller_package->duration = $request->duration;
        $seller_package->max_profit = min(255, (int)$request->max_profit);
        $seller_package->logo = $request->logo;
        if ($seller_package->save()) {

            $seller_package_translation = SellerPackageTranslation::firstOrNew(['lang' => $request->lang, 'seller_package_id' => $seller_package->id]);
            $seller_package_translation->name = $request->name;
            $seller_package_translation->save();
            flash(translate('Package has been inserted successfully'))->success();
            return redirect()->route('seller_packages.index');
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
        $seller_package = SellerPackage::findOrFail($id);
        foreach ($seller_package->seller_package_translations as $key => $seller_package_translation) {
            $seller_package_translation->delete();
        }
        SellerPackage::destroy($id);
        flash(translate('Package has been deleted successfully'))->success();
        return redirect()->route('seller_packages.index');
    }


    //FrontEnd
    //@index
    public function packages_payment_list()
    {
        $seller_packages_payment = SellerPackagePayment::with('seller_package')->where('user_id', Auth::user()->id)->latest()->paginate(10);
        return view('seller_packages.frontend.packages_payment_list', compact('seller_packages_payment'));
    }

    public function seller_packages_list()
    {
        $seller_packages = SellerPackage::all();
        return view('seller_packages.frontend.seller_packages_list', compact('seller_packages'));
    }

    public function purchase_package(Request $request)
    {
        $data['seller_package_id'] = $request->seller_package_id;
        $data['payment_method'] = $request->payment_option;

        $request->session()->put('payment_type', 'seller_package_payment');
        $request->session()->put('payment_data', $data);

        $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);

        if ($seller_package->amount == 0) {
            return $this->purchase_payment_done(Session::get('payment_data'), null);
        } elseif (Auth::user()->shop->seller_package != null && $seller_package->product_upload_limit < Auth::user()->shop->seller_package->product_upload_limit) {
            flash(translate('You have more uploaded products than this package limit. You need to remove excessive products to downgrade.'))->warning();
            return back();
        }

        $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
        if (class_exists($decorator)) {
            return (new $decorator)->pay($request);
        }
    }

    public function purchase_payment_done($payment_data, $payment)
    {
        $seller = Auth::user()->shop;
        $seller->seller_package_id = Session::get('payment_data')['seller_package_id'];
        $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
        $seller->product_upload_limit = $seller_package->product_upload_limit;
        $seller->package_invalid_at = date('Y-m-d', strtotime($seller->package_invalid_at . ' +' . $seller_package->duration . 'days'));
        $seller->save();

        $seller_package = new SellerPackagePayment;
        $seller_package->user_id = Auth::user()->id;
        $seller_package->seller_package_id = Session::get('payment_data')['seller_package_id'];
        $seller_package->payment_method = Session::get('payment_data')['payment_method'];
        $seller_package->payment_details = $payment;
        $seller_package->approval = 1;
        $seller_package->offline_payment = 2;
        $seller_package->save();

        flash(translate('Package purchasing successful'))->success();
        return redirect()->route('seller.dashboard');
    }

    public function unpublish_products(Request $request)
    {
        foreach (Shop::all() as $shop) {
            if ($shop->package_invalid_at != null && Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) <= 0) {
                foreach ($shop->user->products as $product) {
                    $product->published = 0;
                    $product->save();
                }
                $shop->seller_package_id = null;
                $shop->save();
            }
        }
    }

    public function purchase_package_offline(Request $request)
    {
        $seller_package = SellerPackage::findOrFail($request->package_id);

        if (Auth::user()->shop->seller_package != null && $seller_package->product_upload_limit < Auth::user()->shop->seller_package->product_upload_limit) {
            flash(translate('You have more uploaded products than this package limit. You need to remove excessive products to downgrade.'))->warning();
            return redirect()->route('seller.seller_packages_list');
        }
        $seller_package = new SellerPackagePayment;
        $seller_package->user_id = Auth::user()->id;
        $seller_package->seller_package_id = $request->package_id;
        $seller_package->payment_method = $request->payment_option;
        $seller_package->payment_details = $request->trx_id;
        $seller_package->approval = 0;
        $seller_package->offline_payment = 1;
        $seller_package->reciept = $request->photo;
        $seller_package->save();
        flash(translate('Offline payment has been done. Please wait for response.'))->success();
        return redirect()->route('seller.products');
    }
}
