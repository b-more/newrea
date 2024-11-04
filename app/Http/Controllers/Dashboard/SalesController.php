<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['agent', 'customer'])
            ->where('payment_status_id', 1)
            ->where('agent_id', '!=', null);

        // Apply filters
        if ($request->has('agent')) {
            $query->where('agent_id', $request->agent);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get sales summary
        $summary = $query->select([
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(amount_paid) as total_amount'),
            DB::raw('AVG(amount_paid) as average_amount')
        ])->first();

        $sales = $query->latest()->paginate(10);
        $agents = Agent::all();

        return view('dashboard.sales.index', compact('sales', 'agents', 'summary'));
    }
}
