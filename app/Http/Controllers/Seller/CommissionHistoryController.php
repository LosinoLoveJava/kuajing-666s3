<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Http\Request;
use App\Models\CommissionHistory;
use App\Support\ReportDateRange;
use Auth;

class CommissionHistoryController extends Controller
{
    public function index(Request $request) {
        $seller_id = null;
        $date_range = null;
        
        $commission_history = CommissionHistory::with('order')->where('seller_id', Auth::user()->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        
        if ($request->date_range) {
            $date_range = $request->date_range;
        }
        ReportDateRange::apply($commission_history, $request->input('date_range'));
        
        $commission_history = $commission_history->paginate(10)->withQueryString();
        return view('seller.commission_history.index', compact('commission_history', 'seller_id', 'date_range'));
    }
}
