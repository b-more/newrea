<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentResource\Pages;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Models\Agent;
use App\Models\AuditTrail;
use App\Models\BankBranch;
use App\Models\BankName;
use App\Models\BusinessType;
use App\Models\District;
use App\Models\Province;
use App\Models\AgentActivityLog;
use App\Models\FloatTransaction;
use App\Services\SmsService;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Columns\IconColumn;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Agent Status Constants
    const STATUS_PENDING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_SUSPENDED = 2;
    const STATUS_BLACKLISTED = 3;
    const STATUS_DELETED = 4;

    protected static array $agentStatuses = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_SUSPENDED => 'Suspended',
        self::STATUS_BLACKLISTED => 'Blacklisted',
        self::STATUS_DELETED => 'Deleted',
    ];

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->role_id == 1) {
            return $query->orderBy('created_at', 'desc');
        }

        return $query->where('is_deleted', 0)->orderBy('created_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                Wizard::make([
                    // Step 1: Agent Details
                    Wizard\Step::make('Agent Details')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('business_name')
                                    ->prefixIcon('heroicon-o-building-office-2')
                                    ->label('Agent Name')
                                    ->required(),

                                TextInput::make('dob')
                                    ->label('Date of Birth')
                                    ->prefixIcon('heroicon-m-calendar')
                                    ->required(),

                                Select::make('gender')
                                    ->options([
                                        'male' => 'Male',
                                        'female' => 'Female'
                                    ])
                                    ->required(),

                                TextInput::make('nrc')
                                    ->label('National Registration Number')
                                    ->prefixIcon('heroicon-m-identification')
                                    ->required(),

                                Select::make('business_type_id')
                                    ->label('Business Type')
                                    ->options(BusinessType::all()->pluck('name', 'id'))
                                    ->required()
                                    ->live(),

                                TextInput::make('business_tpin')
                                    ->label('TPIN')
                                    ->prefix('TPIN')
                                    ->unique(ignoreRecord: true)
                                    ->required(),
                            ]),

                            Grid::make(2)->schema([
                                TextInput::make('business_address_line_1')
                                    ->label('Physical Address')
                                    ->prefixIcon('heroicon-o-map-pin')
                                    ->required(),

                                TextInput::make('village')
                                    ->label('Village')
                                    ->prefixIcon('heroicon-o-home')
                                    ->required(),

                                Select::make('province_id')
                                    ->label('Province')
                                    ->options(Province::all()->pluck('name', 'id'))
                                    ->reactive()
                                    ->required(),

                                Select::make('district_id')
                                    ->label('District')
                                    ->options(function (callable $get) {
                                        $province = Province::find($get('province_id'));
                                        if (!$province) {
                                            return District::all()->pluck('name', 'id');
                                        }
                                        return District::where('province_id', $province->id)
                                            ->pluck('name', 'id');
                                    })
                                    ->required(),
                            ]),
                        ]),

                    // Step 2: Contact Details
                    Wizard\Step::make('Contact Details')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('agent_phone_number')
                                    ->label('Agent Phone Number')
                                    ->prefix('+260')
                                    ->length(9)
                                    ->unique(ignoreRecord: true)
                                    ->required(),

                                TextInput::make('personal_phone_number')
                                    ->label('Personal Phone Number')
                                    ->prefix('+260')
                                    ->length(9)
                                    ->unique(ignoreRecord: true)
                                    ->required(),

                                TextInput::make('next_of_kin_name')
                                    ->label('Next of Kin Name')
                                    ->required(),

                                TextInput::make('next_of_kin_relation')
                                    ->label('Next of Kin Relationship')
                                    ->required(),

                                TextInput::make('next_of_kin_address')
                                    ->label('Next of Kin Address')
                                    ->required(),

                                TextInput::make('next_of_kin_number')
                                    ->label('Next of Kin Phone Number')
                                    ->prefix('+260')
                                    ->length(9)
                                    ->required(),
                            ]),

                            Section::make('Required Documents')
                                ->schema([
                                    FileUpload::make('nrc_files')
                                        ->label('NRC Copies')
                                        ->directory('agent_documents/nrc')
                                        ->multiple()
                                        ->maxSize(5120)
                                        ->acceptedFileTypes(['image/*', 'application/pdf'])
                                        ->required(),

                                    FileUpload::make('business_documents')
                                        ->label('Business Registration Documents')
                                        ->directory('agent_documents/business')
                                        ->multiple()
                                        ->maxSize(5120)
                                        ->acceptedFileTypes(['image/*', 'application/pdf'])
                                        ->required()
                                        ->visible(fn ($get) => $get('business_type_id') == 2),
                                ]),
                        ]),

                    // Step 3: Bank Details
                    Wizard\Step::make('Bank Details')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('bank_account_name')
                                    ->label('Bank Account Name')
                                    ->required(),

                                Select::make('bank_name')
                                    ->label('Bank Name')
                                    ->options(BankName::all()->pluck('name', 'name'))
                                    ->required()
                                    ->live(),

                                Select::make('bank_branch')
                                    ->label('Bank Branch')
                                    ->options(function ($get) {
                                        $bank = BankName::where('name', $get('bank_name'))->first();
                                        if (!$bank) {
                                            return BankBranch::all()->pluck('branch_name', 'branch_name');
                                        }
                                        return BankBranch::where('bank_name_id', $bank->id)
                                            ->pluck('branch_name', 'branch_name');
                                    })
                                    ->required(),

                                TextInput::make('bank_account_number')
                                    ->label('Bank Account Number')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),

                            // Float Configuration Section
                            Section::make('Float Configuration')
                                ->schema([
                                    TextInput::make('float_limit')
                                        ->label('Float Limit')
                                        ->prefix('K')
                                        ->numeric()
                                        ->default(0)
                                        ->required(),

                                    TextInput::make('initial_float')
                                        ->label('Initial Float Balance')
                                        ->prefix('K')
                                        ->numeric()
                                        ->default(0)
                                        ->required(),
                                ])
                                ->visible(fn () => Auth::user()->role_id == 1),
                        ]),
                ])->submitAction(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('business_name')
                    ->label('Agent Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('agent_phone_number')
                    ->label('Phone Number')
                    ->searchable(),

                Tables\Columns\TextColumn::make('province.name')
                    ->label('Location')
                    ->description(fn($record) => $record->district->name ?? '')
                    ->searchable(),

                Tables\Columns\TextColumn::make('float_balance')
                    ->label('Float Balance')
                    ->money('ZMW')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(self::$agentStatuses)
                    ->attribute('is_active'),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
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
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->color('warning')
                        ->visible(fn (Agent $record) => !$record->is_deleted),

                    // Approve Action - for pending agents
                    Tables\Actions\Action::make('approve')
        ->icon('heroicon-o-check-circle')
        ->color('success')
        ->requiresConfirmation()
        ->modalHeading('Approve Agent')
        ->visible(fn (Agent $record) =>
            $record->is_active == 0 &&
            !$record->is_deleted
        )
        ->action(function (Agent $record) {
            try {
                DB::beginTransaction();

                $defaultPin = rand(1000, 9999);
                $password = "REA.1234";

                $record->update([
                    'is_active' => 1,
                    'pin' => $defaultPin,
                    'activated_at' => now(),
                    'activated_by' => auth()->id()
                ]);

                // Log activity
                AgentActivityLog::create([
                    'agent_id' => $record->id,
                    'activity_type' => 'account_activation',
                    'phone_number' => $record->agent_phone_number,
                    'details' => [
                        'activated_by' => auth()->id(),
                        'activated_at' => now()
                    ],
                    'status' => 'completed',
                    'ip_address' => request()->ip()
                ]);

                // Send SMS notification
                $message = "Your REA Agent account has been activated.\n" .
                          "Merchant Code: {$record->merchant_code}\n" .
                          "Default PIN: {$defaultPin}\n" .
                          "Password: {$password}\n" .
                          "Please change your PIN on first login.";

                          SmsService::send(
                            $message,
                            "260" . $record->agent_phone_number
                        );

                DB::commit();

                Notification::make()
                    ->title('Agent Approved Successfully')
                    ->success()
                    ->send();

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }),

                    // Deactivate Action - for active agents
                    Tables\Actions\Action::make('deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Agent')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Deactivation Reason')
                                ->required()
                        ])
                        ->visible(fn (Agent $record) =>
                            $record->is_active == 1 &&
                            !$record->is_deleted
                        )
                        ->action(function (Agent $record, array $data) {
                            $record->update([
                                'is_active' => 0,
                                'suspended_at' => now(),
                                'suspended_by' => auth()->id(),
                                'suspension_reason' => $data['reason']
                            ]);

                            // Log activity
                            AuditTrail::create([
                                'user_id' => auth()->id(),
                                'module' => 'Agents',
                                'activity' => "Deactivated agent {$record->business_name}",
                                'ip_address' => request()->ip()
                            ]);

                            // Send SMS notification
                            $message = "Your REA agent account has been deactivated. Reason: {$data['reason']}";
                            // Implement your SMS sending logic here
                        }),

                    // Reset PIN Action
                    Tables\Actions\Action::make('resetPin')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reset Agent PIN')
                        ->visible(fn (Agent $record) =>
                            $record->is_active == 1 &&
                            !$record->is_deleted
                        )
                        ->action(function (Agent $record) {
                            try {
                                DB::beginTransaction();

                                $newPin = rand(1000, 9999);

                                $record->update([
                                    'pin' => $newPin,
                                    'pin_changed_at' => now(),
                                    'pin_attempts' => 0,
                                    'pin_locked_until' => null
                                ]);

                                // Log activity
                                AgentActivityLog::create([
                                    'agent_id' => $record->id,
                                    'activity_type' => 'pin_reset',
                                    'phone_number' => $record->agent_phone_number,
                                    'details' => [
                                        'reset_by' => auth()->id(),
                                        'reset_at' => now()
                                    ],
                                    'status' => 'completed',
                                    'ip_address' => request()->ip()
                                ]);

                                // Send SMS notification
                                $message = "Your REA agent PIN has been reset.\n" .
                                          "New PIN: {$newPin}\n" .
                                          "Please change this PIN on your next login for security.";

                                SmsService::send(
                                    $message,
                                    "260" . $record->agent_phone_number
                                );

                                DB::commit();

                                Notification::make()
                                    ->title('PIN Reset Successfully')
                                    ->success()
                                    ->send();

                            } catch (\Exception $e) {
                                DB::rollBack();
                                Log::error('PIN reset failed', [
                                    'error' => $e->getMessage(),
                                    'agent_id' => $record->id
                                ]);
                                throw $e;
                            }
                        }),

                    // Update Float Action
                    Tables\Actions\Action::make('updateFloat')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('info')
                        ->form([
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->prefix('K'),

                            Forms\Components\Select::make('type')
                                ->label('Transaction Type')
                                ->options([
                                    'credit' => 'Add Float',
                                    'debit' => 'Deduct Float'
                                ])
                                ->required(),

                            Forms\Components\TextInput::make('description')
                                ->label('Description')
                                ->required()
                        ])
                        ->action(function (Agent $record, array $data) {
                            try {
                                DB::beginTransaction();

                                $balanceBefore = $record->float_balance;
                                $amount = $data['amount'];
                                $newBalance = $data['type'] === 'credit'
                                    ? $balanceBefore + $amount
                                    : $balanceBefore - $amount;

                                if ($data['type'] === 'debit' && $newBalance < 0) {
                                    throw new \Exception('Insufficient float balance');
                                }

                                // Create transaction record
                                $transaction = FloatTransaction::create([
                                    'agent_id' => $record->id,
                                    'amount' => $amount,
                                    'type' => $data['type'],
                                    'reference_number' => 'FLT-' . time() . rand(1000, 9999),
                                    'payment_method' => 'admin',
                                    'status' => 'completed',
                                    'description' => $data['description'],
                                    'balance_before' => $balanceBefore,
                                    'balance_after' => $newBalance,
                                    'processed_by' => auth()->id(),
                                    'processed_at' => now()
                                ]);

                                $record->update(['float_balance' => $newBalance]);

                                // Send SMS notification
                                $message = "Float " . ($data['type'] === 'credit' ? "credit" : "debit") .
                                          " of K" . number_format($amount, 2) . "\n" .
                                          "New Balance: K" . number_format($newBalance, 2) . "\n" .
                                          "Ref: " . $transaction->reference_number;

                                SmsService::send(
                                    $message,
                                    "260" . $record->agent_phone_number
                                );

                                DB::commit();

                                Notification::make()
                                    ->title('Float Updated Successfully')
                                    ->success()
                                    ->body("New balance: K" . number_format($newBalance, 2))
                                    ->send();

                            } catch (\Exception $e) {
                                DB::rollBack();
                                Log::error('Float update failed', [
                                    'error' => $e->getMessage(),
                                    'agent_id' => $record->id
                                ]);
                                throw $e;
                            }
                        }),
                    // Delete Action
                    Tables\Actions\Action::make('delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Agent')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Deletion Reason')
                                ->required()
                        ])
                        ->visible(fn (Agent $record) =>
                            !$record->is_deleted &&
                            auth()->user()->role_id == 1
                        )
                        ->action(function (Agent $record, array $data) {
                            $record->update([
                                'is_deleted' => true,
                                'is_active' => 0,
                                'deleted_at' => now(),
                                'deleted_by' => auth()->id(),
                                'deletion_reason' => $data['reason']
                            ]);

                            // Log activity
                            AuditTrail::create([
                                'user_id' => auth()->id(),
                                'module' => 'Agents',
                                'activity' => "Deleted agent {$record->business_name}",
                                'ip_address' => request()->ip()
                            ]);
                        }),
                ])
                ->label('Actions')
                ->button()
                ->color('primary'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->visible(fn () => auth()->user()->role_id == 1),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Define any relationships here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
            'view' => Pages\ViewAgent::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', self::STATUS_PENDING)
            ->where('is_deleted', false)
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $pendingCount = static::getModel()::where('is_active', self::STATUS_PENDING)
            ->where('is_deleted', false)
            ->count();

        return $pendingCount > 0 ? 'warning' : 'primary';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Agent Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Agents';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function shouldRegisterNavigation(): bool
    {
        //return auth()->user()->can('view_agents');
        return true;
    }

    private function sendSmsNotification(string $message, string $phone_number): void
    {
        try {
            Log::info('Sending SMS notification', [
                'phone' => $phone_number,
                'message' => $message
            ]);

            $url_encoded_message = urlencode($message);
            $url = 'https://www.cloudservicezm.com/smsservice/httpapi?' .
                   'username=Blessmore&password=Blessmore&msg=' . $url_encoded_message .
                   '.+&shortcode=2343&sender_id=REA&phone=' . $phone_number .
                   '&api_key=121231313213123123';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                throw new \Exception("SMS API returned status code: $http_code");
            }

            Log::info('SMS sent successfully', [
                'phone' => $phone_number,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'error' => $e->getMessage(),
                'phone' => $phone_number
            ]);
        }
    }

}
