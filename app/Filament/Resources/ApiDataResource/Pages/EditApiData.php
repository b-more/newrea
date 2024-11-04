<?php

namespace App\Filament\Resources\ApiDataResource\Pages;

use App\Filament\Resources\ApiDataResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApiData extends EditRecord
{
    protected static string $resource = ApiDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
