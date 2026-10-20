<?php

namespace App\Http\Controllers;

use App\Models\Product;
use CoreComponentRepository;
use Illuminate\Http\Request;

class ProductStorehouseController extends Controller
{
    public function index(Request $request)
    {
        CoreComponentRepository::instantiateShopRepository();

        $type = 'In House';
        $col_name = null;
        $query = null;
        $sort_search = null;

        $products = Product::where('in_storehouse', 1)->where('added_by', 'admin')->where('auction_product', 0)->where('wholesale_product', 0);

        if ($request->type != null) {
            $var = explode(",", $request->type);
            $col_name = $var[0];
            $query = $var[1];
            $products = $products->orderBy($col_name, $query);
            $sort_type = $request->type;
        }
        if ($request->search != null) {
            $sort_search = $request->search;
            $products = $products
                ->where('name', 'like', '%' . $sort_search . '%')
                ->orWhereHas('stocks', function ($q) use ($sort_search) {
                    $q->where('sku', 'like', '%' . $sort_search . '%');
                });
        }

        $products = $products->where('digital', 0)->orderBy('created_at', 'desc')->paginate(15);
        return view('backend.product_storehouse.index', compact('products', 'type', 'col_name', 'query', 'sort_search'));
    }

    public function product_storehouse_update(Request $request)
    {
        if ($request->id) {
            Product::where('added_by', 'admin')->where('id', $request->id)->update(['in_storehouse' => $request->in_storehouse]);
            return 1;
        }
        return 0;
    }

    public function product_storehouse_move_out($id)
    {
        if ($id) {
            Product::where('added_by', 'admin')->where('id', $id)->update(['in_storehouse' => 0]);
        }
        return back();
    }

    public function product_storehouse_order_free_up($id)
    {
        if ($id) {
            product_storehouse_order_free_up($id);
        }
        return back();
    }

    public function bulk_product_storehouse_add(Request $request)
    {
        if ($request->id) {
            Product::where('added_by', 'admin')->whereIn('id', $request->id)->update(['in_storehouse' => 1]);
            return 1;
        }
        return 0;
    }

    public function bulk_product_storehouse_remove(Request $request)
    {
        if ($request->id) {
            Product::where('added_by', 'admin')->whereIn('id', $request->id)->update(['in_storehouse' => 0]);
            return 1;
        }
        return 0;
    }
}
