<?php

namespace App\Http\Controllers\Seller;

use App\Http\Resources\PosProductCollection;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Review;
use App\Models\Seller;
use App\Services\ProductStockService;
use App\Services\ProductTaxService;
use App\Utility\CategoryUtility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function back;
use function collect;
use function count;
use function dd;
use function flash;
use function translate;

class ProductStorehouseController extends Controller
{
    protected $productTaxService;
    protected $productStockService;

    public function __construct(
        ProductTaxService   $productTaxService,
        ProductStockService $productStockService
    )
    {
        $this->productTaxService = $productTaxService;
        $this->productStockService = $productStockService;
    }

    public function index()
    {
        return view('seller.product_storehouse.index');
    }

    public function searchProduct(Request $request)
    {
        // 排除已复制产品
        $alreadyCopyIds = Product::query()
            ->where('user_id', Auth::user()->id)
            ->where('published', 1)
            ->whereNotNull('original_id')
            ->pluck('original_id')
            ->toArray();

        $products = ProductStock::join('products', 'product_stocks.product_id', '=', 'products.id')
            ->where('products.in_storehouse', 1)
            ->whereNotIn('products.id', $alreadyCopyIds)
            ->select('products.*', 'product_stocks.id as stock_id', 'product_stocks.variant', 'product_stocks.price as stock_price', 'product_stocks.qty as stock_qty', 'product_stocks.image as stock_image')
            ->orderBy('products.created_at', 'desc');


        if ($request->category != null) {
            $arr = explode('-', $request->category);
            if ($arr[0] == 'category') {
                $category_ids = CategoryUtility::children_ids($arr[1]);
                $category_ids[] = $arr[1];
                $products = $products->whereIn('products.category_id', $category_ids);
            }
        }

        if ($request->brand != null) {
            $products = $products->where('products.brand_id', $request->brand);
        }

        if ($request->keyword != null) {
            $products = $products->where('products.name', 'like', '%' . $request->keyword . '%')->orWhere('products.barcode', $request->keyword);
        }

        $paginate = $products->paginate(16);

        $stocks = new PosProductCollection($products->paginate(16));
        $stocks->appends(['keyword' => $request->keyword, 'category' => $request->category, 'brand' => $request->brand]);
        return $stocks;
    }

    public function addProduct(Request $request)
    {
        $userId = Auth::user()->id;
        if (!$request->all && !$request->product_ids) return response()->json(['success' => 0, 'message' => translate('Please select a product')]);

        // 排除已复制产品
        $alreadyCopyIds = Product::query()
            ->where('user_id', $userId)
            ->whereNotNull('original_id')
            ->pluck('original_id')
            ->toArray();

        if ($request->all) {
            $productIds = Product::query()
                ->where('added_by', 'admin')
                ->where('in_storehouse', 1)
                ->pluck('id')
                ->toArray();

        } else {
            $productIds = $request->product_ids;
        }

        // 排除已复制产品ID
        $productIds = array_filter($productIds, function ($v) use ($alreadyCopyIds) {
            return !in_array($v, $alreadyCopyIds);
        }, ARRAY_FILTER_USE_BOTH);
        $shop = Auth::user()->shop;
        if ($shop->verification_status==0) return response()->json(['success' => 0, 'message' => translate('Shop under review.')]);
        if (
            $shop->product_upload_limit > ($shop->user->products()->count() + count($productIds))
            && $shop->package_invalid_at != null
            && Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) >= 0
        ) {

        }else{
            return response()->json(['success' => 0, 'message' => translate('Please upgrade your package.')]);
        }
        try {
            DB::beginTransaction();
            // 获取最大利润
            $sellerPackage = Auth::user()->shop->seller_package;
            if (!$sellerPackage) {
                return response()->json(['success' => 0, 'message' => translate('Please upgrade your package.')]);
            }
            $maxProfit = $sellerPackage->max_profit / 100;

            // 循环复制产品
            foreach ($productIds as $productId) {
                $product = Product::find($productId);
                $profitPrice = $product->unit_price * $maxProfit;

                $product_new = $product->replicate();
                $product_new->slug = $product_new->slug . '-' . Str::random(5);
                $product_new->added_by = 'seller';
                $product_new->user_id = $userId;
                $product_new->unit_price = $product->unit_price + $profitPrice;
                $product_new->original_id = $productId;
                $product_new->published = 1;
                $product_new->in_storehouse = 0;
                $product_new->save();
                /*店铺铺货 评论权限*/
                if ($shop->comment_permission == 1){
                    foreach ( $product->reviews as $review )
                    {
                        $reviewModel = new Review;
                        $reviewModel->product_id = $product_new->id;
                        $reviewModel->user_id = $review->user_id;
                        $reviewModel->rating = $review->rating;
                        $reviewModel->comment = $review->comment;
                        $reviewModel->viewed = '0';
                        $reviewModel->save();
                    }
                }


                // 循环复制产品翻译
                foreach ($product->product_translations as $productTranslation) {
                    $productTranslationNew = $productTranslation->replicate();
                    $productTranslationNew->product_id = $product_new->id;
                    $productTranslationNew->save();
                }

                //Product Stock
                $this->productStockService->product_duplicate_store($product->stocks, $product_new, $profitPrice);

                //VAT & Tax
                $this->productTaxService->product_duplicate_store($product->taxes, $product_new);
            }

            DB::commit();
            return response()->json(['success' => 1]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => 0]);
        }
    }
}
