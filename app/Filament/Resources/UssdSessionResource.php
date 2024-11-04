<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UssdSessionResource\Pages;
use App\Filament\Resources\UssdSessionResource\RelationManagers;
use App\Models\UssdSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UssdSessionResource extends Resource
{
    protected static ?string $model = UssdSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('phone_number')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('session_id')
                    ->maxLength(255),
                Forms\Components\TextInput::make('case_no')
                    ->maxLength(255),
                Forms\Components\TextInput::make('step_no')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('phone_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('session_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('case_no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('step_no')
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
            'index' => Pages\ListUssdSessions::route('/'),
            'create' => Pages\CreateUssdSession::route('/create'),
            'edit' => Pages\EditUssdSession::route('/{record}/edit'),
        ];
    }    
}
