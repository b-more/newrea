<?php

namespace App\Http\Controllers;

use App\Jobs\FrontendTransactionConfirm;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TransactionController extends Controller
{
    function generateConversationId()
    {
        $random = rand(100000000000000000, 999999999999999999);

        // Check if the payment reference number already exists in the database
        if (DB::table('payments')->where('payment_reference_number', $random)->exists()) {
            // If the payment reference number already exists, generate a new one recursively
            return $this->generateConversationId();
        }

        return $random;
    }

    public function check_customer(Request $request)
    {
        $request->validate([
            'meter_no' => 'required'
        ]);

        $customer = $this->get_customer_name($request->meter_no);

        if ($customer === "Not successful") {
            $custom_response = [
                'success' => false,
                'message' => 'Failed to fetch customer name',
            ];
            return response()->json($custom_response, 400);
        } else {
            $custom_response = [
                'success' => true,
                'message' => 'Customer fetched successfully',
                'customer' => $customer
            ];
            return response()->json($custom_response, 200);
        }

    }

    public function make_payment(Request $request)
    {
        $request->validate([
            "customer_id" => "required",
            "meter_no" => "required",
            "phone_number" => "required",
            "amount" => "required"
        ]);

        //payment intent
        $pay = $this->payment($request->phone_number, $request->amount, $request->meter_no, $request->customer_id);

        $custom_response = [
            "success" => true,
            "message" => "Payment Intent Sent to phone"
        ];

        return response()->json($custom_response, 200);
    }

    function uuidv4()
    {
        return sprintf('%05d-%05d-%05d-%05d',
            mt_rand(0, 9999),
            mt_rand(0, 9999),
            mt_rand(0, 999),
            mt_rand(0, 999)
        );
    }

    function payment($phone, $amount, $meter_no, $customer_id)
    {
        //Check if the phone number is airtel, mtn or Zamtel
        //airtel mobile money payment intent initiation
        if (str_starts_with($phone, '77') || str_starts_with($phone, '97') || str_starts_with($phone, '097') || str_starts_with($phone, '26097') || str_starts_with($phone, '077') || str_starts_with($phone, '26077') || str_starts_with($phone, '+26077') || str_starts_with($phone, '+26097')) {

            //generate the random reference number
            $ref_number = $this->uuidv4();

            $phone_number = $phone;

            if (str_starts_with($phone, '077')) {
                $phone_number = ltrim($phone_number, '0');
            } elseif (str_starts_with($phone, '26077')) {
                $phone_number = ltrim($phone_number, '260');
            } elseif (str_starts_with($phone, '26097')) {
                $phone_number = ltrim($phone_number, '260');
            } elseif (str_starts_with($phone, '+26077')) {
                $phone_number = ltrim($phone_number, '+260');
            } elseif (str_starts_with($phone, '+26097')) {
                $phone_number = ltrim($phone_number, '+260');
            } elseif (str_starts_with($phone, '097')) {
                $phone_number = ltrim($phone_number, '0');
            }

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
                    'Content-Type: application/json'
                ),
            ));

            $token_response = curl_exec($curl);

            curl_close($curl);

            Log::info("Airtel Money", ["token" => json_encode($token_response)]);

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

            Log::info("Airtel Body", ["Response" => json_encode($airtel_body)]);

            if ($token) {

                $headers = [
                    'Authorization' => 'Bearer ' . $token,
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
                    Log::info("Airtel Money", ["Response" => json_encode($response->body())]);
                } else {
                    // Request failed
                    Log::info("Airtel Money", ["Response" => json_encode($response->status())]);
                }

                //save payment intent into the database
                $initialised_transaction = Payment::create([
                    'payment_method_id' => 2, // 1.Cash 2. mobile money
                    'payment_channel_id' => 1, //1. Airtel 2. MTN 3. Zamtel
                    'payment_route_id' => 2, //Web App
                    'payment_reference_number' => $ref_number,
                    'phone_number' => $phone_number,
                    'meter_number' => $meter_no,
                    'amount_paid' => $amount,
                    'payment_status_id' => 2, //1. success 2. pending 3. failed
                    'transaction_type_id' => 1, //1.credit and 2. Debit
                    'customer_id' => $customer_id,
                    'retry_count' => 1
                ]);

                $initialised_transaction->save();

                FrontendTransactionConfirm::dispatch($initialised_transaction)->delay(now()->addSeconds(25));

                return true;
            }
            return true;

        } elseif (str_starts_with($phone, '76') || str_starts_with($phone, '96') || str_starts_with($phone, '096') || str_starts_with($phone, '26096') || str_starts_with($phone, '076') || str_starts_with($phone, '26076')) {

            //generate the random reference number
            //$ref_number = Str::uuid()->toString();
            $ref_number = $this->uuidv4();

            $phone_number = $phone;

            if (str_starts_with($phone, '076')) {
                $phone_number = ltrim($phone_number, '0');
            }
            if (str_starts_with($phone, '26076')) {
                $phone_number = ltrim($phone_number, '260');
            }
            if (str_starts_with($phone, '26096')) {
                $phone_number = ltrim($phone_number, '260');
            }
            if (str_starts_with($phone, '096')) {
                $phone_number = ltrim($phone_number, '0');
            }

            //MTN MOMO
            $payer_number = '260' . $phone_number;
            $reference = Str::uuid()->toString();

            $token_response = Http::withBasicAuth('5f098bf7-051d-411e-9da5-17d7bf9e6ae5', 'df31dbf6faf04fe4b003be6859448ea0')->withHeaders([
                'X-Target-Environment' => 'mtnzambia',
                'Ocp-Apim-Subscription-Key' => '2abcde1eed76408389592e1b181ba0be'
            ])->post('https://proxy.momoapi.mtn.com/collection/token/');

            $response = (string)$token_response->getBody();
            $json = json_decode($response);
            //save the token into a variable
            $token = $json->access_token;

            $body = [];
            $body['amount'] = $amount;
            $body['currency'] = "ZMW";
            $body['externalId'] = $ref_number;
            $body['payer']['partyIdType'] = "MSISDN";
            $body['payer']['partyId'] = $payer_number;
            $body['payerMessage'] = 'Paying Electricity for' . $amount;
            $body['payeeNote'] = 'Paying Electricity for' . $amount;

            $headers = [];
            $headers[] = "X-Reference-Id: " . $ref_number;
            $headers[] = "X-Target-Environment: mtnzambia";
            $headers[] = "Ocp-Apim-Subscription-Key: 2abcde1eed76408389592e1b181ba0be";
            $headers[] = "Authorization: Bearer " . $token;
            $headers[] = "Content-Type: application/json";
            $headers[] = "X-Callback-Url: https://rea.co.zm/payment-complete";

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://proxy.momoapi.mtn.com/collection/v1_0/requesttopay");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

            $curl_info = curl_getinfo($ch);

            $result = curl_exec($ch);

            Log::info("MTN Money", ["Response" => json_encode($result)]);

            if ($result) {
                //save payment intent into the database
                $initialised_transaction = Payment::create([
                    'payment_method_id' => 2, // 1.Cash 2. mobile money
                    'payment_channel_id' => 2, //1. Airtel 2. MTN 3. Zamtel
                    'payment_route_id' => 2, //Web App
                    'payment_reference_number' => $ref_number,
                    'phone_number' => $phone,
                    'meter_number' => $meter_no,
                    'amount_paid' => $amount,
                    'payment_status_id' => 2, //1. success 2. pending 3. failed
                    'transaction_type_id' => 1, //1.credit and 2. Debit
                    'customer_id' => $customer_id,
                    'retry_count' => 1
                ]);

                $initialised_transaction->save();

                FrontendTransactionConfirm::dispatch($initialised_transaction)->delay(now()->addSeconds(25));
            }

        } elseif (str_starts_with($phone, '75') || str_starts_with($phone, '95') || str_starts_with($phone, '095') || str_starts_with($phone, '26095') || str_starts_with($phone, '075') || str_starts_with($phone, '26075')) {

            //generate the random reference number
            $payment_ref = $this->generateConversationId();

            $phone_number = $phone;

            if (str_starts_with($phone, '075')) {
                $phone_number = ltrim($phone_number, '0');
            }
            if (str_starts_with($phone, '26075')) {
                $phone_number = ltrim($phone_number, '260');
            }
            if (str_starts_with($phone, '26095')) {
                $phone_number = ltrim($phone_number, '260');
            }
            if (str_starts_with($phone, '095')) {
                $phone_number = ltrim($phone_number, '0');
            }

            //save payment intent into the database
            $initialised_transaction = Payment::create([
                'payment_method_id' => 2, // 1.Cash 2. mobile money
                'payment_channel_id' => 3, //1. Airtel 2. MTN 3. Zamtel
                'payment_reference_number' => $payment_ref,
                'payment_route_id' => 2,
                'phone_number' => $phone,
                'meter_number' => $meter_no,
                'amount_paid' => $amount,
                'payment_status_id' => 2, //1. success 2. pending 3. failed
                'transaction_type_id' => 1, //1.credit and 2. Debit
                'customer_id' => $customer_id
            ]);

            $initialised_transaction->save();

            //Zamtel Money
            //send payment to zamtel money

            return $this->zamtel_soap("260" . $phone_number, $amount, $initialised_transaction->id, $payment_ref);
        }
    }

    function get_customer_name($meter_no)
    {
        $test_url = 'http://sparkapp-staging.spk.io:5010/api/v0/customers?meter_serial=' . $meter_no;
        $url = 'https://rea-location001.sparkmeter.cloud/api/v0/customers?meter_serial=' . $meter_no;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json', 'Authentication-Token: .eJwFwckRgDAIAMBeeMsMIHLU4viAJPRfgrsvlHQRRWFzCGqyY8hTOKaTc6_ZdOCCMPG1-zQnL1LSOpR29LFeKu7w_eZqE5s.ZX__SA.Ob37E1u4ZWoyQPbOajB0i5rTv_M'
            //'Content-Type: application/json', 'Authentication-Token: .eJwNw8kNwDAIBMBeeAfJxuuAa4nyWB_0X0Iy0jxii57HXaNUKhpTIziVYSP-nRVyCQpGrhGZu9vE7c3sAKxn9YIJeT_fFxNO.ZU304Q.NBAhH6aPi3BopxLPR5UmyNlVgrY'
        ));

        $response = curl_exec($ch);
        curl_close($ch);

        // Assuming $response contains the JSON string you provided
        $responseArray = json_decode($response, true);

        // Check if decoding was successful
        if ($responseArray !== null && isset($responseArray['status'])) {
            $status = $responseArray['status'];

            // Check if the status is 'success' before accessing 'name'
            if ($status === 'success' && isset($responseArray['customers'][0]['name'])) {


                // Now you can use $status and $name as needed
                $response = [
                    "name" => $responseArray['customers'][0]['name'],
                    "id" => $responseArray['customers'][0]['id'],
                    "phone_number" => $responseArray['customers'][0]['phone_number'],
                    "meter_no" => $meter_no
                ];
                //echo "Status: $status\n";
                return $response;
            } else {
                // Handle the case where status is not 'success' or 'name' is not present
                return "Not successful";
            }
        } else {
            // Handle the case where decoding failed or 'status' is not present
            return "Not successful";
        }


    }

    function zamtel_soap($phone, $amount, $transaction_id, $transaction_ref)
    {

        $current_date = Carbon::now();
        $timestamp = $current_date->format('YmdHis');
        $password = "YEGxyU69L8lHXjHUJe1SNk3MwOMTxQG9";
        $CllT_Caller = "CllT_Caller";
        $identifier = "111135";
        $test_url = "http://172.18.2.135:7661/payment/services/SYNCAPIRequestMgrService";
        $prod_url = "http://172.18.0.133:7661/payment/services/SYNCAPIRequestMgrService/";

        Log::info("Zamtel Web Started - PHONE NUMBER: " . $phone . "; AMOUNT: " . $amount.", TIMESTAMP ".$timestamp);

        $newXmlPayload = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
        xmlns:api="http://cps.huawei.com/synccpsinterface/api_requestmgr"
        xmlns:req="http://cps.huawei.com/synccpsinterface/request"
        xmlns:com="http://cps.huawei.com/synccpsinterface/common"
        xmlns:cus="http://cps.huawei.com/cpsinterface/customizedrequest">
            <soapenv:Body>
                <api:Request>
                    <!-- Request Header -->
                    <req:Header>
                        <req:Version>1.0</req:Version>
                        <req:CommandID>InitTrans_Customer Pay Organization Bill</req:CommandID>
                        <req:OriginatorConversationID>429106433679961234</req:OriginatorConversationID>
                        <req:Caller>
                            <req:CallerType>2</req:CallerType>
                            <req:ThirdPartyID>REA_Caller</req:ThirdPartyID>
                            <req:Password>YEGxyU69L8lHXjHUJe1SNk3MwOMTxQG9</req:Password>
                        </req:Caller>
                        <req:KeyOwner>1</req:KeyOwner>
                        <req:Timestamp>20190611103527</req:Timestamp>
                    </req:Header>
                    <!-- Request Body -->
                    <req:Body>
                        <req:Identity>
                            <req:Initiator>
                                <req:IdentifierType>1</req:IdentifierType>
                                <req:Identifier>'.$phone.'</req:Identifier>
                            </req:Initiator>
                            <req:ReceiverParty>
                                <req:IdentifierType>4</req:IdentifierType>
                                <req:Identifier>111135</req:Identifier>
                            </req:ReceiverParty>
                        </req:Identity>
                        <req:TransactionRequest>
                            <req:Parameters>
                                <req:Parameter>
                                    <com:Key>BillReferenceNumber</com:Key>
                                    <com:Value>'.$transaction_ref.'</com:Value>
                                </req:Parameter>
                                <req:Amount>'.$amount.'</req:Amount>
                                <req:Currency>ZMW</req:Currency>
                            </req:Parameters>
                        </req:TransactionRequest>
                    </req:Body>
                </api:Request>
            </soapenv:Body>
        </soapenv:Envelope>';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $prod_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $newXmlPayload,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/soap+xml,application/dime,multipart/related,text/*',
                'Content-Type: text/xml',
                'SOAPAction: ""'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        Log::info("PHONE NUMBER " . $phone . " Zamtel Curl", ["Result" => $response]);

        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);

        // Register SOAP namespace prefix
        $xml->registerXPathNamespace('res', 'http://cps.huawei.com/synccpsinterface/result');

        // Use the registered namespace prefix in XPath query
        $resultCode = (string)$xml->xpath('//res:ResultCode')[0];
        $resultDesc = (string)$xml->xpath('//res:ResultDesc')[0];

        Log::info("PHONE NUMBER " . $phone . " Zamtel SOAP", ["Result Code" => $resultCode]);

        // Check if the response has the expected structure

        if ($resultCode == 0 && $resultCode == "0") {

            $payment = Payment::where('id', $transaction_id)->first();

            $this->createTransactionSparkApi($payment->id, $payment->customer_id, $payment->amount_paid, $transaction_ref, $payment->phone_number);

            Log::info("Zamtel SOAP Success Code ".$resultCode);
            Log::info("Zamtel SOAP Success Description".$resultDesc);

            $update_record = Payment::where('id', $transaction_id)->update([
                'payment_status_id' => 1 //success
            ]);

        } elseif ($resultCode == "E8003") {
            //ResultCode: E8027
            //ResultDesc: System is unable to process your request now, please try later
            //Scenario: Occurs when there is a mismatch with credentials I.e password and thirdPartyID fields.

            Log::info("Zamtel SOAP Error Code ".$resultCode);
            Log::info("Zamtel SOAP Error Description ".$resultDesc);

            $update_record = Payment::where('id', $transaction_id)->update([
                'payment_status_id' => 3, //failed
                'error_message' => $resultDesc
            ]);

        }  elseif ($resultCode == "E8027") {
            //ResultCode: E8027
            //ResultDesc: System is unable to process your request now, please try later
            //Scenario: Occurs when there is a mismatch with credentials I.e password and thirdPartyID fields.

            Log::info("Zamtel SOAP Error Code ".$resultCode);
            Log::info("Zamtel SOAP Error Description ".$resultDesc);

            $update_record = Payment::where('id', $transaction_id)->update([
                'payment_status_id' => 3, //failed
                'error_message' => $resultDesc
            ]);

        } elseif ($resultCode == "2001") {
            //ResultCode: 2001
            //ResultDesc: Initiator authentication error.
            //Scenario: Occurs when there is a mismatch with Initiator credentials i.e Identifier and SecurityCredential fields.

            Log::info("Zamtel SOAP Error Code ".$resultCode);
            Log::info("Zamtel SOAP Error Description ".$resultDesc);

            $update_record = Payment::where('id', $transaction_id)->update([
                'payment_status_id' => 3, //failed
                'error_message' => $resultDesc
            ]);

        } elseif ($resultCode == "2006") {
            //ResultCode: 2001
            //ResultDesc: Initiator authentication error.
            //Scenario: Occurs when there is a mismatch with Initiator credentials i.e Identifier and SecurityCredential fields.

            Log::info("Zamtel SOAP Error Code ".$resultCode);
            Log::info("Zamtel SOAP Error Description Insufficient Balance");

            $update_record = Payment::where('id', $transaction_id)->update([
                'payment_status_id' => 3, //failed
                'error_message' => $resultDesc
            ]);

        }elseif ($resultCode == "-1" && $resultCode == -1) {
            //ResultCode: -1
            //ResultDesc: System internal error.
            //Scenario: Occurs when there is a connection timeout.

            Log::info("Zamtel SOAP Error Code ".$resultCode);
            Log::info("Zamtel SOAP Error Description ".$resultDesc);

            $update_record = Payment::where('id', $transaction_id)->update([
                'payment_status_id' => 3, //failed
                'error_message' => $resultDesc
            ]);

        }else {
            //failed
            Log::info("Zamtel SOAP", ["Failed Response" => $resultCode]);

            $update_record = Payment::where('id', $transaction_id)->update([
                'payment_status_id' => 3, //failed
                'error_message' => $resultDesc
            ]);
        }

        return true;


    }

    function createTransactionSparkApi($payment_id, $customer_id, $amount, $ref_number, $phone_number): void
    {

        Log::info("Spark APP payment id=" . $payment_id . ", customer_id=" . $customer_id . ", amount=" . $amount . ", ref number=" . $ref_number . ", phone number=" . $phone_number);

        $curl = curl_init();

        curl_setopt_array($curl, array(
           // CURLOPT_URL => 'http://sparkapp-staging.spk.io:5010/api/v0/transaction/',
            CURLOPT_URL => 'https://https://rea-location001.sparkmeter.cloud/api/v0/transaction/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'customer_id=' . $customer_id . '&amount=' . $amount . '&source=cash&external_id=' . $ref_number,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authentication-Token: .eJwFwckRgDAIAMBeeMsMIHLU4viAJPRfgrsvlHQRRWFzCGqyY8hTOKaTc6_ZdOCCMPG1-zQnL1LSOpR29LFeKu7w_eZqE5s.ZX__SA.Ob37E1u4ZWoyQPbOajB0i5rTv_M'
                //'Authentication-Token: .eJwNw8kNwDAIBMBeeAfJxuuAa4nyWB_0X0Iy0jxii57HXaNUKhpTIziVYSP-nRVyCQpGrhGZu9vE7c3sAKxn9YIJeT_fFxNO.ZU-0fg.9FQ_6C4Tcv9ue2Tmda_8l3zvqnA'
            ),
        ));

        $response = curl_exec($curl);
        // Get the HTTP status code
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);


        curl_close($curl);
        Log::info("Spark APP", ["Status" => json_encode($http_status_code)]);
        Log::info("Spark App", ["Response" => json_encode($response)]);
        if ($http_status_code == 201) {
            // Decode the JSON response
            $decoded_response = json_decode($response, true);

            // Access 'status' and 'transaction_id'
            $status = $decoded_response['status'];
            $transaction_id = $decoded_response['transaction_id'];

            //Update the payment record
            $update_payment = Payment::where('id', $payment_id)->update([
                'payment_status_id' => 1,
                'spark_transaction_id' => $transaction_id
            ]);

            //send message to the client
            $message_string = "You have successfully topped up your electricity for K".$amount.". Transaction number " . $transaction_id;
            $this->sendMessage($message_string, $phone_number);

        } else {
            // Decode the JSON response
            $decoded_response = json_decode($response, true);

            // Access 'status' and 'transaction_id'
            $status = $decoded_response['status'];
            $error_message = $decoded_response['error'];

            //Update the payment record
            $update_payment = Payment::where('id', $payment_id)->update([
                'payment_status_id' => 2,
                'error_message' => $error_message
            ]);
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
