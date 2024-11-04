<?php

namespace App\Filament\Resources\PaymentStatusResource\Pages;

use App\Filament\Resources\PaymentStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentStatus extends CreateRecord
{
    protected static string $resource = PaymentStatusResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
