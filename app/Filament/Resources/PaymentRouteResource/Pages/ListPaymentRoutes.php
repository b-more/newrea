<?php

namespace App\Filament\Resources\PaymentRouteResource\Pages;

use App\Filament\Resources\PaymentRouteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentRoutes extends ListRecords
{
    protected static string $resource = PaymentRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
