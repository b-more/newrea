<?php

namespace App\Filament\Resources\CustomerFeedbackResource\Pages;

use App\Filament\Resources\CustomerFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerFeedback extends EditRecord
{
    protected static string $resource = CustomerFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),

            Actions\Action::make('resolve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Textarea::make('resolution')
                        ->label('Resolution Details')
                        ->required()
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => 'resolved',
                        'resolution' => $data['resolution'],
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id()
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Feedback Resolved')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn (): bool =>
                    $this->record->status === 'pending' ||
                    $this->record->status === 'in_progress'
                ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
