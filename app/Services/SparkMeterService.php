<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SparkMeterService
{
    private $baseUrl;
    private $apiKey;
    private $apiSecret;
    private $timeout;
    private $retries;

    public function __construct()
    {
        $this->baseUrl = config('services.sparkmeter.url', 'https://www.sparkmeter.cloud/api/v1');
        $this->apiKey = config('services.sparkmeter.key');
        $this->apiSecret = config('services.sparkmeter.secret');
        $this->timeout = config('services.sparkmeter.timeout', 30);
        $this->retries = config('services.sparkmeter.retries', 3);
    }

    /**
     * Make API request with proper error handling
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        try {
            Log::info('SparkMeter: Making API request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => $this->apiKey,
                'X-API-SECRET' => $this->apiSecret
            ])
            ->timeout($this->timeout)
            ->retry($this->retries, 100, function ($exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException
                    || ($exception instanceof \Illuminate\Http\Client\RequestException
                        && $exception->response->status() >= 500);
            });

            if ($method === 'GET') {
                $response = $response->get($this->baseUrl . $endpoint, $data);
            } else {
                $response = $response->post($this->baseUrl . $endpoint, $data);
            }

            Log::info('SparkMeter: Response received', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('SparkMeter: API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $this->getErrorMessage($e)
            ];
        }
    }

    /**
     * Get customer details by customer code
     */
    public function getCustomer($customerCode)
    {
        $response = $this->makeRequest('GET', '/customers', [
            'code' => $customerCode,
            'reading_details' => true
        ]);

        if (!$response['success']) {
            return $response;
        }

        // Extract customer data from response
        $customerData = $response['data']['data'][0] ?? null;
        if (!$customerData) {
            return [
                'success' => false,
                'message' => 'Customer not found'
            ];
        }

        // Format customer data
        return [
            'success' => true,
            'customer' => [
                'id' => $customerData['id'],
                'name' => $customerData['name'],
                'code' => $customerData['code'],
                'phone_number' => $customerData['phone_number'],
                'meter_number' => $customerData['meters'][0]['serial'] ?? null,
                'balance' => $customerData['balances']['credit']['value'] ?? '0.00',
                'currency' => $customerData['balances']['credit']['currency'] ?? 'ZMW',
                'raw_data' => $customerData
            ]
        ];
    }

    /**
     * Get meter balance
     */
    public function getMeterBalance($meterNumber)
    {
        $response = $this->makeRequest('GET', "/meters/{$meterNumber}/balance");

        if (!$response['success']) {
            return $response;
        }

        return [
            'success' => true,
            'balance' => $response['data']['balance'] ?? 0,
            'units' => $response['data']['units'] ?? 'kWh',
            'raw_data' => $response['data']
        ];
    }

    /**
     * Generate credit token
     */
    public function generateToken($meterNumber, $amount)
    {
        $response = $this->makeRequest('POST', '/credits/generate', [
            'meter_number' => $meterNumber,
            'amount' => $amount,
            'currency' => 'ZMW',
            'reference' => 'USSD_' . time() . '_' . rand(1000, 9999)
        ]);

        if (!$response['success']) {
            return $response;
        }

        return [
            'success' => true,
            'token' => $response['data']['token'],
            'units' => $response['data']['units'],
            'amount' => $response['data']['amount'],
            'reference' => $response['data']['reference'],
            'raw_data' => $response['data']
        ];
    }

    /**
     * Verify credit token
     */
    public function verifyToken($tokenNumber)
    {
        return $this->makeRequest('GET', "/credits/{$tokenNumber}");
    }

    /**
     * Get transaction status
     */
    public function getTransactionStatus($reference)
    {
        return $this->makeRequest('GET', "/transactions/{$reference}");
    }

    /**
     * Check API health
     */
    public function checkHealth()
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-API-KEY' => $this->apiKey,
                    'X-API-SECRET' => $this->apiSecret
                ])
                ->get($this->baseUrl . '/health');

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'API is available' : 'API is unavailable'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API is unavailable',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get customer consumption history
     */
    public function getConsumptionHistory($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/consumption");
    }

    /**
     * Get customer payment history
     */
    public function getPaymentHistory($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/payments");
    }

    /**
     * Format error messages
     */
    private function getErrorMessage(\Exception $e)
    {
        if (strpos($e->getMessage(), 'cURL error 28') !== false) {
            return 'Service temporarily slow. Please try again.';
        }

        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'Unable to connect to service. Please try again.';
        }

        return 'Service error. Please try again later.';
    }

    /**
     * Cache customer data temporarily
     */
    public function cacheCustomerData($customerCode, $data, $minutes = 5)
    {
        Cache::put("customer_{$customerCode}", $data, now()->addMinutes($minutes));
    }

    /**
     * Get cached customer data
     */
    public function getCachedCustomerData($customerCode)
    {
        return Cache::get("customer_{$customerCode}");
    }

    /**
     * Clear cached customer data
     */
    public function clearCachedCustomerData($customerCode)
    {
        Cache::forget("customer_{$customerCode}");
    }

     /**
     * Generate a unique external ID for transactions
     */
    private function generateExternalId()
    {
        $prefix = 'ZR'; // Prefix for your transactions
        $date = date('ymd'); // Current date YYMMDD
        $random = strtoupper(Str::random(4)); // Random 4 characters
        return "{$prefix}{$random}-{$date}";
    }

    /**
     * Process payment to SparkMeter
     */
    public function processPayment($customerCode, $amount, $memo = 'Electricity purchase')
    {
        try {
            $externalId = $this->generateExternalId();

            Log::info('Processing SparkMeter payment', [
                'customer_code' => $customerCode,
                'amount' => $amount,
                'external_id' => $externalId
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => $this->apiKey,
                'X-API-SECRET' => $this->apiSecret
            ])->post($this->baseUrl . '/credits', [
                'amount' => (string)$amount,
                'memo' => $memo,
                'customer_code' => $customerCode,
                'external_id' => $externalId
            ]);

            Log::info('SparkMeter payment response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['errors'])) {
                    return $this->handleApiError($data['errors']);
                }

                if (isset($data['data'])) {
                    return [
                        'success' => true,
                        'transaction_id' => $data['data']['id'],
                        'recipient_id' => $data['data']['recipient_id'],
                        'amount' => $data['data']['amount']['value'],
                        'currency' => $data['data']['amount']['currency'],
                        'status' => $data['data']['status'],
                        'external_id' => $data['data']['external_id']
                    ];
                }
            }

            return $this->handleApiError($response->json()['errors'] ?? []);

        } catch (\Exception $e) {
            Log::error('Payment processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle API errors
     */
    private function handleApiError($errors)
    {
        $error = $errors[0] ?? null;
        if (!$error) {
            return ['success' => false, 'message' => 'Unknown error occurred'];
        }

        Log::warning('SparkMeter API error', ['error' => $error]);

        switch ($error['title']) {
            case 'Authentication Error':
                return [
                    'success' => false,
                    'message' => 'Service temporarily unavailable',
                    'error_type' => 'auth',
                    'details' => $error['details']
                ];

            case 'Forbidden':
                return [
                    'success' => false,
                    'message' => 'Service access denied',
                    'error_type' => 'forbidden',
                    'details' => $error['details']
                ];

            case 'Resource Not Found':
                return [
                    'success' => false,
                    'message' => 'Invalid customer information',
                    'error_type' => 'not_found',
                    'details' => $error['details']
                ];

            case 'Rate Limit Exceeded':
                return [
                    'success' => false,
                    'message' => 'Service busy, please try again',
                    'error_type' => 'rate_limit',
                    'details' => $error['details']
                ];

            default:
                return [
                    'success' => false,
                    'message' => 'Payment processing failed',
                    'error_type' => 'unknown',
                    'details' => $error['details'] ?? 'Unknown error'
                ];
        }
    }
}
