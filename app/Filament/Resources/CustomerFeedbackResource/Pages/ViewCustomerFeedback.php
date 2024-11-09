<?php

namespace App\Filament\Resources\CustomerFeedbackResource\Pages;

use App\Filament\Resources\CustomerFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerFeedback extends ViewRecord
{
    protected static string $resource = CustomerFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->status !== 'closed'),

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
                })
                ->visible(fn (): bool =>
                    $this->record->status === 'pending' ||
                    $this->record->status === 'in_progress'
                ),

            Actions\Action::make('reopen')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'in_progress',
                        'resolved_at' => null,
                        'resolved_by' => null
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Feedback Reopened')
                        ->warning()
                        ->send();
                })
                ->visible(fn (): bool =>
                    $this->record->status === 'resolved' ||
                    $this->record->status === 'closed'
                ),
        ];
    }
}
