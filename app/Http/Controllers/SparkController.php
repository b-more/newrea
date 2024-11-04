<?php
// app/Http/Controllers/SparkController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SparkController extends Controller
{
    /**
     * Test customer lookup
     */
    public function lookup($code)
    {
        try {
            Log::info('SparkMeter: Starting lookup', [
                'code' => $code,
                'api_key' => config('services.sparkmeter.key'),
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => config('services.sparkmeter.key'),
                'X-API-SECRET' => config('services.sparkmeter.secret')
            ])->get('https://www.sparkmeter.cloud/api/v1/customers', [
                'code' => $code,
                'reading_details' => true
            ]);

            Log::info('SparkMeter: Response received', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'data' => $response->json()
            ]);

        } catch (\Exception $e) {
            Log::error('SparkMeter: Error occurred', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'API request failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get meter balance
     */
    public function balance($meterNumber)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => config('services.sparkmeter.key'),
                'X-API-SECRET' => config('services.sparkmeter.secret')
            ])->get('https://www.sparkmeter.cloud/api/v1/meters/' . $meterNumber . '/balance');

            return response()->json([
                'success' => true,
                'data' => $response->json()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Balance check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate token
     */
    public function generateToken(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => config('services.sparkmeter.key'),
                'X-API-SECRET' => config('services.sparkmeter.secret')
            ])->post('https://www.sparkmeter.cloud/api/v1/credits/generate', [
                'meter_number' => $request->meter_number,
                'amount' => $request->amount,
                'reference' => 'USSD_' . time()
            ]);

            return response()->json([
                'success' => true,
                'data' => $response->json()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
