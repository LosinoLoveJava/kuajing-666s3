<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\Category;
use App\Models\User;
use App\Models\Upload;
use App\Models\ProductTax;
use App\Models\AttributeValue;
use App\Models\Cart;
use Carbon\Carbon;
use Combinations;
use CoreComponentRepository;
use Artisan;
use Cache;
use Auth;
use Str;
use App\Services\ProductService;
use App\Services\ProductTaxService;
use App\Services\ProductFlashDealService;
use App\Services\ProductStockService;

class ProductController extends Controller
{
    protected $productService;
    protected $productTaxService;
    protected $productFlashDealService;
    protected $productStockService;

    public function __construct(
        ProductService $productService,
        ProductTaxService $productTaxService,
        ProductFlashDealService $productFlashDealService,
        ProductStockService $productStockService
    ) {
        $this->productService = $productService;
        $this->productTaxService = $productTaxService;
        $this->productFlashDealService = $productFlashDealService;
        $this->productStockService = $productStockService;
    }


    public function apicat( Request $request ) 
    {
        $categories = Category::get();
        $all = $categories->toArray();
        #echo "<PRE>";print_r( $all );exit;
        $all_cat = [];
        foreach( $all as $k => $v )
        {
            $ar = [];
            $ar['id'] = $v['id'];
            $ar['parent_id'] = $v['parent_id'];
            $ar['name'] = $v['name'];
            $ar['slug'] = $v['slug'];
            if( isset( $v['category_translations']))
            {
                foreach ( $v['category_translations'] as $kk => $v )
                {
                    $ar['name'.($kk+1)] = $v['name'];
                }
            }
            $all_cat[] = $ar;
        }
        echo json_encode( $all_cat);
        exit;
    }
    public function apicj( Request $request ) 
    {
        
        
        error_reporting(0);

        file_put_contents(dirname(__FILE__) . '/apicj_input2.txt', file_get_contents("php://input"));
        
         $json = file_get_contents("php://input");

        if ( empty($json) )
        {
            exit('data error');
        }

        $pr = json_decode($json, true);
        if ( $pr['key'] != get_setting('caiji_key') )
        {
            exit('Key Error');
        }
        
        
        echo 'ok';
    }
    public function api( Request $request ) {


        error_reporting(0);
        file_put_contents(dirname(__FILE__) . '/tog_post2.txt', var_export($_POST, true), FILE_APPEND);
        file_put_contents(dirname(__FILE__) . '/tog_get2.txt', var_export($_GET, true), FILE_APPEND);
        file_put_contents(dirname(__FILE__) . '/tog_input2.txt', file_get_contents("php://input"), FILE_APPEND);

        $json = file_get_contents("php://input");

        if ( empty($json) )
        {
            exit('data error');
        }

        $pr = json_decode($json, true);
        if ( $pr['key'] != get_setting('caiji_key') )
        {
            exit('Key Error');
        }


        $product = new Product;
        $product->name = $pr['name'];
        $product->added_by = 'admin';
        $product->user_id = User::where('user_type', 'admin')->first()->id;

        $product->category_id = 0;
        $product->brand_id = 0;
        $product->barcode = 0;

        $product->unit = 'pc';

        #$product->cb_price = $request->cb_price;
        #$product->xn_salenum = $request->xn_salenum;
        $product->min_qty = 1;
        $product->current_stock = 1000;
        $product->low_stock_quantity = 1;
        $product->stock_visibility_state = 1;
        $product->external_link = '';
        $product->external_link_btn = '';


        $mid = time() . mt_rand(100, 499);
        if ( isset($pr['photos']) )
        {
            foreach ( $pr['photos'] as $img )
            {

                $upload = new Upload;
                $upload->user_id = Auth::user()->id;
                $upload->file_size = 8888;
                $upload->extension = 'jpg';
                $upload->type = 'image';
                $upload->file_original_name = $upload->file_name = $img;
                $upload->mid = $mid;
                $upload->save();

                #echo $img;

            }
        }

        $thm_id = 0;
        $upload = new Upload;
        $all = $upload->where([ 'mid' => $mid ])->get()->toArray();;

        $ids = '';
        foreach ( $all as $a => $v )
        {
            if ( $a == 0 )
            {
                $thm_id = $v['id'];
            }
            $ids .= $v['id'] . ',';
        }
        $ids = rtrim($ids, ',');
    
        preg_match( '/([0-9|.]+)/',$pr['price'],$ppp );
        $pr['price'] = $ppp[1];

        $product->tags = '';
        $product->description = $pr['description'];
        $product->video_provider = '';
        $product->video_link = '';
        $product->unit_price = $pr['price'];
        $product->discount = 0;
        $product->discount_type = 'amount';


        $mid2 = time() . mt_rand(500, 999);
        $upload = new Upload;
        $upload->user_id = Auth::user()->id;
        $upload->file_size = 8888;
        $upload->extension = 'jpg';
        $upload->type = 'image';
        $upload->file_original_name = $upload->file_name = $pr['thumbnail_img'];
        $upload->mid = $mid2;
        $upload->save();

        $thm_id = $upload->where([ 'mid' => $mid2 ])->first()->id;


        $product->thumbnail_img = $thm_id;

        $product->photos = $ids;


        $product->shipping_type = 'free';
        $product->est_shipping_days = 1;


        $product->meta_title = $pr['name'];


        $product->meta_description = strip_tags($product->description);
        $product->meta_img = $product->thumbnail_img;


        $product->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', strtolower($pr['name'])));

        if ( Product::where('slug', $product->slug)->count() > 0 )
        {
            exit('Another product exists with same slug. Please change the slug!');
        }

        $colors = [];
        $product->colors = json_encode($colors);

        $choice_options = [];


        $product->attributes = json_encode([]);

        $product->choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);

