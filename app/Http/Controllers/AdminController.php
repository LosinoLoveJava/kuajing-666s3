<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Artisan;
use Cache;
use CoreComponentRepository;

class AdminController extends Controller
{
    /**
     * Show the admin dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function admin_dashboard(Request $request)
    {   
        CoreComponentRepository::initializeCache();
        $root_categories = Category::where('level', 0)->get();

        $graph_cache_key = 'admin_dashboard_graph_v2_'.sha1($root_categories->pluck('id')->implode(','));
        $cached_graph_data = Cache::remember($graph_cache_key, 86400, function() use ($root_categories){
            // Only category totals enter PHP, regardless of catalogue size. Aggregate
            // sales separately so products with multiple stock variants count once.
            $children = DB::table('categories')->get(['id', 'parent_id'])->groupBy('parent_id');
            $sales = DB::table('products')->groupBy('category_id')
                ->selectRaw('category_id, SUM(num_of_sale) AS total')->pluck('total', 'category_id');
            $stock = DB::table('product_stocks')
                ->join('products', 'products.id', '=', 'product_stocks.product_id')
                ->groupBy('products.category_id')
                ->selectRaw('products.category_id, SUM(product_stocks.qty) AS total')->pluck('total', 'category_id');
            $item = ['num_of_sale_data' => '', 'qty_data' => ''];
            foreach ($root_categories as $category) {
                $pending = [$category->id];
                $visited = [];
                $sale = 0;
                $qty = 0;
                while ($pending) {
                    $id = array_pop($pending);
                    if (isset($visited[$id])) {
                        continue;
                    }
                    $visited[$id] = true;
                    $sale += $sales->get($id, 0);
                    $qty += $stock->get($id, 0);
                    foreach ($children->get($id, []) as $child) {
                        $pending[] = $child->id;
                    }
                }
                $item['num_of_sale_data'] .= $sale.',';
                $item['qty_data'] .= $qty.',';
            }
            return $item;
        });

        return view('backend.dashboard', compact('root_categories', 'cached_graph_data'));
    }

    function clearCache(Request $request)
    {
        Artisan::call('cache:clear');
        flash(translate('Cache cleared successfully'))->success();
        return back();
    }
}
