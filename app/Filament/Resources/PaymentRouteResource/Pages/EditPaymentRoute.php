<?php

namespace App\Filament\Resources\PaymentRouteResource\Pages;

use App\Filament\Resources\PaymentRouteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentRoute extends EditRecord
{
    protected static string $resource = PaymentRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
