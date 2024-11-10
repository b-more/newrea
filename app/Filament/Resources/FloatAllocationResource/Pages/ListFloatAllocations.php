<?php

namespace App\Filament\Resources\FloatAllocationResource\Pages;

use App\Filament\Resources\FloatAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFloatAllocations extends ListRecords
{
    protected static string $resource = FloatAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
