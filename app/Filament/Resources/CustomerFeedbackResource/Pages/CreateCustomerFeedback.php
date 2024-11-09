<?php

namespace App\Filament\Resources\CustomerFeedbackResource\Pages;

use App\Filament\Resources\CustomerFeedbackResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCustomerFeedback extends CreateRecord
{
    protected static string $resource = CustomerFeedbackResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['feedback_number'] = 'FB-' . date('ymd') . '-' . strtoupper(Str::random(4));
        $data['status'] = 'pending';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
