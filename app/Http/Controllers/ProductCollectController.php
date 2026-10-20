<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeTranslation;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\BrandTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Review;
use App\Models\Upload;
use App\Models\User;
use App\Services\ProductFlashDealService;
use App\Services\ProductService;
use App\Services\ProductStockService;
use App\Services\ProductTaxService;
use App\Utility\ProductUtility;
use Auth;
use Combinations;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use function count;
use function curl_close;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function dd;
use function explode;
use function json_decode;
use function json_encode;
use function print_r;
use function rand;
use function set_time_limit;
use function single_price;
use function str_replace;
use function var_dump;
use function var_export;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_URL;

class ProductCollectController extends Controller
{
    protected $productService;
    protected $productTaxService;
    protected $productFlashDealService;
    protected $productStockService;

    protected $key = '';
    protected $secret = '';

    protected $r
        = [
            'co.id'  => 0.01,
            'com.my' => 1,
            'com.ph' => 1,
            'sg'     => 1,
            'co.th'  => 1,
            'vn'     => 1,
        ];

    public function __construct(
        ProductService $productService,
        ProductTaxService $productTaxService,
        ProductFlashDealService $productFlashDealService,
        ProductStockService $productStockService
    ) {
        $this->key                     = get_setting('wanbang_key');
        $this->secret                  = get_setting('wanbang_secret');
        $this->productService          = $productService;
        $this->productTaxService       = $productTaxService;
        $this->productFlashDealService = $productFlashDealService;
        $this->productStockService     = $productStockService;
    }

    public function index() {
        $products  = [];
        $categorys = json_encode(collect(Category::where('id', '>', 0)->get()));

        return view('backend.product_collect.index', compact('products', 'categorys'));
    }

    public function ids() {
        $data   = json_decode(file_get_contents(dirname(__FILE__) . '/apicj_input2.txt'), true);
        $params = [];
        $list   = $data['data'];
        $type   = "lazada";
        foreach ( $list as $item )
        {
            $url    = parse_url($item);
            $id     = str_replace('.html', '', str_replace('/products/', '', $url['path']));
            $array  = explode('-', $id);
            $host   = explode('.', $url['host']);
            $nation = $host[count($host) - 2] . '.' . $host[count($host) - 1];
            $newId  = str_replace('i', '', $array[count($array) - 1]);
            if ( $url['host'] == 'www.alibaba.com' )
            {
                $type  = "alibaba";
                $newId = explode('_', $newId)[count(explode('_', $newId)) - 1];
            }
            $params[] = [
                'id'      => $newId,
                'nation'  => $nation,
                'url'     => $item,
                'name'    => '',
                'pic_url' => '',
                'source'  => '',
                'rating'  => '',
                'price'   => '',
                'stock'   => '',
            ];
        }
        $categorys = Category::where('id', '>', 0)->get();
        return response()->json(compact('params', 'categorys', 'type'), 200);
    }

