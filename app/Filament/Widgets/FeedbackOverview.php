<?php

// File: app/Filament/Resources/CustomerFeedbackResource/Widgets/FeedbackOverview.php

namespace App\Filament\Resources\CustomerFeedbackResource\Widgets;

use App\Models\CustomerFeedback;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FeedbackOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $total = CustomerFeedback::count();
        $pending = CustomerFeedback::where('status', 'pending')->count();
        $resolved = CustomerFeedback::where('status', 'resolved')->count();
        $inProgress = CustomerFeedback::where('status', 'in_progress')->count();

        // Calculate trends
        $previousTotal = CustomerFeedback::where('created_at', '<', now()->subDays(7))->count();
        $totalTrend = $previousTotal ? (($total - $previousTotal) / $previousTotal) * 100 : 0;

        // Get average resolution time
        $avgResolutionTime = CustomerFeedback::whereNotNull('resolved_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_time'))
            ->first()
            ->avg_time ?? 0;

        return [
            Stat::make('Total Feedback', $total)
                ->description('All time feedback count')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart([7, 4, 6, 8, 5, 9, $total])
                ->color('primary'),

            Stat::make('Pending', $pending)
                ->description('Awaiting response')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart([2, 3, 5, 4, 3, 2, $pending]),

            Stat::make('In Progress', $inProgress)
                ->description('Being handled')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->chart([1, 2, 3, 2, 4, 3, $inProgress]),

            Stat::make('Resolved', $resolved)
                ->description('Successfully handled')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([4, 5, 3, 6, 3, 7, $resolved]),

            Stat::make('Avg. Resolution Time', round($avgResolutionTime, 1) . ' hours')
                ->description('Time to resolve')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
