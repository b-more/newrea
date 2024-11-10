<?php

namespace App\Filament\Resources\FloatAllocationResource\Pages;

use App\Filament\Resources\FloatAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFloatAllocation extends EditRecord
{
    protected static string $resource = FloatAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
