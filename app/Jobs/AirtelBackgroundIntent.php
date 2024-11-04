<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirtelBackgroundIntent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $phone_number;
    public $amount;
    public $meter_no;
    public $customer_id;
    public $ref_number;

    /**
     * Create a new job instance.
     */
    public function __construct($phone_number, $amount, $meter_no, $customer_id, $ref_number)
    {
        $this->phone_number = $phone_number;
        $this->amount = $amount;
        $this->meter_no = $meter_no;
        $this->customer_id = $customer_id;
        $this->ref_number = $ref_number;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $phone_number = $this->phone_number;
        $amount = $this->amount;
        $meter_no = $this->meter_no;
        $customer_id = $this->customer_id;
        $ref_number = $this->ref_number;


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://openapi.airtel.africa/auth/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{"client_id":"19fd47b3-6c68-4759-b360-f0f2c4592e07","client_secret":"4dd9fea0-3c5d-4df5-a9ff-369bd16f511c","grant_type":"client_credentials"}',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Cookie: SERVERID=s115'
            ),
        ));

        $token_response = curl_exec($curl);

        curl_close($curl);

        Log::info("Airtel Money",["token" => json_encode($token_response)]);

        $string_response = $token_response;
        $json = json_decode($string_response);
        $token = $json->access_token;

        $airtel_body = [
            "phone_number" => $phone_number,
            "amount" => $amount,
            "meter_no" => $meter_no,
            "customer_id" => $customer_id,
            "ref_number" => $ref_number
        ];

        Log::info("Airtel Body",["Response" => json_encode($airtel_body)]);

        if($token) {

            $headers = [
                'Authorization' => 'Bearer '.$token,
                'X-Country' => 'ZM',
                'X-Currency' => 'ZMW',
                'Content-Type' => 'application/json',
            ];

            $response = Http::withHeaders($headers)
                ->post('https://openapi.airtel.africa/merchant/v1/payments/', [
                    "reference" => "Buy Electricity",
                    "subscriber" => [
                        "country" => "ZM",
                        "currency" => "ZMW",
                        "msisdn" => $phone_number,
                    ],
                    "transaction" => [
                        "amount" => $amount,
                        "country" => "ZM",
                        "currency" => "ZMW",
                        "id" => $ref_number,
                    ],
                ]);

            if ($response->successful()) {
                // Request was successful
                Log::info("Airtel Money",["Response" => json_encode($response->body())]);
            } else {
                // Request failed
                Log::info("Airtel Money",["Response" => json_encode($response->status())]);
            }

            //save payment intent into the database
            $initialised_transaction = Payment::create([
                'payment_method_id' => 2, // 1.Cash 2. mobile money
                'payment_channel_id' => 1, //1. Airtel 2. MTN 3. Zamtel
                'payment_reference_number' => $ref_number,
                'phone_number' => $phone_number,
                'meter_number' => $meter_no,
                'amount_paid' => $amount,
                'payment_status_id'=>2, //1. success 2. pending 3. failed
                'transaction_type_id'=>1, //1.credit and 2. Debit
                'customer_id'=> $customer_id,
                'retry_count' => 1,
                'payment_route_id' => 1,
            ]);

            $initialised_transaction->save();

            BackgroundTransactionConfirm::dispatch($initialised_transaction)->delay(now()->addSeconds(20));
        }
    }
}
