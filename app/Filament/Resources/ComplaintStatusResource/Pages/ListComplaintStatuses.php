<?php

namespace App\Filament\Resources\ComplaintStatusResource\Pages;

use App\Filament\Resources\ComplaintStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComplaintStatuses extends ListRecords
{
    protected static string $resource = ComplaintStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
