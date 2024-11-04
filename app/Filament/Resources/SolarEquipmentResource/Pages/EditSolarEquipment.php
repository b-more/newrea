<?php

namespace App\Filament\Resources\SolarEquipmentResource\Pages;

use App\Filament\Resources\SolarEquipmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSolarEquipment extends EditRecord
{
    protected static string $resource = SolarEquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
