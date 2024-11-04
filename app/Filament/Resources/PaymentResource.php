<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\TransactionType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-m-banknotes';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        return $query->orderBy('created_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('phone_number')
                    ->tel()
                    ->maxLength(255),
                Select::make('payment_method_id')->label('Payment Method')->required()->options(PaymentMethod::all()->pluck('name','id')->toArray())->id('payment-method-field'),
                Select::make('payment_channel_id')->label('Payment Channel')->required()->options(PaymentChannel::all()->pluck('name','id')->toArray())->id('payment-channel-field'),
                Select::make('payment_status_id')->label('Payment Status')->required()->options(PaymentStatus::all()->pluck('name','id')->toArray()),
                Select::make('transaction_type_id')->label('Transaction Type')->required()->options(TransactionType::all()->pluck('name','id')->toArray()),

                Forms\Components\TextInput::make('session_id')
                    ->maxLength(255),
                Forms\Components\TextInput::make('meter_number')
                    ->maxLength(255)
                    ->required(),
                Forms\Components\TextInput::make('payment_reference_number')
                    ->maxLength(255)
                    ->required(),
                Forms\Components\TextInput::make('amount_paid')
                    ->maxLength(255)
                    ->required(),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
                Forms\Components\TextInput::make('comments')
                    ->maxLength(255),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable(),    
                Tables\Columns\TextColumn::make('phone_number')
                    ->searchable()
                    ->label('Phone Number')
                    ->formatStateUsing(fn (string $state): string => "260".substr($state, -9))
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->alignEnd(true)
                    ->formatStateUsing(fn (string $state): string => "K ".number_format($state,2))
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('payment_method.name')
                    ->numeric()
                    ->sortable()
                    ->label('Payment Method'),
                Tables\Columns\TextColumn::make('payment_channel.name')
                    ->numeric()
                    ->sortable()
                    ->label('Network'),
                Tables\Columns\TextColumn::make('payment_status.name')
                    ->sortable()
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Successful' => 'success',
                        'Failed' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('payment_route.name')
                    ->numeric()
                    ->sortable()
                    ->label('Route'),
                Tables\Columns\TextColumn::make('transaction_type.name')
                    ->numeric()
                    ->sortable()
                    ->label('Type'),
                /*Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable()
                    ->label('Received by'),
                Tables\Columns\TextColumn::make('session_id')
                    ->searchable()
                    ->label('Type'),*/
                Tables\Columns\TextColumn::make('meter_number')
                    ->searchable()
                    ->label('Meter No.'),
                Tables\Columns\TextColumn::make('payment_reference_number')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('description')
                //     ->searchable(),
                // Tables\Columns\TextColumn::make('comments')
                //     ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                 Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
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
            'index' => Pages\ListPayments::route('/'),
            // 'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
