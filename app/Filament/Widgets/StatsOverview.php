<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    // protected function getStats(): array
    // {
    //     $success_count = DB::table('payments')->where('payment_status_id', 1)->count();
    //     $pending_count = DB::table('payments')->where('payment_status_id', 2)->count();
    //     $failed_count = DB::table('payments')->where('payment_status_id', 3)->count();

    //     $complaints = DB::table('complaints')->whereNotNull('meter_number')->count();
    //     $feedbacks = DB::table('payments')->where('payment_status_id', 2)->count();
    //     $inquiries = DB::table('payments')->where('payment_status_id', 3)->count();

    //     return [
    //         Stat::make('Successful Payments', $success_count)
    //             ->description('Increase')
    //             ->descriptionIcon('heroicon-m-arrow-trending-up')
    //             ->color('success'),
    //         Stat::make('Pending Payments', $pending_count)
    //             ->description('Increase')
    //             ->descriptionIcon('heroicon-m-arrow-trending-up')
    //             ->color('info'),
    //         Stat::make('Failed Payments', $failed_count)
    //             ->description('Increase')
    //             ->descriptionIcon('heroicon-m-arrow-trending-down')
    //             ->color('danger'),

    //         // Stat::make('Complaints', $complaints)
    //         //     ->description('Increase')
    //         //     ->descriptionIcon('heroicon-m-arrow-trending-down')
    //         //     ->color('danger'),
    //         ];

    // }

    protected function getStats(): array
{
    $success_count = DB::table('payments')->where('payment_status_id', 1)->count();
    $pending_count = DB::table('payments')->where('payment_status_id', 2)->count();
    $failed_count = DB::table('payments')->where('payment_status_id', 3)->count();

    $complaints = DB::table('complaints')->whereNotNull('meter_number')->count();
    $feedbacks = DB::table('customer_feedbacks')->whereNotNull('feedback_number')->count();
    $inquiries = DB::table('general_inquiries')->whereNotNull('inquiry_number')->count();

    return [
        Stat::make('Successful Payments', $success_count)
            ->description('Increase')
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->color('success'),

        Stat::make('Pending Payments', $pending_count)
            ->description('Increase')
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->color('info'),

        Stat::make('Failed Payments', $failed_count)
            ->description('Increase')
            ->descriptionIcon('heroicon-m-arrow-trending-down')
            ->color('danger'),

        Stat::make('Complaints', $complaints)
            //->description('Increase')
            ->descriptionIcon('heroicon-m-face-frown'),
           // ->color('danger'),

        Stat::make('Feedbacks', $feedbacks)
            //->description('Increase')
            ->descriptionIcon('heroicon-m-face-smile'),
            //->color('warning'),

        Stat::make('Inquiries', $inquiries)
            //->description('Increase')
            ->descriptionIcon('heroicon-o-question-mark-circle')
            //->color('primary'),
    ];
}

}
