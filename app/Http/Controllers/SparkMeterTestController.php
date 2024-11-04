<?php
// app/Http/Controllers/Api/SparkMeterTestController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SparkMeterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SparkMeterTestController extends Controller
{
    protected $sparkMeter;

    public function __construct(SparkMeterService $sparkMeter)
    {
        $this->sparkMeter = $sparkMeter;
    }

    public function testCustomerLookup($code)
    {
        try {
            Log::info('SparkMeter Test: Starting lookup', ['code' => $code]);

            $result = $this->sparkMeter->testCustomerLookup($code);

            Log::info('SparkMeter Test: Lookup result', ['result' => $result]);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('SparkMeter Test: Error occurred', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
