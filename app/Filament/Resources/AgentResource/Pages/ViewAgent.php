<?php

// File: app/Filament/Resources/AgentResource/Pages/ViewAgent.php
namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewAgent extends ViewRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->url(fn () => $this->getResource()::getUrl('edit', ['record' => $this->record]))
                ->visible(fn () => !$this->record->is_deleted),

            Action::make('back')
                ->url(fn () => $this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }
}
