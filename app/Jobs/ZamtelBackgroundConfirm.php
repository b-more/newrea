<?php

namespace App\Jobs;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZamtelBackgroundConfirm implements ShouldQueue
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

        Log::info("Zamtel USSD Background started for PhoneNumber: ".$initialised_transaction->phone_number.", Amount: " .$initialised_transaction->amount_paid);

        $this->zamtel_soap($initialised_transaction->phone_number, $initialised_transaction->amount_paid, $initialised_transaction->id, $initialised_transaction->payment_reference_number);
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

        Log::info("Zamtel USSD Payment Started - PHONE NUMBER: " . $phone . "; AMOUNT: " . $amount.", TIMESTAMP ".$timestamp);

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
                // Specify the appropriate SOAPAction value if required by the service
                'SOAPAction: ""',
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

        Log::info("Spark APP payment id=".$payment_id.", customer_id=".$customer_id.", amount=".$amount.", ref number=".$ref_number.", phone number=".$phone_number);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            //CURLOPT_URL => 'http://sparkapp-staging.spk.io:5010/api/v0/transaction/',
            CURLOPT_URL => 'https://rea-location001.sparkmeter.cloud/api/v0/transaction/',
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
            //$message_string = "You have successfully topped up your electricity. Txn number ".$transaction_id;
            $message_string = "You have successfully topped up your electricity for K".$amount.". Transaction number " . $transaction_id;
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

    function sendMessage($message_string, $phone_number): void
    {
        //Next auto response
        $url_encoded_message = urlencode($message_string);

        //Next auto response
        $sendSenderSMS = Http::withoutVerifying()
            ->post('https://www.cloudservicezm.com/smsservice/httpapi?username=Blessmore&password=Blessmore&msg=' . $url_encoded_message . '.+&shortcode=2343&sender_id=REA&phone=' . $phone_number . '&api_key=121231313213123123');

        //return $sendSenderSMS->body();
        Log::info("SMS notification sent to ".$phone_number);
    }
}
