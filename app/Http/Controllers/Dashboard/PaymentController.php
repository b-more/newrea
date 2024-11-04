<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['customer', 'agent'])
            ->latest();

        // Apply filters
        if ($request->has('reference')) {
            $query->where('payment_reference_number', 'like', '%' . $request->reference . '%');
        }

        if ($request->has('status')) {
            $query->where('payment_status_id', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate(10);

        return view('dashboard.payments.index', compact('payments'));
    }

    public function show(Payment $payment)
    {
        $payment->load(['customer', 'agent']);
        return view('dashboard.payments.show', compact('payment'));
    }
}
