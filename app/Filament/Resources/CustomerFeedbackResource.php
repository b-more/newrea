<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerFeedbackResource\Pages;
use App\Models\CustomerFeedback;
use App\Models\CommunicationChannel;
use App\Filament\Resources\CustomerFeedbackResource\Widgets;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;

class CustomerFeedbackResource extends Resource
{
    protected static ?string $model = CustomerFeedback::class;

    protected static ?string $navigationIcon = 'heroicon-m-face-smile';

    protected static ?string $navigationGroup = 'Customer Management';

    protected static ?string $navigationLabel = 'Feedback';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\FeedbackOverview::class,
        ];
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() > 0 ? 'warning' : 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Feedback Information')
                    ->description('View and manage customer feedback details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('feedback_number')
                                    ->label('Reference Number')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefixIcon('heroicon-m-hashtag'),

                                Forms\Components\TextInput::make('phone_number')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->prefixIcon('heroicon-m-phone')
                                    ->telRegex('/^[0-9]{9,12}$/')
                                    ->required(),

                                    Forms\Components\Select::make('communication_channel_id')
                                    ->label('Channel')
                                    ->relationship('communicationChannel', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('code')
                                            ->required()
                                            ->maxLength(50)
                                            ->unique(),
                                        Forms\Components\Textarea::make('description')
                                            ->maxLength(255),
                                        Forms\Components\Toggle::make('is_active')
                                            ->default(true),
                                    ]),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'in_progress' => 'In Progress',
                                        'resolved' => 'Resolved',
                                        'closed' => 'Closed'
                                    ])
                                    ->default('pending')
                                    ->required(),

                                // Forms\Components\TextInput::make('session_id')
                                //     ->label('Session ID')
                                //     ->disabled()
                                //     ->visible(fn ($record) => filled($record?->session_id)),
                            ]),
                    ]),

                Section::make('Feedback Content')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Customer Feedback')
                            ->required()
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('comment')
                            ->label('Internal Comment')
                            ->helperText('Add internal notes or response to the feedback')
                            ->columnSpanFull(),
                    ]),

                Section::make('Resolution Details')
                    ->schema([
                        Forms\Components\Textarea::make('resolution')
                            ->label('Resolution Details')
                            ->helperText('Describe how the feedback was handled')
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Resolved At')
                            ->visible(fn ($record) => $record?->status === 'resolved'),

                        Forms\Components\TextInput::make('resolved_by')
                            ->label('Resolved By')
                            ->visible(fn ($record) => $record?->status === 'resolved'),
                    ])
                    ->visible(fn ($record) => $record?->status === 'resolved' || $record?->status === 'closed'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('feedback_number')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('phone_number')
                    ->label('Customer')
                    ->searchable()
                    ->formatStateUsing(fn ($record) =>
                        $record->customer ? $record->customer->name : $record->phone_number
                    ),

                TextColumn::make('description')
                    ->label('Feedback')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),

                TextColumn::make('communication_channel.name')
                    ->label('Channel')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'in_progress' => 'info',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed'
                    ]),

                SelectFilter::make('communication_channel_id')
                    ->label('Channel')
                    ->relationship('communicationChannel', 'name'),

                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Action::make('resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('resolution')
                            ->label('Resolution Details')
                            ->required(),
                    ])
                    ->action(function (CustomerFeedback $record, array $data): void {
                        $record->update([
                            'status' => 'resolved',
                            'resolution' => $data['resolution'],
                            'resolved_at' => now(),
                            'resolved_by' => auth()->id()
                        ]);

                        Notification::make()
                            ->title('Feedback Resolved')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (CustomerFeedback $record): bool =>
                        $record->status === 'pending' || $record->status === 'in_progress'
                    ),

                Action::make('reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (CustomerFeedback $record): void {
                        $record->update([
                            'status' => 'in_progress',
                            'resolved_at' => null,
                            'resolved_by' => null
                        ]);
                    })
                    ->visible(fn (CustomerFeedback $record): bool =>
                        $record->status === 'resolved' || $record->status === 'closed'
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
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
            'index' => Pages\ListCustomerFeedback::route('/'),
            'create' => Pages\CreateCustomerFeedback::route('/create'),
            'edit' => Pages\EditCustomerFeedback::route('/{record}/edit'),
            'view' => Pages\ViewCustomerFeedback::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['feedback_number', 'phone_number', 'description'];
    }
}
