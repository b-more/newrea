<?php

namespace App\Filament\Resources\CustomerFeedbackResource\Pages;

use App\Filament\Resources\CustomerFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerFeedback extends ListRecords
{
    protected static string $resource = CustomerFeedbackResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\CreateAction::make(),
    //     ];
    // }
}
