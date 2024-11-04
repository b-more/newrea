<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolarEquipmentResource\Pages;
use App\Filament\Resources\SolarEquipmentResource\RelationManagers;
use App\Models\SolarEquipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SolarEquipmentResource extends Resource
{
    protected static ?string $model = SolarEquipment::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Solar Equipment')
            ->description('Add solar Equipment to the system.')
            ->schema([
                TextInput::make('name')
                ->required()
                ->maxLength(255),
                TextInput::make('serial_number')
                ->required()
                ->maxLength(255),
                TextInput::make('price')
                ->required()
                ->maxLength(255),
                TextInput::make('location')
                ->required()
                ->maxLength(255),
                Select::make('agent')
                ->options([
                    'John Kalunga' => 'John Kalunga',
                    'Gift Musonda' => 'Gift Musonda',
                    'Gladys Phiri' => 'Gladys Phiri',
                ]),
                DateTimePicker::make('date_entered'),
    ])

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
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
            'index' => Pages\ListSolarEquipment::route('/'),
            'create' => Pages\CreateSolarEquipment::route('/create'),
            'edit' => Pages\EditSolarEquipment::route('/{record}/edit'),
        ];
    }    
}
