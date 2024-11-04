<?php

namespace App\Filament\Resources\ComplaintCategoryResource\Pages;

use App\Filament\Resources\ComplaintCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComplaintCategory extends EditRecord
{
    protected static string $resource = ComplaintCategoryResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\DeleteAction::make(),
    //     ];
    // }
}
