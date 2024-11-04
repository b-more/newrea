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

class BackgroundTransactionConfirm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $initialised_transaction;

    /**
     * Create a new job instance.
     */
    public function __construct(Payment $initialised_transaction)
    {
        $this->initialised_transaction = $initialised_transaction;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $initialised_transaction = $this->initialised_transaction;

        //Check which network is the record using

        //which payment method is the record using

        //if it is Airtel it goes to airtel
        //if status response is 1, push to thundercloud and update
        //the payment record and send the sms to the user

        //Common functions among the three MNOs for sending to Thunder Cloud
        //Create the function outside which will receive the common parameter meter_no

        if($initialised_transaction->payment_method_id==2 && $initialised_transaction->payment_channel_id == 1)//AIRTEL
        {
            Log::info("Stated Background Intent", ["Corfirm Details" => json_encode($initialised_transaction)]);
            if ($initialised_transaction->payment_status_id == 2 && $initialised_transaction->retry_count <= 5){
                $this->ConfirmAirtelMoneyTransaction($initialised_transaction->id,$initialised_transaction->customer_id,$initialised_transaction->amount_paid,$initialised_transaction->payment_reference_number,$initialised_transaction->phone_number);
            }

        }elseif($initialised_transaction->payment_method_id==2 && $initialised_transaction->payment_channel_id == 2)//MTN
        {
            if ($initialised_transaction->payment_status_id == 2 && $initialised_transaction->retry_count <= 5){
                $this->ConfirmMtnMoneyTransaction($initialised_transaction->id,$initialised_transaction->customer_id,$initialised_transaction->amount_paid,$initialised_transaction->payment_reference_number,$initialised_transaction->phone_number);
            }
        }
    }

    function createTransactionSparkApi($payment_id, $customer_id, $amount, $ref_number, $phone_number): void
    {

        Log::info("Spark APP payment id=".$payment_id.", customer_id=".$customer_id.", amount=".$amount.", ref number=".$ref_number.", phone number=".$phone_number);

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://sparkapp-staging.spk.io:5010/api/v0/transaction/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'customer_id='.$customer_id.'&amount='.$amount.'&source=cash&external_id='.$ref_number,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authentication-Token: .eJwNw8kNwDAIBMBeeAfJxuuAa4nyWB_0X0Iy0jxii57HXaNUKhpTIziVYSP-nRVyCQpGrhGZu9vE7c3sAKxn9YIJeT_fFxNO.ZU-0fg.9FQ_6C4Tcv9ue2Tmda_8l3zvqnA'
        ),
        ));

        $response = curl_exec($curl);
        // Get the HTTP status code
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);


        curl_close($curl);
        Log::info("Spark APP", ["Status" => json_encode($http_status_code)]);
        Log::info("Spark App", ["Response" => json_encode($response)]);
        if($http_status_code == 201){
             // Decode the JSON response
            $decoded_response = json_decode($response, true);

            // Access 'status' and 'transaction_id'
            $status = $decoded_response['status'];
            $transaction_id = $decoded_response['transaction_id'];

            //Update the payment record
            $update_payment= Payment::where('id',$payment_id)->update([
                'payment_status_id'=> 1,
                'spark_transaction_id' => $transaction_id
            ]);

            //send message to the client
            $message_string = "You have successfully topped up your electricity. Txn number ".$transaction_id;
            $this->sendMessage($message_string, $phone_number);

        }else{
             // Decode the JSON response
             $decoded_response = json_decode($response, true);

             // Access 'status' and 'transaction_id'
             $status = $decoded_response['status'];
             $error_message = $decoded_response['error'];

             //Update the payment record
            $update_payment= Payment::where('id',$payment_id)->update([
                'payment_status_id'=> 2,
                'error_message' => $error_message
            ]);
        }

   }

   function ConfirmAirtelMoneyTransaction($payment_id, $customer_id, $amount, $payment_reference_number, $phone_number): void
   {
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

            $string_response = $token_response;
            $json = json_decode($string_response);
            $token = $json->access_token;

            if ($token) {

                //check payment confirmation

                $payment_response = Http::withHeaders([
                    'X-Currency' => 'ZMW',
                    'X-Country' => 'ZM',
                    'Accept' => '*/*',
                    'Authorization' => 'Bearer ' . $token
                ])->get('https://openapi.airtel.africa/standard/v1/payments/' . $payment_reference_number);

                $status_state = $payment_response->status();
                $status_json = $payment_response->json();

                if ($status_state == 200) {
                    Log::info("Background Airtel", ["Success Response" => json_encode($status_json)]);
                    //check if the key data exist
                    if (array_key_exists("data", $status_json)) {
                        if ($status_json['data']['transaction']['status'] == "TS") {
                          //Send to Spark API
                          $this->createTransactionSparkApi($payment_id,$customer_id, $amount, $payment_reference_number, $phone_number);
                        }elseif ($status_json['data']['transaction']['status'] == "TIP") {
                            $retry_count = Payment::where('id',$payment_id)->first()->retry_count;
                            if($retry_count+1 == 5){
                                $update_payment = Payment::where('id',$payment_id)->update([
                                    'payment_status_id' => 3,
                                    'retry_count' => $retry_count+1
                                ]);
                            }else{
                                $update_payment = Payment::where('id',$payment_id)->update([
                                    'retry_count' => $retry_count+1
                                ]);
                                $initialised_transaction = Payment::find($payment_id);
                                BackgroundTransactionConfirm::dispatch($initialised_transaction);
                            }
                        }
                    } else {
                        $update_payment = Payment::where('id',$payment_id)->update([
                            'payment_status_id' => 3 //failed
                        ]);
                    }
                }else{
                    Log::info("Background Airtel", ["Failed Response" => json_encode($status_json)]);
                    $initialised_transaction = Payment::find($payment_id);
                    BackgroundTransactionConfirm::dispatch($initialised_transaction);

                }

            }
   }

    function ConfirmMtnMoneyTransaction($payment_id, $customer_id, $amount, $payment_reference_number, $phone_number): void
    {
        $request_url = "https://proxy.momoapi.mtn.com/collection/v1_0/requesttopay/".$payment_reference_number;

        //generate auth token
        $token_response = Http::withBasicAuth('5f098bf7-051d-411e-9da5-17d7bf9e6ae5', 'df31dbf6faf04fe4b003be6859448ea0')->withHeaders([
            'X-Target-Environment' => 'mtnzambia',
            'Ocp-Apim-Subscription-Key' => '2abcde1eed76408389592e1b181ba0be'
        ])->post('https://proxy.momoapi.mtn.com/collection/token/');

        $response = (string)$token_response->getBody();
        $json = json_decode($response);
        //save the token into a variable
        $token = $json->access_token;

        //get status code
        $payment_response = Http::withToken($token)->withHeaders([
            'X-Target-Environment' => 'mtnzambia',
            'Ocp-Apim-Subscription-Key' => '2abcde1eed76408389592e1b181ba0be'
        ])->get($request_url);

        $status_state = $payment_response->status();
        $status_json = $payment_response->json();

        if($status_state == 200){
            if($status_json['status'] == "SUCCESSFUL") {
                //Send to Spark API
                $this->createTransactionSparkApi($payment_id,$customer_id, $amount, $payment_reference_number, $phone_number);
            }else{
                $retry_count = Payment::where('id',$payment_id)->first()->retry_count;
                if($retry_count+1 == 5){
                    $update_payment = Payment::where('id',$payment_id)->update([
                        'payment_status_id' => 3,
                        'retry_count' => $retry_count+1
                    ]);
                }else{
                    $update_payment = Payment::where('id',$payment_id)->update([
                        'retry_count' => $retry_count+1
                    ]);
                    $initialised_transaction = Payment::find($payment_id);
                    BackgroundTransactionConfirm::dispatch($initialised_transaction);
                }
            }
        }else{
            $initialised_transaction = Payment::find($payment_id);
            BackgroundTransactionConfirm::dispatch($initialised_transaction);
        }
    }

   function sendMessage($message_string, $phone_number): void
    {
        //Next auto response
        $url_encoded_message = urlencode($message_string);

        //Next auto response
        $sendSenderSMS = Http::withoutVerifying()
            ->post('https://www.cloudservicezm.com/smsservice/httpapi?username=Blessmore&password=Blessmore&msg=' . $url_encoded_message . '.+&shortcode=2343&sender_id=REA&phone=' . $phone_number . '&api_key=121231313213123123');

        //return $sendSenderSMS->body();
    }
}
