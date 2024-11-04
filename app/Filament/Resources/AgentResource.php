<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentResource\Pages;
use App\Filament\Resources\AgentResource\RelationManagers;
use App\Models\Agent;
use App\Models\AuditTrail;
use App\Models\BankBranch;
use App\Models\BankName;
use App\Models\BusinessType;
use App\Models\District;
use App\Models\Province;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup as ActionsActionGroup;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Stmt\Label;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if(auth()->user()->role_id == 1)
        {
            return $query->orderBy('created_at', 'desc');
        }
        return $query->where('is_deleted', 0)->orderBy('created_at', 'desc');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Wizard::make([
                            Wizard\Step::make('Agent Details')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('business_name')
                                            ->prefixIcon('heroicon-o-building-office-2')
                                            ->label('Agent Name')
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent),
                                        Forms\Components\TextInput::make('dob')
                                            ->label('Date of Birth')
                                            ->prefixIcon('heroicon-m-chat-bubble-bottom-center-text')
                                            ->required(),

                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('gender')
                                            ->options([
                                                'male' => 'Male',
                                                'female' => 'Female'
                                            ])
                                            ->label('Gender')
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent),
                                        Forms\Components\TextInput::make('nrc')
                                            ->label('National Registration Number')
                                            ->prefixIcon('heroicon-m-chat-bubble-bottom-center-text'),

                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Select::make('business_type_id')
                                            ->label('Business Type')
                                            ->options(BusinessType::all()->pluck('name', 'id')->toArray())
                                            ->live()
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent)
                                    ]),
                                Grid::make(1)
                                    ->schema([
                                        Forms\Components\TextInput::make('business_tpin')
                                            ->prefix('TPIN')
                                            ->label('Individual TPIN')
                                            ->unique(ignoreRecord: true)
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent),
                                    ])->visible(function(callable $get){
                                        if($get('business_type_id')== 1 && $get('business_type_id') == "1"){
                                            return true;
                                        }
                                        return false;
                                    }),
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('business_address_line_1')
                                            ->prefixIcon('heroicon-o-book-open')
                                            ->label('Physical Address')
                                            ->required(),
                                        Forms\Components\TextInput::make('village')
                                            ->prefixIcon('heroicon-o-book-open')
                                            ->label('Village')
                                            ->required(),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Select::make('province_id')
                                            ->label('Province')
                                            ->options(Province::all()->pluck('name', 'id')->toArray())
                                            ->reactive()
                                            ->required(),

                                        Select::make('district_id')
                                            ->label('District')
                                            ->options(function (callable $get) {
                                                $province = Province::find($get('province_id'));
                                                if (!$province) {
                                                    return District::all()->pluck('name', 'id');
                                                }
                                                return District::where('province_id', $province->id)->pluck('name', 'id');
                                            })
                                            ->reactive()
                                            ->required(),
                                    ]),
                                
                            ]),
                            Wizard\Step::make('Contact Details')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('agent_phone_number')
                                            ->length(9)
                                            ->label('Agent Phone Number')
                                            ->prefix('+260')
                                            ->unique(ignoreRecord: true)
                                            ->required(),
                                        Forms\Components\TextInput::make('personal_phone_number')
                                            ->length(9)
                                            ->label('Personal Phone Number')
                                            ->prefix('+260')
                                            ->unique(ignoreRecord: true)
                                            ->required(),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('next_of_kin_name')
                                            ->label('Next of Kin Name'),

                                        Forms\Components\TextInput::make('next_of_kin_relation')
                                            ->label('Next of Kin Relationship'),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('next_of_kin_address')
                                            ->label('Next of Kin Address'),

                                        Forms\Components\TextInput::make('next_of_kin_number')
                                            ->label('Next of Kin Phone Number'),
                                    ]),
                                Forms\Components\Section::make('Agent NRC')
                                    ->schema([
                                        FileUpload::make('director_nrc')
                                            ->label('NRC')
                                            ->directory('agent_nrc')
                                            ->reorderable()
                                            ->openable()
                                            ->maxSize(5)
                                            ->storeFileNamesIn('agent_nrc')
                                            ->multiple()
                                            ->maxSize(1024),
                                    ])

                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 1 && $get('business_type_id') == "1" ){
                                            return true;
                                        }
                                        return false;
                                    }),
                            ]),
                        Wizard\Step::make('Bank Details (Optional)')
                            ->schema([
                                Grid::make(1)
                                ->schema([
                                    TextInput::make('business_bank_account_name')
                                        ->Label('Bank Account Name')

                                ]),
                                Grid::make(2)
                                    ->schema([
                                        Select::make('business_bank_name')
                                            ->label('Bank Name')
                                            ->options(BankName::all()->pluck('name', 'name')->toArray())
                                            ->live(),

                                        Select::make('business_bank_account_branch_name')
                                            ->label('Bank Branch Name')
                                            ->options(function (callable $get) {
                                                $bank = BankName::where('name',$get('business_bank_name'))->first();
                                                if (!$bank) {
                                                    return BankBranch::all()->pluck('branch_name', 'branch_name');
                                                }
                                                return BankBranch::where('bank_name_id', $bank->id)->pluck('branch_name', 'branch_name');
                                            })
                                            ->reactive(),
                                    ]),
                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('business_bank_account_number')
                                        ->prefixIcon('heroicon-o-credit-card')
                                        ->label('Bank Account Number'),
                                    ]),

                                    Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('business_reg_number')
                                            ->prefix('PACRA')
                                            ->unique(ignoreRecord: true)
                                            ->Label('Pacra Number')
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent),
                                        Forms\Components\TextInput::make('business_tpin')
                                            ->prefix('TPIN')
                                            ->unique()
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent),
                                    ])
                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 2 && $get('business_type_id') == "2"){
                                            return true;
                                        }
                                        return false;
                                    }),
                                Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('business_reg_number')
                                            ->prefix('Reg No')
                                            ->unique(ignoreRecord: true)
                                            ->Label('Reg Number')
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent),
                                        Forms\Components\TextInput::make('business_tpin')
                                            ->prefix('TPIN')
                                            ->unique(ignoreRecord: true)
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent),
                                    ])
                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 2 && $get('business_type_id') == "2"){
                                            return true;
                                        }
                                        return false;
                                    }),
                                Forms\Components\Section::make('Certificate of Incorporation')
                                    ->schema([
                                        FileUpload::make('certificate_of_incorporation')
                                            ->label('')
                                            ->directory('certificate_of_incorporation')
                                            ->reorderable()
                                            ->openable()
                                            ->maxSize(5)
                                            ->multiple()
                                            ->storeFileNamesIn('certificate_of_incorporation')
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent)
                                            ->maxSize(1024),
                                    ])
                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 2 && $get('business_type_id') == "2"){
                                            return true;
                                        }
                                        return false;
                                    }),
                                Forms\Components\Section::make('Certificate of Incorporation')
                                    ->schema([
                                        FileUpload::make('certificate_of_incorporation')
                                            ->label('Certificate of Registration')
                                            ->directory('certificate_of_incorporation')
                                            ->reorderable()
                                            ->openable()
                                            ->maxSize(5)
                                            ->multiple()
                                            ->storeFileNamesIn('certificate_of_incorporation')
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent)
                                            ->maxSize(1024),
                                    ])
                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 2 && $get('business_type_id') == "2"){
                                            return true;
                                        }
                                        return false;
                                    }),
                                Forms\Components\Section::make('Tax Clearance')
                                    ->schema([
                                        FileUpload::make('tax_clearance')
                                            ->label('')
                                            ->directory('tax_clearance')
                                            ->reorderable()
                                            ->openable()
                                            ->maxSize(5)
                                            ->multiple()
                                            ->storeFileNamesIn('tax_clearance')
                                            ->maxSize(1024),
                                    ])
                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 2 && $get('business_type_id') == "2"){
                                            return true;
                                        }
                                        return false;
                                    }),
                                Forms\Components\Section::make('Tax Clearance')
                                    ->schema([
                                        FileUpload::make('tax_clearance')
                                            ->label('')
                                            ->directory('tax_clearance')
                                            ->reorderable()
                                            ->openable()
                                            ->maxSize(5)
                                            ->storeFileNamesIn('tax_clearance')
                                            ->multiple()
                                            ->required(static fn(Page $livewire): bool => $livewire instanceof Pages\CreateAgent)
                                            ->maxSize(1024),
                                    ])
                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 2 && $get('business_type_id') == "2"){
                                            return true;
                                        }
                                        return false;
                                    }),
                                    
                                    Forms\Components\Section::make('Director Details')
                                    ->schema([
                                        FileUpload::make('director_details')
                                            ->label('')
                                            ->directory('director_details')
                                            ->reorderable()
                                            ->openable()
                                            ->maxSize(5)
                                            ->storeFileNamesIn('director_details')
                                            ->multiple()
                                            ->maxSize(1024),
                                    ])
                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 2 && $get('business_type_id') == "2"){
                                            return true;
                                        }
                                        return false;
                                    }),
                                    Forms\Components\Section::make('Pacra Printout')
                                    ->schema([
                                        FileUpload::make('pacra_printout')
                                            ->label('')
                                            ->directory('pacra_printout')
                                            ->reorderable()
                                            ->openable()
                                            ->maxSize(5)
                                            ->storeFileNamesIn('pacra_printout')
                                            ->multiple()
                                            ->maxSize(1024),
                                    ])
                                    ->visible(function(callable $get){
                                        if($get('business_type_id')== 2 && $get('business_type_id') == "2"){
                                            return true;
                                        }
                                        return false;
                                    }),

                            ]),
                            
                        ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('business_name')
                    ->label('Agent Name')
                    ->wrap()
                    ->searchable()
                   ,
                Tables\Columns\TextColumn::make('province.name')
                    ->label('Province | District')
                    ->numeric()
                    ->sortable()
                    ->description(function($record){
                        return District::where('id', $record->district_id)->first()->name ?? "";
                    }),
                Tables\Columns\TextColumn::make('business_address_line_1')
                    ->label('Address | Agent Number')
                    ->description(function($record){
                        return "260".$record->business_phone_number;
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('merchant_code')
                    ->label('Merchant Code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('business_tpin')
                    ->searchable()
                    ->label('Agent TPIN'),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Status'),
                Tables\Columns\IconColumn::make('is_deleted')
                    ->boolean()
                    ->visible(function(){
                        return auth()->user()->role_id == 1;
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),

            ])
            ->recordUrl(
                fn (Agent $record): string => url('agents'),
            )
            ->filters([
                SelectFilter::make('is_active')
                ->multiple()
                ->options([
                    '0' => 'Pending',
                    '1' => 'Success',
                    '2' => 'De-activate'
                ]),
            Filter::make('created_at')
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
                })
            ])
            // ->actions([
            //     Tables\Actions\Action::make('Approve')
            //             ->action(function (Agent $record){
            //                 //change is_active column for Business and related Client accounts
            //                 $deactivate_business = Agent::where('id', $record->id)->update([
            //                     "is_active" => 1
            //                 ]);

            //                 //log user activity
            //                 $activity = AuditTrail::create([
            //                     "user_id" => Auth::user()->id,
            //                     "module" => "Businesses",
            //                     "activity" => "Activated Business record with ID ".$record->id,
            //                     "ip_address" => request()->ip()
            //                 ]);

            //                 $activity->save();

            //             })
            //             ->color('success')
            //             ->icon('heroicon-m-hand-thumb-up')
            //             ->requiresConfirmation()
            //             ->modalHeading('Approve Business')
            //             ->modalDescription(function($record){
            //                 return 'Are you sure you would like to approve this '.$record->business_name.' agent account';
            //             })
            //             ->modalSubmitActionLabel('Yes, Approve')
            //             // ->visible(function(Agent $record){
            //             //     //return checkUpdateBusinessesPermission() && $record->is_active == 0 && $record->is_delete == 0 && (Auth::user()->role_id == 3 || Auth::user()->role_id == 8);
            //             //     return $record->is_active == 0 && $record->is_delete == 0 && (Auth::user()->role_id == 3 || Auth::user()->role_id == 8);
            //             // })
            //             ,
            // ])
            ->actions([
                ActionsActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        // ->visible(function (){
                        //     return checkReadBusinessesPermission();
                        // })
                        ,
                    Tables\Actions\EditAction::make()
                        ->color('hyper')
                    //     ->visible(function (){
                    //     return checkUpdateBusinessesPermission();
                    // })
                    ,
                    Tables\Actions\Action::make('Approve')
                        ->action(function (Agent $record){
                            //change is_active column for Business and related Client accounts
                            $deactivate_business = Agent::where('id', $record->id)->update([
                                "is_active" => 1
                            ]);

                            // $deactivate_business_users = Client::where("business_id", $record->id)->update([
                            //     "is_active" => 1
                            // ]);

                            //log user activity
                            $activity = AuditTrail::create([
                                "user_id" => Auth::user()->id,
                                "module" => "Businesses",
                                "activity" => "Activated Business record with ID ".$record->id,
                                "ip_address" => request()->ip()
                            ]);

                            $activity->save();

                            // $account_owner_name = Client::where('id', $record->user_id)->first()->name;
                            // //production
                            // $api_secret_id_to_send =  apiAccessSecretId();
                            // $api_access_token_to_send = apiAccessToken();
                            // $password = "New.1234";

                            // //update api credentials records
                            // $production_api_credentials = APICredential::where("business_id", $record->id)->update([
                            //     "secret_id" => $api_secret_id_to_send,
                            //     "access_token" => Hash::make($api_access_token_to_send)
                            // ]);

                            // //send notification
                            // SendAccountMail::dispatch($record->account_number,$account_owner_name,$api_secret_id_to_send,$api_access_token_to_send,$password,$record->business_email);
                        })
                        ->color('success')
                        ->icon('heroicon-m-hand-thumb-up')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Business')
                        ->modalDescription(function($record){
                            return 'Are you sure you would like to approve this '.$record->business_name.' business account';
                        })
                        ->modalSubmitActionLabel('Yes, Approve')
                        // ->visible(function(Agent $record){
                        //     //return checkUpdateBusinessesPermission() && $record->is_active == 0 && $record->is_delete == 0 && (Auth::user()->role_id == 3 || Auth::user()->role_id == 8);
                        //     return $record->is_active == 0 && $record->is_delete == 0 && (Auth::user()->role_id == 3 || Auth::user()->role_id == 8);
                        // })
                        ,
                    Tables\Actions\Action::make('Deactivate')
                        ->action(function (Agent $record){
                            //change is_active column for Business and related Client accounts
                            $deactivate_business = Agent::where('id', $record->id)->update([
                                "is_active" => 0
                            ]);

                            // $deactivate_business_users = Client::where("business_id", $record->id)->update([
                            //     "is_active" => 0
                            // ]);

                            //log user activity
                            $activity = AuditTrail::create([
                                "user_id" => Auth::user()->id,
                                "module" => "Businesses",
                                "activity" => "Deactivated Business record with ID ".$record,
                                "ip_address" => request()->ip()
                            ]);

                            $activity->save();

                            $message_subject = "Account Deactivation";
                            //$account_owner_name = Client::where('id', $record->user_id)->first()->name;
                            $message_to_send = "Your ".$record->business_name." account number ".$record->account_number." has been de-activated. Kindly call our Support for immediate action/resolution.";
                            //send notification
                            //NotificationsMail::dispatch($record->account_number, $account_owner_name, $message_to_send, $message_subject, $record->business_email);
                        })
                        ->color('danger')
                        ->icon('heroicon-m-hand-thumb-down')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Business')
                        ->modalDescription(function($record){
                            return 'Are you sure you want to deactivate '. $record->business_name." business account?";
                        })
                        ->modalSubmitActionLabel('Yes, Deactivate')
                        // ->visible(function(Agent $record){
                        //     //return checkUpdateBusinessesPermission() && $record->is_active == 1 && $record->is_delete == 0 && Auth::user()->role_id == 3;
                        //     return $record->is_active == 1 && $record->is_delete == 0 && Auth::user()->role_id == 3;
                        // })
                        ,
                    Tables\Actions\Action::make('Delete')
                        ->action(function($record){
                            //change is_active column for Business and related Client accounts
                            $delete_business = agent::where('id', $record->id)->update([
                                "is_active" => 0,
                                "is_deleted" => 1
                            ]);

                            // $delete_agent_users = Client::where("agent_id", $record->id)->update([
                            //     "is_active" => 0,
                            //     "is_deleted" => 1
                            // ]);

                            //log user activity
                            $activity = AuditTrail::create([
                                "user_id" => Auth::user()->id,
                                "module" => "agentes",
                                "activity" => "Deleted agent record with details ".$record,
                                "ip_address" => request()->ip()
                            ]);

                            $activity->save();

                            $message_subject = "Account Deleted";
                            //$account_owner_name = Client::where('id', $record->user_id)->first()->name;
                            $message_to_send = "Your ".$record->agent_name." account number ".$record->account_number." has been deleted";
                            //send notification
                            //NotificationsMail::dispatch($record->account_number, $account_owner_name, $message_to_send, $message_subject, $record->agent_email);
                        })
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Delete agent')
                        ->modalDescription(function($record){
                            return "This delete action is permanent and cannot be undone";
                        })
                        ->modalSubmitActionLabel('Yes, Delete')
                        // ->visible(function(agent $record){
                        //     return checkDeleteAgentesPermission() && $record->is_delete == 0 && (Auth::user()->role_id == 3 || Auth::user()->role_id == 8);
                        // })
                        ,
                ])

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // ExportBulkAction::make()->visible(function (){
                    //     return checkCreateAgentesPermission();
                    // }),
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
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }    
}