        $product->published = 1;

        $product->cash_on_delivery = 0;

        //$variations = array();

        $product->save();


        // Product Translations
        $product_translation = ProductTranslation::firstOrNew([ 'lang' => env('DEFAULT_LANGUAGE'), 'product_id' => $product->id ]);
        $product_translation->name = $pr['name'];
        $product_translation->unit = 'pc';
        $product_translation->description = $pr['description'];
        $product_translation->save();


        echo 'ok';
        exit;


        echo "<PRE>";
        print_r($arr);
        exit;

    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function admin_products( Request $request ) {
        CoreComponentRepository::instantiateShopRepository();

        $type = 'In House';
        $col_name = NULL;
        $query = NULL;
        $sort_search = NULL;

        $products = Product::where('added_by', 'admin')->where('auction_product', 0)->where('wholesale_product', 0);

        if ( $request->type != NULL )
        {
            $var = explode(",", $request->type);
            $col_name = $var[0];
            $query = $var[1];
            $products = $products->orderBy($col_name, $query);
            $sort_type = $request->type;
        }
        if ( $request->search != NULL )
        {
            $sort_search = $request->search;
            $products = $products
                ->where('name', 'like', '%' . $sort_search . '%')
                ->orWhereHas('stocks', function ( $q ) use ( $sort_search )
                {
                    $q->where('sku', 'like', '%' . $sort_search . '%');
                });
        }

        $products = $products->where('digital', 0)->orderBy('created_at', 'desc')->paginate(15);

        return view('backend.product.products.index', compact('products', 'type', 'col_name', 'query', 'sort_search'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function seller_products( Request $request ) {
        $col_name = NULL;
        $query = NULL;
        $seller_id = NULL;
        $sort_search = NULL;
        $products = Product::where('added_by', 'seller')->where('auction_product', 0)->where('wholesale_product', 0);
        if ( $request->has('user_id') && $request->user_id != NULL )
        {
            $products = $products->where('user_id', $request->user_id);
            $seller_id = $request->user_id;
        }
        if ( $request->search != NULL )
        {
            $products = $products
                ->where('name', 'like', '%' . $request->search . '%');
            $sort_search = $request->search;
        }
        if ( $request->type != NULL )
        {
            $var = explode(",", $request->type);
            $col_name = $var[0];
            $query = $var[1];
            $products = $products->orderBy($col_name, $query);
            $sort_type = $request->type;
        }

        $products = $products->where('digital', 0)->orderBy('created_at', 'desc')->paginate(15);
        $type = 'Seller';

        return view('backend.product.products.index', compact('products', 'type', 'col_name', 'query', 'seller_id', 'sort_search'));
    }

    public function all_products( Request $request ) {
        $col_name = NULL;
        $query = NULL;
        $seller_id = NULL;
        $sort_search = NULL;
        $products = Product::orderBy('created_at', 'desc')->where('auction_product', 0)->where('wholesale_product', 0);
        if ( $request->has('user_id') && $request->user_id != NULL )
        {
            $products = $products->where('user_id', $request->user_id);
            $seller_id = $request->user_id;
        }
        if ( $request->search != NULL )
        {
            $sort_search = $request->search;
            $products = $products
                ->where('name', 'like', '%' . $sort_search . '%')
                ->orWhereHas('stocks', function ( $q ) use ( $sort_search )
                {
                    $q->where('sku', 'like', '%' . $sort_search . '%');
                });
        }
        if ( $request->type != NULL )
        {
            $var = explode(",", $request->type);
            $col_name = $var[0];
            $query = $var[1];
            $products = $products->orderBy($col_name, $query);
            $sort_type = $request->type;
        }

        $products = $products->paginate(15);
        $type = 'All';

        return view('backend.product.products.index', compact('products', 'type', 'col_name', 'query', 'seller_id', 'sort_search'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        CoreComponentRepository::initializeCache();

        $categories = Category::where('parent_id', 0)
            ->where('digital', 0)
            ->with('childrenCategories')
            ->get();

        return view('backend.product.products.create', compact('categories'));
    }

    public function add_more_choice_option( Request $request ) {
        $all_attribute_values = AttributeValue::with('attribute')->where('attribute_id', $request->attribute_id)->get();

        $html = '';

        foreach ( $all_attribute_values as $row )
        {
            $html .= '<option value="' . $row->value . '">' . $row->value . '</option>';
        }

        echo json_encode($html);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store( ProductRequest $request ) {
        $product = $this->productService->store($request->except([
            '_token', 'sku', 'choice', 'tax_id', 'tax', 'tax_type', 'flash_deal_id', 'flash_discount', 'flash_discount_type',
        ]));
        $request->merge([ 'product_id' => $product->id ]);

        //VAT & Tax
        if ( $request->tax_id )
        {
            $this->productTaxService->store($request->only([
                'tax_id', 'tax', 'tax_type', 'product_id',
            ]));
        }

        //Flash Deal
        $this->productFlashDealService->store($request->only([
            'flash_deal_id', 'flash_discount', 'flash_discount_type',
        ]), $product);

        //Product Stock
        $this->productStockService->store($request->only([
            'colors_active', 'colors', 'choice_no', 'unit_price', 'sku', 'current_stock', 'product_id',
        ]), $product);

        // Product Translations
        $request->merge([ 'lang' => env('DEFAULT_LANGUAGE') ]);
        ProductTranslation::create($request->only([
            'lang', 'name', 'unit', 'description', 'product_id',
        ]));

        flash(translate('Product has been inserted successfully'))->success();

        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        return redirect()->route('products.admin');
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
    public function admin_product_edit( Request $request, $id ) {
        CoreComponentRepository::initializeCache();

        $product = Product::findOrFail($id);
        if ( $product->digital == 1 )
        {
            return redirect('admin/digitalproducts/' . $id . '/edit');
        }

        $lang = $request->lang;
        $tags = json_decode($product->tags);
        $categories = Category::where('parent_id', 0)
            ->where('digital', 0)
            ->with('childrenCategories')
            ->get();
        return view('backend.product.products.edit', compact('product', 'categories', 'tags', 'lang'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function seller_product_edit( Request $request, $id ) {
        $product = Product::findOrFail($id);
        if ( $product->digital == 1 )
        {
            return redirect('digitalproducts/' . $id . '/edit');
        }
        $lang = $request->lang;
        $tags = json_decode($product->tags);
        // $categories = Category::all();
        $categories = Category::where('parent_id', 0)
            ->where('digital', 0)
            ->with('childrenCategories')
            ->get();

        return view('backend.product.products.edit', compact('product', 'categories', 'tags', 'lang'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update( ProductRequest $request, Product $product ) {
        //Product
        $product = $this->productService->update($request->except([
            '_token', 'sku', 'choice', 'tax_id', 'tax', 'tax_type', 'flash_deal_id', 'flash_discount', 'flash_discount_type',
        ]), $product);

        //Product Stock
        foreach ( $product->stocks as $key => $stock )
        {
            $stock->delete();
        }

        $request->merge([ 'product_id' => $product->id ]);
        $this->productStockService->store($request->only([
            'colors_active', 'colors', 'choice_no', 'unit_price', 'sku', 'current_stock', 'product_id',
        ]), $product);

        //Flash Deal
        $this->productFlashDealService->store($request->only([
            'flash_deal_id', 'flash_discount', 'flash_discount_type',
        ]), $product);

        //VAT & Tax
        if ( $request->tax_id )
        {
            ProductTax::where('product_id', $product->id)->delete();
            $request->merge([ 'product_id' => $product->id ]);
            $this->productTaxService->store($request->only([
                'tax_id', 'tax', 'tax_type', 'product_id',
            ]));
        }

        // Product Translations
        ProductTranslation::updateOrCreate(
            $request->only([
                'lang', 'product_id',
            ]),
            $request->only([
                'name', 'unit', 'description',
            ])
        );

        flash(translate('Product has been updated successfully'))->success();

        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy( $id ) {
        $product = Product::findOrFail($id);

        $product->product_translations()->delete();
        $product->stocks()->delete();
        $product->taxes()->delete();

        if ( Product::destroy($id) )
        {
            Cart::where('product_id', $id)->delete();

            flash(translate('Product has been deleted successfully'))->success();

            Artisan::call('view:clear');
            Artisan::call('cache:clear');

            return back();
        }
        else
        {
            flash(translate('Something went wrong'))->error();
            return back();
        }
    }

    public function bulk_product_delete( Request $request ) {
        if ( $request->id )
        {
            foreach ( $request->id as $product_id )
            {
                $this->destroy($product_id);
            }
        }

        return 1;
    }

    /**
     * Duplicates the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function duplicate( Request $request, $id ) {
        $product = Product::find($id);

        $product_new = $product->replicate();
        $product_new->slug = $product_new->slug . '-' . Str::random(5);
        $product_new->save();

        //Product Stock
        $this->productStockService->product_duplicate_store($product->stocks, $product_new);

        //VAT & Tax
        $this->productTaxService->product_duplicate_store($product->taxes, $product_new);

        flash(translate('Product has been duplicated successfully'))->success();
        if ( $request->type == 'In House' )
            return redirect()->route('products.admin');
        else if ( $request->type == 'Seller' )
            return redirect()->route('products.seller');
        else if ( $request->type == 'All' )
            return redirect()->route('products.all');
    }

    public function get_products_by_brand( Request $request ) {
        $products = Product::where('brand_id', $request->brand_id)->get();
        return view('partials.product_select', compact('products'));
    }

    public function updateTodaysDeal( Request $request ) {
        $product = Product::findOrFail($request->id);
        $product->todays_deal = $request->status;
        $product->save();
        Cache::forget('todays_deal_products');
        return 1;
    }

    public function updatePublished( Request $request ) {
        $product = Product::findOrFail($request->id);
        $product->published = $request->status;

        if ( $product->added_by == 'seller' && addon_is_activated('seller_subscription') && $request->status == 1 )
        {
            $shop = $product->user->shop;
            if (
                $shop->package_invalid_at == NULL
                || Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) < 0
                || $shop->product_upload_limit <= $shop->user->products()->where('published', 1)->count()
            )
            {
                return 0;
            }
        }

        $product->save();

        Artisan::call('view:clear');
        Artisan::call('cache:clear');
        return 1;
    }

    public function updateProductApproval( Request $request ) {
        $product = Product::findOrFail($request->id);
        $product->approved = $request->approved;

        if ( $product->added_by == 'seller' && addon_is_activated('seller_subscription') )
        {
            $shop = $product->user->shop;
            if (
                $shop->package_invalid_at == NULL
                || Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) < 0
                || $shop->product_upload_limit <= $shop->user->products()->where('published', 1)->count()
            )
            {
                return 0;
            }
        }

        $product->save();

        Artisan::call('view:clear');
        Artisan::call('cache:clear');
        return 1;
    }

    public function updateFeatured( Request $request ) {
        $product = Product::findOrFail($request->id);
        $product->featured = $request->status;
        if ( $product->save() )
        {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            return 1;
        }
        return 0;
    }

    public function sku_combination( Request $request ) {
        $options = [];
        if ( $request->has('colors_active') && $request->has('colors') && count($request->colors) > 0 )
        {
            $colors_active = 1;
            array_push($options, $request->colors);
        }
        else
        {
            $colors_active = 0;
        }

        $unit_price = $request->unit_price;
        $product_name = $request->name;

        if ( $request->has('choice_no') )
        {
            foreach ( $request->choice_no as $key => $no )
            {
                $name = 'choice_options_' . $no;
                $data = [];
                // foreach (json_decode($request[$name][0]) as $key => $item) {
                foreach ( $request[$name] as $key => $item )
                {
                    // array_push($data, $item->value);
                    array_push($data, $item);
                }
                array_push($options, $data);
            }
        }

        $combinations = Combinations::makeCombinations($options);
        return view('backend.product.products.sku_combinations', compact('combinations', 'unit_price', 'colors_active', 'product_name'));
    }

    public function sku_combination_edit( Request $request ) {
        $product = Product::findOrFail($request->id);

        $options = [];
        if ( $request->has('colors_active') && $request->has('colors') && count($request->colors) > 0 )
        {
            $colors_active = 1;
            array_push($options, $request->colors);
        }
        else
        {
            $colors_active = 0;
        }

        $product_name = $request->name;
        $unit_price = $request->unit_price;

        if ( $request->has('choice_no') )
        {
            foreach ( $request->choice_no as $key => $no )
            {
                $name = 'choice_options_' . $no;
                $data = [];
                // foreach (json_decode($request[$name][0]) as $key => $item) {
                foreach ( $request[$name] as $key => $item )
                {
                    // array_push($data, $item->value);
                    array_push($data, $item);
                }
                array_push($options, $data);
            }
        }

        $combinations = Combinations::makeCombinations($options);
        return view('backend.product.products.sku_combinations_edit', compact('combinations', 'unit_price', 'colors_active', 'product_name', 'product'));
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function depository_products( Request $request ) {
        CoreComponentRepository::instantiateShopRepository();

        $type = 'In House';
        $col_name = NULL;
        $query = NULL;
        $sort_search = NULL;

        $products = Product::where('added_by', 'admin')->where('auction_product', 0)->where('wholesale_product', 0);

        if ( $request->type != NULL )
        {
            $var = explode(",", $request->type);
            $col_name = $var[0];
            $query = $var[1];
            $products = $products->orderBy($col_name, $query);
            $sort_type = $request->type;
        }
        if ( $request->search != NULL )
        {
            $sort_search = $request->search;
            $products = $products
                ->where('name', 'like', '%' . $sort_search . '%')
                ->orWhereHas('stocks', function ( $q ) use ( $sort_search )
                {
                    $q->where('sku', 'like', '%' . $sort_search . '%');
                });
        }

        $products = $products->where('digital', 0)->orderBy('created_at', 'desc')->paginate(15);

        return view('backend.product.products.index', compact('products', 'type', 'col_name', 'query', 'sort_search'));
    }
}
