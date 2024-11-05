<!--
    // function zamtel_soap($phone, $amount, $transaction_id, $transaction_ref)
    // {
    //     try {
    //         $current_date = Carbon::now();
    //         $timestamp = $current_date->format('YmdHis');
    //         $password = "YEGxyU69L8lHXjHUJe1SNk3MwOMTxQG9";
    //         $identifier = "111135";
    //         $prod_url = "http://172.18.0.133:7661/payment/services/SYNCAPIRequestMgrService/";

    //         Log::info("Zamtel Web Started", [
    //             'phone' => $phone,
    //             'amount' => $amount,
    //             'timestamp' => $timestamp
    //         ]);

    //         $newXmlPayload = '<?xml version="1.0" encoding="UTF-8"?>
    //         <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
    //         xmlns:api="http://cps.huawei.com/synccpsinterface/api_requestmgr"
    //         xmlns:req="http://cps.huawei.com/synccpsinterface/request"
    //         xmlns:com="http://cps.huawei.com/synccpsinterface/common"
    //         xmlns:cus="http://cps.huawei.com/cpsinterface/customizedrequest">
    //             <soapenv:Body>
    //                 <api:Request>
    //                     <req:Header>
    //                         <req:Version>1.0</req:Version>
    //                         <req:CommandID>InitTrans_Customer Pay Organization Bill</req:CommandID>
    //                         <req:OriginatorConversationID>429106433679961234</req:OriginatorConversationID>
    //                         <req:Caller>
    //                             <req:CallerType>2</req:CallerType>
    //                             <req:ThirdPartyID>REA_Caller</req:ThirdPartyID>
    //                             <req:Password>' . $password . '</req:Password>
    //                         </req:Caller>
    //                         <req:KeyOwner>1</req:KeyOwner>
    //                         <req:Timestamp>' . $timestamp . '</req:Timestamp>
    //                     </req:Header>
    //                     <req:Body>
    //                         <req:Identity>
    //                             <req:Initiator>
    //                                 <req:IdentifierType>1</req:IdentifierType>
    //                                 <req:Identifier>' . $phone . '</req:Identifier>
    //                             </req:Initiator>
    //                             <req:ReceiverParty>
    //                                 <req:IdentifierType>4</req:IdentifierType>
    //                                 <req:Identifier>' . $identifier . '</req:Identifier>
    //                             </req:ReceiverParty>
    //                         </req:Identity>
    //                         <req:TransactionRequest>
    //                             <req:Parameters>
    //                                 <req:Parameter>
    //                                     <com:Key>BillReferenceNumber</com:Key>
    //                                     <com:Value>' . $transaction_ref . '</com:Value>
    //                                 </req:Parameter>
    //                                 <req:Amount>' . $amount . '</req:Amount>
    //                                 <req:Currency>ZMW</req:Currency>
    //                             </req:Parameters>
    //                         </req:TransactionRequest>
    //                     </req:Body>
    //                 </api:Request>
    //             </soapenv:Body>
    //         </soapenv:Envelope>';

    //         $curl = curl_init();

    //         curl_setopt_array($curl, array(
    //             CURLOPT_URL => $prod_url,
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_ENCODING => '',
    //             CURLOPT_MAXREDIRS => 10,
    //             CURLOPT_TIMEOUT => 0,
    //             CURLOPT_FOLLOWLOCATION => true,
    //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST => 'POST',
    //             CURLOPT_POSTFIELDS => $newXmlPayload,
    //             CURLOPT_HTTPHEADER => array(
    //                 'Accept: application/soap+xml,application/dime,multipart/related,text/*',
    //                 'Content-Type: text/xml',
    //                 'SOAPAction: ""'
    //             ),
    //         ));

    //         $response = curl_exec($curl);

    //         // Check for curl errors
    //         if (curl_errno($curl)) {
    //             Log::error("Zamtel SOAP Curl Error", [
    //                 'error' => curl_error($curl),
    //                 'errno' => curl_errno($curl)
    //             ]);
    //             curl_close($curl);
    //             return false;
    //         }

    //         $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    //         curl_close($curl);

    //         Log::info("Zamtel SOAP Response", [
    //             'http_code' => $http_code,
    //             'response' => $response
    //         ]);

    //         // Check if response is empty
    //         if (empty($response)) {
    //             Log::error("Zamtel SOAP Empty Response");
    //             return false;
    //         }

    //         // Try to parse XML response
    //         libxml_use_internal_errors(true);
    //         $xml = simplexml_load_string($response);

    //         if ($xml === false) {
    //             $errors = libxml_get_errors();
    //             Log::error("Zamtel SOAP XML Parse Error", [
    //                 'errors' => $errors,
    //                 'response' => $response
    //             ]);
    //             libxml_clear_errors();
    //             return false;
    //         }

    //         // Register namespace and get result code
    //         try {
    //             $xml->registerXPathNamespace('res', 'http://cps.huawei.com/synccpsinterface/result');
    //             $resultCodeNodes = $xml->xpath('//res:ResultCode');
    //             $resultDescNodes = $xml->xpath('//res:ResultDesc');

    //             if (empty($resultCodeNodes)) {
    //                 Log::error("Zamtel SOAP No Result Code Found", [
    //                     'response' => $response
    //                 ]);
    //                 return false;
    //             }

    //             $resultCode = (string)$resultCodeNodes[0];
    //             $resultDesc = !empty($resultDescNodes) ? (string)$resultDescNodes[0] : '';

    //             Log::info("Zamtel SOAP Result", [
    //                 'code' => $resultCode,
    //                 'description' => $resultDesc
    //             ]);

    //             // Handle different result codes
    //             switch ($resultCode) {
    //                 case "0":
    //                     $payment = Payment::find($transaction_id);
    //                     if ($payment) {
    //                         // Update payment status to success
    //                         $payment->update([
    //                             'payment_status_id' => 1
    //                         ]);

    //                         // Send success notification
    //                         $this->sendNotification($phone,
    //                             "Payment request initiated. Please enter your Zamtel Money PIN to complete the transaction."
    //                         );
    //                     }
    //                     return true;

    //                 case "E8003":
    //                 case "E8027":
    //                 case "2001":
    //                 case "2006":
    //                 case "-1":
    //                     Payment::where('id', $transaction_id)->update([
    //                         'payment_status_id' => 3,
    //                         'error_message' => $resultDesc
    //                     ]);
    //                     return false;

    //                 default:
    //                     Payment::where('id', $transaction_id)->update([
    //                         'payment_status_id' => 3,
    //                         'error_message' => $resultDesc
    //                     ]);
    //                     return false;
    //             }

    //         } catch (\Exception $e) {
    //             Log::error("Zamtel SOAP XML Processing Error", [
    //                 'error' => $e->getMessage(),
    //                 'trace' => $e->getTraceAsString()
    //             ]);
    //             return false;
    //         }

    //     } catch (\Exception $e) {
    //         Log::error("Zamtel SOAP General Error", [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return false;
    //     }
    // } -->