    public function data( Request $request ) {
        set_time_limit(0);
        $page   = (int)$request->page;
        $limit  = (int)$request->limit;
        $type   = $request->type ?? 'lazada';
        $data   = json_decode(file_get_contents(dirname(__FILE__) . '/apicj_input2.txt'), true);
        $params = [];
        $list   = $data['data'];
        $exits  = Product::where('digital', 0)->where('in_storehouse', 1)->where('source', $type)->where('added_by', 'admin')->get();
        $has    = array_column(collect($exits)->toArray(), 'barcode');
//        print_r($has);dd();
        foreach ( $list as $item )
        {
            $url    = parse_url($item);
            $id     = str_replace('.html', '', str_replace('/products/', '', $url['path']));
            $array  = explode('-', $id);
            $host   = explode('.', $url['host']);
            $nation = $host[count($host) - 2] . '.' . $host[count($host) - 1];
            $newId  = str_replace('i', '', $array[count($array) - 1]);
            if ( $type == 'alibaba' ) $newId = explode('_', $newId)[count(explode('_', $newId)) - 1];
            if ( !in_array($id, $has) )
            {
                $na = str_replace('lazada.', '', $nation);
                if ( $type == 'alibaba' ) $na = 'alibaba';
                $params[] = [
                    'id'     => $newId,
                    'nation' => $na,
                    'url'    => $item,
                ];
            }
        }
        $products = [];
        $count    = count($params);
        $pages    = ceil($count / $limit);
        if ( $page < 1 ) $page = 1;
        foreach ( $params as $key => $param )
        {
            $key = $key + 1;
            if ( $page == 1 )
            {
                if ( $key <= $page * $limit )
                {
                    $res = $this->curlGet($param['id'], $param['nation'], $type=='alibaba'?'alibaba':'lazada');
                    if ( $res )
                    {
                        if ( $res['error_code'] == '0000' )
                        {
                            $item       = $res['item'];
                            $products[] = [
                                'id'      => $item['num_iid'],
                                'name'    => $item['title'],
                                'pic_url' => $item['pic_url'],
                                'source'  => $res['api_type'],
                                'rating'  => 5,
                                'price'   => $type=='alibaba'?$item['price']:single_price($item['price'] * ( isset($this->r[$param['nation']]) ? $this->r[$param['nation']] : 1 )),
                                'stock'   => rand(1000, 5000),
                                'url'     => $param['url'],
                                'comment' => false,
                            ];
                        }
                    }
                }
            }
            else if ( $page > 1 )
            {
                if ( $key > ( $page - 1 ) * $limit && $key <= $page * $limit )
                {
//                    $res = $this->curlGet($param['id'], $param['nation']);
                    $res = $this->curlGet($param['id'], $param['nation'], $type=='alibaba'?'alibaba':'lazada');
                    if ( $res )
                    {
                        if ( $res['error_code'] == '0000' )
                        {
                            $item       = $res['item'];
                            $products[] = [
                                'id'      => $item['num_iid'],
                                'name'    => $item['title'],
                                'pic_url' => $item['pic_url'],
                                'source'  => $res['api_type'],
                                'rating'  => 5,
                                'price'   => $type=='alibaba'?$item['price']:single_price($item['price'] * ( isset($this->r[$param['nation']]) ? $this->r[$param['nation']] : 1 )),
                                'stock'   => rand(1000, 5000),
                                'url'     => $param['url'],
                                'comment' => false,
                            ];
                        }
                    }
                }
            }
        }
        return response()->json(compact('products', 'pages', 'count', 'page', 'limit', 'type'), 200);
    }

