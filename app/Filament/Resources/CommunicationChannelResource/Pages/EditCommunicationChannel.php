<?php

namespace App\Filament\Resources\CommunicationChannelResource\Pages;

use App\Filament\Resources\CommunicationChannelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunicationChannel extends EditRecord
{
    protected static string $resource = CommunicationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
