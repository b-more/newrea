<?php

namespace App\Filament\Resources\CustomerFeedbackResource\Pages;

use App\Filament\Resources\CustomerFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\CustomerFeedbackResource\Widgets\FeedbackOverview;

class ListCustomerFeedback extends ListRecords
{
    protected static string $resource = CustomerFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Feedback'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FeedbackOverview::class,
        ];
    }
}
