<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LatestTransactions extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return Payment::with(['customer', 'agent'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($payment) {
                return Stat::make(
                    $payment->customer?->name ?? 'Unknown Customer',
                    'K ' . number_format($payment->amount_paid, 2)
                )
                ->description($payment->created_at->diffForHumans())
                ->color($payment->payment_status_id == 1 ? 'success' : 'danger');
            })
            ->toArray();
    }
}
