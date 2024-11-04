<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\FloatTransaction;
use App\Models\Agent;
use Illuminate\Http\Request;

class FloatController extends Controller
{
    public function index(Request $request)
    {
        $query = FloatTransaction::with('agent')
            ->latest();

        // Apply filters
        if ($request->has('agent')) {
            $query->where('agent_id', $request->agent);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $floatTransactions = $query->paginate(10);
        $agents = Agent::all();

        return view('dashboard.float.index', compact('floatTransactions', 'agents'));
    }

    public function show(FloatTransaction $floatTransaction)
    {
        $floatTransaction->load('agent');
        return view('dashboard.float.show', compact('floatTransaction'));
    }
}
