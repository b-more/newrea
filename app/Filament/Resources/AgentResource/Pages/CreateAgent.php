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

function sendSmsNotification(string $message, string $phone_number): void

{
    // Send confirmation SMS
    $url_encoded_message = urlencode($message);

    $url = 'https://www.cloudservicezm.com/smsservice/httpapi?username=Blessmore&password=Blessmore&msg=' . $url_encoded_message . '.+&shortcode=2343&sender_id=GeePay Biz&phone=' . $phone_number . '&api_key=121231313213123123';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Use this only if you have SSL verification issues
    $response = curl_exec($ch);
    curl_close($ch);
}

function generateMerchantCode()
{
    // Generate a random number between 1000 and 9999
    $merchant_code= rand(100000, 999999);


    // Check if the payment reference number already exists in the database
    if (DB::table('agents')->where('merchant_code',$merchant_code)->exists()) {
        // If the payment reference number already exists, generate a new one recursively
        return generateMerchantCode();
    }
    return $merchant_code;
}

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    public function mount(): void
    {
        $user = Auth::user();
        //abort_unless(checkCreateBusinessesPermission() && (Auth::user()->role_id == 1 || Auth::user()->role_id == 2 || Auth::user()->role_id == 7), 403);

        $activity = AuditTrail::create([
            "user_id" => $user->id,
            "module" => "Businesses",
            "activity" => "Viewed Create Businesses Page",
            "ip_address" => request()->ip()
        ]);

        $activity->save();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $formatted_business_name = strtolower(str_replace(' ', '', $data['business_name']));


        //branch code
        $bank_id = BankName::where('name', $data['business_bank_name'])->first()->id;
        $branch_code = BankBranch::where('bank_name_id', $bank_id)->first()->branch_code ?? "";

        $user_id = Auth::user()->id;

        $data['user_id'] = $user_id;
        $data['is_active'] = 0;
        $data['business_bank_account_branch_code'] = $branch_code;

        return $data;

    }

    protected function afterCreate()
    {
        //log user activity
        $activity = AuditTrail::create([
            "user_id" => Auth::user()->id,
            "module" => "Agents",
            "activity" => "Created Agent record with ID ".$this->record->id,
            "ip_address" => request()->ip()
        ]);

        $activity->save();

        $password = "REA.1234";

        $merchant_code = generateMerchantCode();

        $update_business = Agent::where('id', $this->record->id)->update([
            "merchant_code" => $merchant_code
        ]);

        $candidate_name = $this->data['business_name'];
        $exploded_string  = explode(" ", $candidate_name);

        //send email with credentials to the business email address
        $account_number_to_send = $this->record->account_number;
        $account_owner_to_send = $exploded_string[0];
        $merchant_code_to_send = $merchant_code;

       

        $message = "Your REA Agent Merchant Code ID ".$merchant_code." has been created successfully. Use ".$password." as your temporal password to login after account activation";

        sendSmsNotification($message, "260".$this->data['agent_phone_number']);

        Log::info($message);

        //Mail::to($this->data['business_email'])->send(new WelcomeMail($password,$merchant_code_to_send));

       // SendAccountMail::dispatch($password,$merchant_code_to_send,$this->data['business_email']);

    }
}
