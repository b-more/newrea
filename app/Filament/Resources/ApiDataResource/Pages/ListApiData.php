<?php

namespace App\Filament\Resources\ApiDataResource\Pages;

use App\Filament\Resources\ApiDataResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApiData extends ListRecords
{
    protected static string $resource = ApiDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
