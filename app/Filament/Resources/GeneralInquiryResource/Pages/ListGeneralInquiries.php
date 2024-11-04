<?php

namespace App\Filament\Resources\GeneralInquiryResource\Pages;

use App\Filament\Resources\GeneralInquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGeneralInquiries extends ListRecords
{
    protected static string $resource = GeneralInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