    //产品开始采集入库
    public function bulk_product_collect_add( Request $request ) {
        set_time_limit(0);
        $ids         = $request->ids;
        $category_id = $request->category_id;
        $type   = $request->type ?? 'lazada';
        $category    = Category::findOrFail($category_id);
        $url         = array_column($ids, 'url');
        $ids         = array_column($ids, NULL, 'id');
        $data        = json_decode(file_get_contents(dirname(__FILE__) . '/apicj_input2.txt'), true);
        $params      = [];
        $list        = array_unique(array_intersect($url, $data['data']));
        $exits       = Product::where('digital', 0)->where('in_storehouse', 1)->where('source', $type)->where('added_by', 'admin')->get();
        $has         = array_column(collect($exits)->toArray(), 'barcode');
        foreach ( $list as $item )
        {
            $url    = parse_url($item);
            $id     = str_replace('.html', '', str_replace('/products/', '', $url['path']));
            $array  = explode('-', $id);
            $host   = explode('.', $url['host']);
            $nation = $host[count($host) - 2] . '.' . $host[count($host) - 1];
            $newId  = str_replace('i', '', $array[count($array) - 1]);
            if ( $type == 'alibaba' ) $newId = explode('_', $newId)[count(explode('_', $newId)) - 1];
            if ( !in_array($newId, $has) )
            {
                $na = str_replace('lazada.', '', $nation);
                if ( $type == 'alibaba.com' ) $na = 'alibaba';
                $params[] = [
                    'id'     => $newId,
                    'nation' => $na,
                ];
            }
        }
        foreach ( $params as $key => $param )
        {
            $res = $this->curlGet($param['id'], $param['nation'],$type=='alibaba'?'alibaba':'lazada');

            if ( $res )
            {
                if ( $res['error_code'] == '0000' )
                {
                    DB::beginTransaction();
                    $slug            = Str::slug($res['item']['name']);
                    $same_slug_count = Product::where('slug', 'LIKE', $slug . '%')->count();
                    $slug_suffix     = $same_slug_count ? '-' . $same_slug_count + 1 : '';
                    $slug            .= $slug_suffix;
                    $productData     = [
                        'added_by'               => 'admin',
                        'name'                   => $res['item']['title'],
                        'category_id'            => $category->id,
                        'brand_id'               => '0',
                        'unit'                   => 'Pc',
                        'min_qty'                => '1',
                        'tags'                   => '',
                        'barcode'                => $res['item']['num_iid'],
                        'refundable'             => '1',
                        'photos'                 => '', //570,569
                        'thumbnail_img'          => '', //570
                        'video_provider'         => 'youtube',
                        'video_link'             => NULL,
//                        'unit_price'             => $res['item']['price'] * ( isset($this->r[$param['nation']]) ? $this->r[$param['nation']] : 1 ),
                        'unit_price'             => $type=='alibaba'?str_replace('$', '', $res['item']['price']):$res['item']['price'] * ( isset($this->r[$param['nation']]) ? $this->r[$param['nation']] : 1 ),
                        'discount'               => '0',
                        'discount_type'          => 'amount',
                        'earn_point'             => '0',
                        'current_stock'          => rand(1000, 5000),
                        'external_link'          => NULL,
                        'external_link_btn'      => NULL,
                        'description'            => $res['item']['desc'],
                        'pdf'                    => NULL,
                        'meta_title'             => $type=='alibaba'?$res['item']['title']:$res['item']['name'],
                        'meta_description'       => $type=='alibaba'?$res['item']['desc_short']:$res['item']['name'],
                        'meta_img'               => '570',
                        'shipping_type'          => $res['item']['post_fee'] ? 'flat_rate' : 'free',
                        'low_stock_quantity'     => '1',
                        'stock_visibility_state' => 'quantity',
                        'cash_on_delivery'       => '1',
                        'est_shipping_days'      => '3',
                        'user_id'                => Auth::user()->id,
                        'approved'               => 1,
                        'discount_start_date'    => NULL,
                        'discount_end_date'      => NULL,
                        'shipping_cost'          => $res['item']['post_fee'] ? $res['item']['post_fee'] : 0,
                        'slug'                   => $res['item']['num_iid'],
                        'choice_attributes'      => [],
                        'colors'                 => '[]',//'colors' => '["#7FFFD4","#A9A9A9"]',
                        'choice_options'         => '[]',//{"attribute_id":"2","values":["Aaaa","Ssssss"]}
                        'attributes'             => '[]',//转成JSON "2"
                        'published'              => 0,
                        'in_storehouse'          => 1,
                        'digital'                => 0,
                        'source'                => $type=='alibaba'?'alibaba':'lazada',
                    ];
                    //thumbnail_img图片下载
                    $dir    = 'download' . parse_url($res['item']['pic_url'])['path'];
                    $mid    = time() . mt_rand(100, 499);
                    $client = new \GuzzleHttp\Client();
                    $data   = $client->request('get', $res['item']['pic_url'])->getBody()->getContents();
                    Storage::disk('local')->put($dir, $data);
                    $upload                     = new Upload;
                    $upload->user_id            = Auth::user()->id;
                    $upload->file_size          = 10240000;
                    $upload->extension          = 'jpg';
                    $upload->type               = 'image';
                    $upload->file_original_name = $res['item']['pic_url'];
                    $upload->file_name          = $dir;
                    $upload->mid                = $mid;
                    $upload->save();
                    $productData['thumbnail_img'] = $upload->id;

                    //photos下载
                    $phones    = [];
                    $midPhotos = time() . mt_rand(100, 499);
                    if ( $res['item']['props_img'] )
                    {
                        foreach ( $res['item']['props_img'] as $item )
                        {
                            $dir    = 'download' . parse_url($item)['path'];
                            $client = new \GuzzleHttp\Client();
                            $data   = $client->request('get', $item)->getBody()->getContents();
                            Storage::disk('local')->put($dir, $data);
                            $upload                     = new Upload;
                            $upload->user_id            = Auth::user()->id;
                            $upload->file_size          = 10240000;
                            $upload->extension          = 'jpg';
                            $upload->type               = 'image';
                            $upload->file_original_name = $item;
                            $upload->mid                = $midPhotos;
                            $upload->file_name          = $dir;
                            $upload->save();
                            $phones[] = $upload->id;
                        }
                    }
                    if ( $res['item']['item_imgs'] )
                    {
                        foreach ( $res['item']['item_imgs'] as $item )
                        {
                            $dir    = 'download' . parse_url($item['url'])['path'];
                            $client = new \GuzzleHttp\Client();
                            $data   = $client->request('get', $item['url'])->getBody()->getContents();
                            Storage::disk('local')->put($dir, $data);
                            $upload                     = new Upload;
                            $upload->user_id            = Auth::user()->id;
                            $upload->file_size          = 10240000;
                            $upload->extension          = 'jpg';
                            $upload->type               = 'image';
                            $upload->file_original_name = $item['url'];
                            $upload->mid                = $midPhotos;
                            $upload->file_name          = $dir;
                            $upload->save();
                            $phones[] = $upload->id;
                        }
                    }
                    $productData['photos'] = implode(',', $phones);

                    //判断品牌
                    if ($res['item']['brand']){
                        $brand = Brand::where('name', $res['item']['brand'])->first();
                        if ( $brand != NULL )
                        {
                            $productData['brand_id'] = $brand->id;
                        }
                        else
                        {
                            $brand                   = new Brand;
                            $brand->name             = $res['item']['brand'];
                            $brand->meta_title       = $res['item']['brand'];
                            $brand->meta_description = $item['brand'];
                            $brand->slug             = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $res['item']['brand'])) . '-' . Str::random(5);
                            $brand->logo             = '';
                            $brand->save();
                            $brand_translation       = BrandTranslation::firstOrNew([
                                'lang'     => env('DEFAULT_LANGUAGE'),
                                'brand_id' => $brand->id,
                            ]);
                            $brand_translation->name = $res['item']['brand'];
                            $brand_translation->save();
                            $productData['brand_id'] = $brand->id;
                        }
                    }



                    $product = Product::create($productData);

                    //属性计算

                    $choice_attributes = $choice_options = [];
                    if ( $res['item']['props_list'] )
                    {
                        foreach ( $res['item']['props_list'] as $key => $re )
                        {
                            $item            = explode(':', $re);
                            $attribute       = Attribute::where('name', $item[0])->firstOrNew();
                            $attribute->name = $item[0];
                            $attribute->save();
                            $attribute_translation       = AttributeTranslation::firstOrNew([
                                'lang'         => env('DEFAULT_LANGUAGE'),
                                'attribute_id' => $attribute->id,
                            ]);
                            $attribute_translation->name = $attribute->name;
                            $attribute_translation->save();
                            $attribute_value               = AttributeValue::where('attribute_id', $attribute->id)->where('value', $item[1])->firstOrNew();
                            $attribute_value->attribute_id = $attribute->id;
                            $attribute_value->value        = $item[1];
                            $attribute_value->save();
                            $choice_attributes[$attribute->id]              = $attribute->id;
                            $choice_options[$attribute->id]['attribute_id'] = $attribute->id;
                            $choice_options[$attribute->id]['values'][$key] = $attribute_value->value;
                        }
                    }
                    $data = [
                        'colors_active' => '0',
                        'choice_no'     => array_values($choice_attributes),
                        'unit_price'             => $type=='alibaba'?str_replace('$', '', $res['item']['price']):$res['item']['price'] * ( isset($this->r[$param['nation']]) ? $this->r[$param['nation']] : 1 ),
                        'sku'           => NULL,
                        'current_stock' => rand(1000, 5000),
                        'product_id'    => $product->id,
                    ];

                    $collection = collect($data);
                    $options    = ProductUtility::get_attribute_options($collection);
                    //Generates the combinations of customer choice options
                    $combinations = Combinations::makeCombinations($options);
                    $variant      = '';
                    if ( count($combinations[0]) > 0 )
                    {
                        $product->variant_product = 1;
                        $product->save();
                        foreach ( $combinations as $key => $combination )
                        {
                            $str                       = ProductUtility::get_combination_string($combination, $collection);
                            $product_stock             = new ProductStock();
                            $product_stock->product_id = $product->id;
                            $product_stock->variant    = $str;
//                            $product_stock->price      = $res['item']['price'] * ( isset($this->r[$param['nation']]) ? $this->r[$param['nation']] : 1 );
                            $product_stock->price      =$type=='alibaba'?str_replace('$', '', $res['item']['price']):single_price($res['item']['price'] * ( isset($this->r[$param['nation']]) ? $this->r[$param['nation']] : 1 ));
                            $product_stock->sku        = '';
                            $product_stock->qty        = rand(1000, 5000);
                            $product_stock->image      = $product->thumbnail_img;
                            $product_stock->save();
                        }
                    }
                    else
                    {
                        unset($collection['colors_active'], $collection['colors'], $collection['choice_no']);
                        $qty   = $collection['current_stock'];
                        $price = $collection['unit_price'];
                        unset($collection['current_stock']);
                        $data = $collection->merge(compact('variant', 'qty', 'price'))->toArray();
                        ProductStock::create($data);
                    }

                    //开始检测是否开启评论采集
                    if ( isset($ids[$param['id']]) && $type=='lazada')
                    {
                        if ( $ids[$param['id']]['comment'] == '1' )
                        {
                            $cRes = $this->curlGetCom($param['id'], $param['nation']);
//                            dd($cRes);
                            if ( $cRes )
                            {
                                if ( $cRes['error_code'] == '0000' )
                                {
                                    $items = $cRes['items']['item'];//评论列表
                                    foreach ( $items as $item )
                                    {
                                        //添加用户信息
                                        $addUser                    = User::where('name', $item['display_user_nick'])->where('avatar', $item['head_pic'])->firstOrNew();
                                        $addUser->name              = $item['display_user_nick'];
                                        $addUser->avatar            = $item['head_pic'];
                                        $addUser->password          = '$2y$10$cCzxgRHlITBgIrTHdzys8.TqtwV42.Fs2PakWtfHw0wnOt0obs/ai';
                                        $addUser->email_verified_at = date("Y-m-d H:i:s");
                                        $addUser->user_type         = 'customer';
                                        $addUser->save();
                                        $addUser->email = $addUser->id . rand(111111, 999999) . "@qq.com";

                                        //开始添加评论
                                        if ( $item['rate_content'] )
                                        {
                                            $review             = new Review;
                                            $review->product_id = $product->id;
                                            $review->user_id    = $addUser->id;
                                            $review->rating     = $item['rating'];
                                            $review->comment    = $item['rate_content'];
                                            $review->viewed     = '0';
                                            $review->save();
                                        }
                                        if ( Review::where('product_id', $product->id)->where('status', 1)->count() > 0 )
                                        {
                                            $product->rating = Review::where('product_id', $product->id)->where('status', 1)->sum('rating') / Review::where('product_id', $product->id)->where('status', 1)->count();
                                        }
                                        else
                                        {
                                            $product->rating = 0;
                                        }
                                        $product->save();
                                    }
                                }
                            }
                        }
                    }

                    DB::commit();
                }
            }
        }
        return 1;
    }


    public function product_collect_update( Request $request ) {
        if ( $request->id )
        {
            Product::where('added_by', 'admin')->where('id', $request->id)->update([ 'in_collect' => $request->in_collect ]);
            return 1;
        }
        return 0;
    }

    public function product_collect_move_out( $id ) {
        if ( $id )
        {
            Product::where('added_by', 'admin')->where('id', $id)->update([ 'in_collect' => 0 ]);
        }
        return back();
    }

    public function product_collect_order_free_up( $id ) {
        if ( $id )
        {
            product_collect_order_free_up($id);
        }
        return back();
    }


    public function bulk_product_collect_remove( Request $request ) {
        if ( $request->id )
        {
            Product::where('added_by', 'admin')->whereIn('id', $request->id)->update([ 'in_collect' => 0 ]);
            return 1;
        }
        return 0;
    }


    protected function curlGet( $productId, $nation, $type = 'lazada' ) {
        $hosts = [
            'https://api-gw.onebound.cn',
            'https://api-1.onebound.cn',
            'https://api-2.onebound.cn',
            'https://api-3.onebound.cn',
            'https://api-4.onebound.cn',
        ];
        foreach ( $hosts as $host )
        {
            // 请求示例 url 默认请求参数已经URL编码处理
            $url         = $host . "/lazada/item_get/?key=" . $this->key . "&nation=" . $nation . "&secret=" . $this->secret . "&num_iid=" . $productId;
            if ($type=='alibaba')$url         = $host . "/alibaba/item_get/?key=" . $this->key . "&secret=" . $this->secret . "&num_iid=" . $productId;
            $headerArray = [ "Content-type:application/json;charset=UTF-8", "Accept:application/json" ];
            $ch          = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
            $output = curl_exec($ch);
            curl_close($ch);
            $output = json_decode($output, true);

            if ( isset($output['error_code']) )
            {
                if ( $output['error_code'] == '0000' )
                {
                    return $output;
                }
            }
        }
    }

    protected function curlGetCom( $productId, $nation ) {
        $hosts = [
            'https://api-gw.onebound.cn',
            'https://api-1.onebound.cn',
            'https://api-2.onebound.cn',
            'https://api-3.onebound.cn',
            'https://api-4.onebound.cn',
        ];
        foreach ( $hosts as $host )
        {
            // 请求示例 url 默认请求参数已经URL编码处理
            $url         = $host . "/lazada/item_review/?key=" . $this->key . "&nation=" . $nation . "&secret=" . $this->secret . "&page=1&num_iid=" . $productId;
            $headerArray = [ "Content-type:application/json;charset=UTF-8", "Accept:application/json" ];
            $ch          = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
            $output = curl_exec($ch);
            curl_close($ch);
            $output = json_decode($output, true);

            if ( isset($output['error_code']) )
            {
                if ( $output['error_code'] == '0000' )
                {
                    return $output;
                }
            }
        }
        return '';
    }

}
