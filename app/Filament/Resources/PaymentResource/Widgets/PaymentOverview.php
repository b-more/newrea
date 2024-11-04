<?php

namespace App\Filament\Resources\PaymentResource\Widgets;

use App\Models\Payment;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Filament\Widgets\ChartWidget;

class PaymentOverview extends ChartWidget
{
    protected static ?string $heading = 'Chart';

    protected function getData(): array
    {
        // Totals per month
        $trend = Trend::model(Payment::class)
        ->between(
            start: now()->startOfMonth(),
            end: now()->endOfMonth(),
        )
        ->perDay()
        ->count();

        // Average user weight where name starts with a over a span of 11 years, results are grouped per year
        $trend = Trend::query(Payment::where('name', 'like', 'a%'))
        ->between(
            start: now()->startOfYear()->subYears(10),
            end: now()->endOfYear(),
        )
        ->perYear()
        ->average('weight');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
