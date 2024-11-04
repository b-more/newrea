<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditTrailResource\Pages;
use App\Filament\Resources\AuditTrailResource\RelationManagers;
use App\Models\AuditTrail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AuditTrailResource extends Resource
{
    protected static ?string $model = AuditTrail::class;

    protected static ?string $navigationIcon = 'heroicon-o-cursor-arrow-ripple';

    protected static ?string $navigationGroup = 'User Management';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->numeric(),
                Forms\Components\TextInput::make('module')
                    ->maxLength(255),
                Forms\Components\TextInput::make('activity')
                    ->maxLength(255),
                Forms\Components\TextInput::make('ip_address')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('module')
                    ->searchable(),
                Tables\Columns\TextColumn::make('activity')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditTrails::route('/'),
            'create' => Pages\CreateAuditTrail::route('/create'),
            'edit' => Pages\EditAuditTrail::route('/{record}/edit'),
        ];
    }    
}
