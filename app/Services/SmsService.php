<?php

// app/Services/SmsService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    public static function send(string $message, string $phone_number): void
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
                'phone' => $phone_number,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
