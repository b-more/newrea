<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class PaymentsChart extends ChartWidget
{
    protected static ?string $heading = 'Payment Transactions';

    protected function getData(): array
    {
        $data = Trend::model(Payment::class)
        ->between(
            start: now()->startOfMonth(),
            end: now()->endOfMonth(),
        )
        ->perDay()
        ->count();
 
    return [
        'datasets' => [
            [
                'label' => 'Payment Transactions',
                'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
            ],
        ],
        'labels' => $data->map(fn (TrendValue $value) => $value->date),
    ];

       
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
