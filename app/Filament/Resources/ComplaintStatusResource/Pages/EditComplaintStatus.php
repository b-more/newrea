<?php

namespace App\Filament\Resources\ComplaintStatusResource\Pages;

use App\Filament\Resources\ComplaintStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComplaintStatus extends EditRecord
{
    protected static string $resource = ComplaintStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
