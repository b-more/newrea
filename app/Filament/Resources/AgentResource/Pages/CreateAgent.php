<?php

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use App\Jobs\SendAccountMail;
use App\Mail\WelcomeMail;
use App\Models\Agent;
use App\Models\AuditTrail;
use App\Models\BankBranch;
use App\Models\BankName;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    /**
     * Send SMS notification
     */
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

    /**
     * Generate unique merchant code
     */
    private function generateMerchantCode(): string
    {
        do {
            $merchant_code = 'REA' . now()->format('y') . rand(10000, 99999);
        } while (DB::table('agents')->where('merchant_code', $merchant_code)->exists());

        return $merchant_code;
    }

    public function mount(): void
    {
        // Authorization check
        abort_unless(
            auth()->user()->can('create_agents') ||
            in_array(auth()->user()->role_id, [1, 2, 7]),
            403
        );

        // Log page access
        AuditTrail::create([
            "user_id" => auth()->id(),
            "module" => "Agents",
            "activity" => "Accessed agent creation page",
            "ip_address" => request()->ip()
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            Log::info('Processing agent creation data', [
                'business_name' => $data['business_name'] ?? null
            ]);

            // Format business name
            $formatted_business_name = Str::lower(Str::replace(' ', '', $data['business_name']));

            // Get bank branch code
            $bank_branch_code = null;
            if (!empty($data['business_bank_name'])) {
                $bank = BankName::where('name', $data['business_bank_name'])->first();
                if ($bank) {
                    $branch = BankBranch::where('bank_name_id', $bank->id)->first();
                    $bank_branch_code = $branch?->branch_code;
                }
            }

            // Generate default PIN for USSD
            $default_pin = rand(1000, 9999);

            // Format phone numbers
            $data['agent_phone_number'] = ltrim($data['agent_phone_number'] ?? '', '+260');
            $data['personal_phone_number'] = ltrim($data['personal_phone_number'] ?? '', '+260');
            $data['next_of_kin_number'] = ltrim($data['next_of_kin_number'] ?? '', '+260');

            // Merge data
            return array_merge($data, [
                'user_id' => auth()->id(),
                'is_active' => false,
                'status' => Agent::STATUS_PENDING,
                'business_bank_account_branch_code' => $bank_branch_code,
                'merchant_code' => $this->generateMerchantCode(),
                'pin' => $default_pin,
                'float_balance' => 0,
                'float_limit' => $data['float_limit'] ?? 10000,
                'operation_status' => 'pending',
                'ussd_access_level' => 'basic',
                'commission_rate' => $data['commission_rate'] ?? 2.50,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in agent data mutation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        $agent = $this->record;

        try {
            // Log creation
            AuditTrail::create([
                "user_id" => auth()->id(),
                "module" => "Agents",
                "activity" => "Created agent {$agent->business_name} with ID {$agent->id}",
                "ip_address" => request()->ip()
            ]);

            // Default credentials
            $password = "REA.1234";
            $merchant_code = $agent->merchant_code;

            // Send SMS notification
            $message = "Your REA Agent account has been created successfully.\n" .
                      "Merchant Code: {$merchant_code}\n" .
                      "Default PIN: {$agent->pin}\n" .
                      "Password: {$password}\n" .
                      "Your account will be activated after verification.";

            $this->sendSmsNotification(
                $message,
                "260" . $agent->agent_phone_number
            );

            // Send email if business email is provided
            if (!empty($agent->business_email)) {
                Mail::to($agent->business_email)->send(
                    new WelcomeMail($password, $merchant_code)
                );
            }

            // Queue background tasks if needed
            SendAccountMail::dispatch(
                $password,
                $merchant_code,
                $agent->business_email
            );

            Log::info('Agent created successfully', [
                'agent_id' => $agent->id,
                'merchant_code' => $merchant_code
            ]);

        } catch (\Exception $e) {
            Log::error('Error in agent creation aftermath', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Agent created successfully';
    }

    protected function getCreatedNotificationContent(): ?string
    {
        $agent = $this->record;
        return "Agent {$agent->business_name} has been created with merchant code {$agent->merchant_code}";
    }
}
