<?php

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Log;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->url(fn () => $this->getResource()::getUrl('view', ['record' => $this->record]))
                ->color('gray'),

            DeleteAction::make()
                ->visible(fn () =>
                    auth()->user()->can('delete_agents') &&
                    !$this->record->is_deleted
                )
                ->before(function () {
                    // Log deletion
                    Log::info('Agent deleted', [
                        'agent_id' => $this->record->id,
                        'deleted_by' => auth()->id()
                    ]);
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Format phone numbers
        if (isset($data['agent_phone_number'])) {
            $data['agent_phone_number'] = ltrim($data['agent_phone_number'], '+260');
        }
        if (isset($data['personal_phone_number'])) {
            $data['personal_phone_number'] = ltrim($data['personal_phone_number'], '+260');
        }
        if (isset($data['next_of_kin_number'])) {
            $data['next_of_kin_number'] = ltrim($data['next_of_kin_number'], '+260');
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Log the update
        Log::info('Agent updated', [
            'agent_id' => $this->record->id,
            'updated_by' => auth()->id(),
            'changed_fields' => $this->record->getDirty()
        ]);

        // Send notifications if status changed
        if ($this->record->isDirty('status') || $this->record->isDirty('is_active')) {
            try {
                // Example: Send SMS notification
                // $message = "Your agent account status has been updated to: {$this->record->statusLabel}";
                // NotificationService::sendSMS($this->record->agent_phone_number, $message);
            } catch (\Exception $e) {
                Log::error('Failed to send agent update notification', [
                    'agent_id' => $this->record->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
