<?php

namespace App\Filament\Resources\SolarPurchaseResource\Pages;

use App\Filament\Resources\SolarPurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSolarPurchases extends ListRecords
{
    protected static string $resource = SolarPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
