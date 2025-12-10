<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function counts()
    {
        return response()->json([
            'invoices' => Invoice::count(),
            'customers' => Customer::count(),
            'items' => Item::count(),
            'total_earnings' => Invoice::sum(DB::raw('COALESCE(total, 0)')),
        ]);
    }
}
