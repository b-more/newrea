<?php

namespace App\Filament\Resources\SolarEquipmentResource\Pages;

use App\Filament\Resources\SolarEquipmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSolarEquipment extends ListRecords
{
    protected static string $resource = SolarEquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
