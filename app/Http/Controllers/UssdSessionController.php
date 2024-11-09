<?php

namespace App\Http\Controllers;

use App\Jobs\AirtelBackgroundIntent;
use App\Jobs\BackgroundTransactionConfirm;
use App\Jobs\ZamtelBackgroundConfirm;
use App\Models\Agent;
use App\Models\AgentActivityLog;
use App\Models\Customer;
use App\Models\UssdSession;
use App\Models\Complaint;
use App\Models\CustomerFeedback;
use App\Models\GeneralInquiry;
use App\Models\Language;
use App\Models\Payment;
use App\Models\FloatTransaction;
use Carbon\Carbon;
use Hamcrest\Type\IsNumeric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\SparkMeterService;

class UssdSessionController extends Controller
{
    protected $sparkMeter;

    public function __construct(SparkMeterService $sparkMeter)
    {
        $this->sparkMeter = $sparkMeter;
    }

    const COMPLAINT_STATUSES = [
        1 => [
            'en' => 'Open/New',
            'ln' => 'Wushili/Wahimpya'
        ],
        2 => [
            'en' => 'Assigned to Technician',
            'ln' => 'Chayinka kudi Technician'
        ],
        3 => [
            'en' => 'Under Investigation',
            'ln' => 'Chinatalishiwa'
        ],
        4 => [
            'en' => 'Technician Dispatched',
            'ln' => 'Technician nasendewa'
        ],
        5 => [
            'en' => 'Awaiting Parts',
            'ln' => 'Kulombela yuma yakuzatisha'
        ],
        6 => [
            'en' => 'In Progress',
            'ln' => 'Chinazatishiwa'
        ],
        7 => [
            'en' => 'Resolved',
            'ln' => 'Chamani'
        ],
        8 => [
            'en' => 'Pending Customer Feedback',
            'ln' => 'Kuindila kukula kudi akakulanda'
        ],
        9 => [
            'en' => 'Closed',
            'ln' => 'Chamana'
        ],
        10 => [
            'en' => 'Reopened',
            'ln' => 'Chanokihu choshi'
        ]
    ];

    private function updateSession($sessionId, $caseNo, $stepNo, array $additionalData = []): void
    {
        try {
            $updateData = array_merge([
                'case_no' => $caseNo,
                'step_no' => $stepNo,
                'updated_at' => now()
            ], $additionalData);

            UssdSession::where('session_id', $sessionId)->update($updateData);

            Log::info('Session updated', [
                'session_id' => $sessionId,
                'case_no' => $caseNo,
                'step_no' => $stepNo,
                'additional_data' => $additionalData
            ]);
        } catch (\Exception $e) {
            Log::error('Session update failed', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'data' => $updateData
            ]);
            throw $e;
        }
    }

    function generateFeedbackNumber()
    {

        $prefix = 'FB'; // Prefix for the inquiry number

        // Generate a random number between 1000000 and 9999999
        $random = rand(1000000, 9999999); // 1000456

        // Combine the prefix, random number, and suffix to form the account number
        $feedback_number = $prefix . $random;  // CN1000456

        // Check if the inquiry number already exists in the database
        if (DB::table('customer_feedbacks')->where('feedback_number', $feedback_number)->exists()) {
            // If the inquiry already exists, generate a new one recursively
            return $this->generateFeedbackNumber();
        }

        return $feedback_number;
    }

    private function sendSmsNotification($phone, $message)
    {
        try {
            Log::info('Sending SMS Notification', [
                'phone' => $phone,
                'message' => $message
            ]);

            $url_encoded_message = urlencode($message);
            $url = 'https://www.cloudservicezm.com/smsservice/httpapi?' .
                'username=Blessmore&password=Blessmore&msg=' . $url_encoded_message .
                '.+&shortcode=2343&sender_id=REAPAY&phone=' . $phone .
                '&api_key=121231313213123123';

            Log::info('SMS API Request', [
                'url' => $url
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            Log::info('SMS API Response', [
                'http_code' => $http_code,
                'response' => $response,
                'phone' => $phone
            ]);

            if ($http_code != 200) {
                throw new \Exception('SMS API returned non-200 status code: ' . $http_code);
            }

            curl_close($ch);
            return true;

        } catch (\Exception $e) {
            Log::error('SMS Sending Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phone
            ]);
            return false;
        }
    }

    public function handleUssd(Request $request)
    {
        try {
            // Initialize variables
        $message_string = "";
        $case_no = 1;
        $step_no = 1;
        $phone = $request->MSISDN;
        $user_input = $request->MESSAGE;
        $session_id = $request->SESSION_ID;
        $lastPart = explode("*", $user_input);
        $parts = count($lastPart);
        $last_part = $lastPart[$parts - 1];
        $request_type = "2"; // Continue

        // Log USSD request
        Log::info("USSD Request", [
            "raw" => $user_input,
            "last_part" => $last_part
        ]);

        // Check if input is just "*" (single asterisk)
        if (trim($user_input) === "*") {
            $this->updateSession($session_id, 1, 1);
            $message_string = $this->getMainMenu();
            return $this->formatResponse($message_string, $request_type);
        }

        // Handle main menu navigation from any step
        if ($last_part === "" && substr($user_input, -1) === "*") {
            $this->updateSession($session_id, 1, 1);
            $message_string = $this->getMainMenu();
            return $this->formatResponse($message_string, $request_type);
        }

        // Check for existing session
        $getLastSessionInfo = UssdSession::where('phone_number', $phone)
            ->where('session_id', $session_id)
            ->orderBy('id', 'DESC')
            ->first();

        // If session exists, continue from where user left off
        if (!empty($getLastSessionInfo)) {
            $case_no = $getLastSessionInfo->case_no;
            $step_no = $getLastSessionInfo->step_no;
            $merchant_code = $getLastSessionInfo->merchant_code ?? null;
            $customer_id = $getLastSessionInfo->customer_id ?? null;
            $agent_id = $getLastSessionInfo->agent_id ?? null;

            // Handle "0" for going back
            if ($last_part === '0') {
                // Go back one step
                if ($step_no > 1) {
                    $step_no--;
                    $this->updateSession($session_id, $case_no, $step_no);
                    $message_string = $this->getPreviousMenu($case_no, $step_no, $getLastSessionInfo);
                    return $this->formatResponse($message_string, $request_type);
                } else if ($case_no != 1) {
                    // If at first step of a case, go back to main menu
                    $this->updateSession($session_id, 1, 1);
                    $message_string = $this->getMainMenu();
                    return $this->formatResponse($message_string, $request_type);
                }
            }
        } else {
            // Create new session for standard USSD journey
            UssdSession::create([
                "phone_number" => $phone,
                "case_no" => 1,
                "step_no" => 1,
                "session_id" => $session_id
            ]);
        }

        // Main USSD flow logic
        switch ($case_no) {
            case 1: // Main Menu
                if ($step_no == 1) {
                    $message_string = $this->getMainMenu();
                    $this->updateSession($session_id, 1, 2);
                } elseif ($step_no == 2 && is_numeric($last_part)) {
                    switch ($last_part) {
                        case 1:
                            $message_string = "Enter your Customer ID:\n\n0. Back";
                            $this->updateSession($session_id, 2, 1);
                            break;
                        case 2:
                            $message_string = "Enter your Customer ID:\n\n0. Back";
                            $this->updateSession($session_id, 3, 1);
                            break;
                        case 3:
                            $message_string = "Enter Transaction ID:\n\n0. Back";
                            $this->updateSession($session_id, 4, 1);
                            break;
                        case 4: // Customer Desk - Start with Language Selection
                            $geLanguages = Language::where('is_active', 1)->get();
                            $language_menu = "Choose language / Sakulenu lizu\n\n";
                            foreach ($geLanguages as $index => $language) {
                                $language_menu .= ($index + 1) . ". " . $language->name . "\n";
                            }
                            $language_menu .= "\n0. Back";
                            $message_string = $language_menu;
                            $this->updateSession($session_id, 11, 1); // New case number for language selection
                            break;
                        case 5:
                            if ($this->isWhitelistedAgent($phone)) {
                                $message_string = "Enter your PIN:\n\n\n0. Back";
                                $this->updateSession($session_id, 6, 1);
                            } else {
                                $message_string = "Unauthorized access. Contact support.";
                                $request_type = "3";
                                $this->sendNotification($phone, $message_string);
                            }
                            break;
                        default:
                            $message_string = "Invalid option. Please try again.\n\n*. Main Menu";
                    }
                }
                break;

            case 2: // Buy Electricity Flow
                if ($step_no == 1) {
                    $customer = $this->validateCustomer($last_part);
                    if ($customer) {
                        $result = $this->sparkMeter->processPayment($customer->customer_number,  $amount,
                            "USSD Electricity Purchase"
                        );

                        if ($result['success']) {
                            $payment = Payment::create([
                                'phone_number' => $phone,
                                'meter_number' => $customer->meter_number,
                                'customer_id' => $customer->id,
                                'amount_paid' => $result['amount'],
                                'payment_status_id' => 1,
                                'payment_reference_number' => $result['external_id'],
                                'transaction_id' => $result['transaction_id']
                            ]);

                            $message_string = "Payment Successful!\n" .
                                            "Amount: " . $result['currency'] . " " . $result['amount'] . "\n" .
                                            "Reference: " . $result['external_id'] . "\n" .
                                            "Status: " . ucfirst($result['status']) . "\n";

                            $this->sendNotification($phone, $message_string);
                        } else {
                            $message_string = $result['message'] . "\n";
                            $this->sendNotification($phone, $message_string);
                        }
                        $request_type = "3";
                    } else {
                        $message_string = "Invalid customer code. Try again:\n";
                    }
                }
                break;

            case 3: // View Balance
                if ($step_no == 1) {
                    try {
                        Log::info('Processing balance check request', [
                            'input' => $last_part
                        ]);

                        $customer = $this->validateCustomer($last_part);
                        if ($customer) {
                            $balanceCheck = $this->checkCustomerBalance($customer->customer_number);

                            if ($balanceCheck['success']) {
                                $message_string = "Customer: " . $customer->name .
                                                "\nMeter: " . $customer->meter_number .
                                                "\nBalance: " . $balanceCheck['currency'] . " " .
                                                $balanceCheck['balance'] .
                                                "\n\n*. Main Menu";

                                $this->sendNotification($phone, $message_string);
                            } else {
                                $message_string = "Failed to check balance. Please try again later.\n0. Back\n*. Main Menu";
                            }
                            $request_type = "3";
                        } else {
                            $message_string = "Invalid customer code. Try again:\n0. Back\n*. Main Menu";
                        }
                    } catch (\Exception $e) {
                        Log::error('Balance check error', [
                            'error' => $e->getMessage()
                        ]);
                        $message_string = "Service temporarily unavailable. Please try again later.\n0. Back\n*. Main Menu";
                        $request_type = "3";
                    }
                }
                break;

            case 4: // Query Transaction
                if ($step_no == 1) {
                    $payment = Payment::where('payment_reference_number', $last_part)->first();

                    if ($payment) {
                        $message_string = "Transaction Details:\n" .
                                        "Amount: K" . number_format($payment->amount_paid, 2) . "\n" .
                                        "Reference: " . $payment->payment_reference_number . "\n" .
                                        "Date: " . $payment->created_at->format('Y-m-d H:i:s') . "\n" .
                                        "Status: Success\n" .
                                        "Meter: " . $payment->meter_number .
                                        "\n\n*. Main Menu";
                        $this->sendNotification($phone, $message_string);
                    } else {
                        $message_string = "Transaction not found\n0. Back\n*. Main Menu";
                    }
                    $request_type = "3";
                }
                break;

            case 5: // Customer Desk
                if ($step_no == 1 && is_numeric($last_part)) {
                    switch ($last_part) {
                        case 1: // Report Blackout
                            $message_string = "Enter your meter number:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 5, 2);
                            break;
                        case 2: // Report Fault
                            $message_string = "Enter your meter number:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 5, 3);
                            break;
                        case 3: // General Inquiry
                            $message_string = "Type your inquiry:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 5, 4);
                            break;
                        default:
                            $message_string = "Invalid option\n0. Back\n*. Main Menu";
                    }
                } elseif ($step_no == 2) { // Blackout Report
                    $meter = $this->validateMeterNumber($last_part);
                    if ($meter) {
                        $complaint_number = $this->generateComplaintNumber();
                        Complaint::create([
                            'complaint_number' => $complaint_number,
                            'type' => 'blackout',
                            'meter_number' => $last_part,
                            'customer_id' => $meter->customer_id,
                            'status' => 'pending'
                        ]);
                        $message_string = "Blackout report submitted successfully.\n" .
                                        "Reference: " . $complaint_number .
                                        "\n\n*. Main Menu";
                        $this->sendNotification($phone, "Your blackout report has been received. Reference: " . $complaint_number);
                        $request_type = "3";
                    } else {
                        $message_string = "Invalid meter number. Try again:\n0. Back\n*. Main Menu";
                    }
                } elseif ($step_no == 3) { // Fault Report
                    $meter = $this->validateMeterNumber($last_part);
                    if ($meter) {
                        $message_string = "Select fault type:\n" .
                                        "1. No Power\n" .
                                        "2. Meter Error\n" .
                                        "3. Connection Issue\n" .
                                        "4. Other\n" .
                                        "0. Back\n*. Main Menu";
                        $this->updateSession($session_id, 5, 5, ['meter_number' => $last_part]);
                    } else {
                        $message_string = "Invalid meter number. Try again:\n0. Back\n*. Main Menu";
                    }
                } elseif ($step_no == 4) { // General Inquiry
                    $inquiry_number = $this->generateInquiryNumber();
                    GeneralInquiry::create([
                        'inquiry_number' => $inquiry_number,
                        'phone_number' => $phone,
                        'message' => $last_part,
                        'status' => 'pending'
                    ]);
                    $message_string = "Inquiry submitted successfully.\n" .
                                    "Reference: " . $inquiry_number .
                                    "\n\n*. Main Menu";
                    $this->sendNotification($phone, "Your inquiry has been received. Reference: " . $inquiry_number);
                    $request_type = "3";
                } elseif ($step_no == 5 && is_numeric($last_part)) { // Fault Type Selection
                    $session = UssdSession::where('session_id', $session_id)->first();
                    $fault_types = ['No Power', 'Meter Error', 'Connection Issue', 'Other'];
                    if ($last_part >= 1 && $last_part <= 4) {
                        $complaint_number = $this->generateComplaintNumber();
                        Complaint::create([
                            'complaint_number' => $complaint_number,
                            'type' => 'fault',
                            'meter_number' => $session->meter_number,
                            'fault_type' => $fault_types[$last_part - 1],
                            'status' => 'pending'
                        ]);
                        $message_string = "Fault report submitted successfully.\n" .
                                        "Reference: " . $complaint_number .
                                        "\n\n*. Main Menu";
                        $this->sendNotification($phone, "Your fault report has been received. Reference: " . $complaint_number);
                        $request_type = "3";
                    } else {
                        $message_string = "Invalid option. Try again:\n0. Back\n*. Main Menu";
                    }
                }
                break;

            case 6: // Agent Login Flow
                if ($step_no == 1) {
                    $agent = $this->validateAgentPin($phone, $last_part);
                    if ($agent) {
                        $message_string = "Welcome " . $agent->business_name .
                                        "\n1. Sell Electricity" .
                                        "\n2. Check Float" .
                                        "\n3. Buy Float" .
                                        "\n4. Change PIN" .
                                        "\n0. Back\n*. Main Menu";
                        $this->updateSession($session_id, 6, 2, ['agent_id' => $agent->id]);
                        $this->logAgentActivity($agent->id, 'login', 'success');
                    } else {
                        $message_string = "Invalid PIN. Try again:\n0. Back\n*. Main Menu";
                        $this->logAgentActivity(null, 'login', 'failed');
                        $this->sendNotification($phone, $message_string);
                    }
                } elseif ($step_no == 2 && is_numeric($last_part)) {
                    switch ($last_part) {
                        case 1: // Sell Electricity
                            $message_string = "Enter customer code:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 7, 1);
                            break;
                        case 2: // Check Float
                            $session = UssdSession::where('session_id', $session_id)->first();
                            $agent = Agent::find($session->agent_id);
                            $message_string = "Your float balance: K" . number_format($agent->float_balance, 2) .
                                            "\n\n0. Back\n*. Main Menu";
                            $request_type = "3";
                            break;
                        case 3: // Buy Float
                            $message_string = "Enter amount to purchase:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 8, 1);
                            break;
                        case 4: // Change PIN
                            $message_string = "Enter your current PIN:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 10, 1);
                            break;
                        default:
                            $message_string = "Invalid option. Try again:\n0. Back\n*. Main Menu";
                    }
                }
                break;

                case 7: // Agent Electricity Sale
                    if ($step_no == 1) {
                        try {
                            Log::info('Processing customer validation', [
                                'input' => $last_part,
                                'session_id' => $session_id
                            ]);

                            // Validate customer using SparkMeter
                            $customerValidation = $this->sparkMeter->validateCustomer($last_part);

                            if (!$customerValidation['success']) {
                                Log::warning('Customer validation failed', [
                                    'customer_code' => $last_part,
                                    'message' => $customerValidation['message']
                                ]);
                                $message_string = "Invalid customer code. Try again:\n0. Back\n*. Main Menu";
                                break;
                            }

                            $customerData = $customerValidation['customer'];

                            // Get agent details
                            $session = UssdSession::where('session_id', $session_id)->first();
                            $agent = Agent::find($session->agent_id);

                            if (!$agent) {
                                throw new \Exception('Agent not found');
                            }

                            if ($agent->float_balance <= 0) {
                                $message_string = "Insufficient float balance. Please top up first.\n0. Back\n*. Main Menu";
                                $request_type = "3";
                                break;
                            }

                            // Store customer details in session
                            $sessionData = [
                                'customer_code' => $customerData['code'],
                                'customer_name' => $customerData['name'],
                                'meter_number' => $customerData['meter_number'],
                                'customer_phone' => $customerData['phone_number'],
                                'transaction_data' => [
                                    'customer_balance' => $customerData['balance'],
                                    'customer_id' => $customerData['id'],
                                    'validation_time' => now()->toIso8601String()
                                ]
                            ];

                            $this->updateSession($session_id, 7, 2, $sessionData);

                            $message_string = "Customer Details:\n" .
                                            "Name: " . $customerData['name'] . "\n" .
                                            "Meter: " . $customerData['meter_number'] . "\n" .
                                            "Balance: K" . $customerData['balance'] . "\n" .
                                            "Your Float: K" . number_format($agent->float_balance, 2) . "\n\n" .
                                            "Enter amount:\n0. Back\n*. Main Menu";

                        } catch (\Exception $e) {
                            Log::error('Customer validation error', [
                                'error' => $e->getMessage(),
                                'input' => $last_part,
                                'session_id' => $session_id
                            ]);

                            $message_string = "Service error. Please try again.\n0. Back\n*. Main Menu";
                        }
                    }
                    elseif ($step_no == 2 && is_numeric($last_part)) {
                        try {
                            $session = UssdSession::where('session_id', $session_id)->first();
                            $agent = Agent::find($session->agent_id);
                            $amount = (float)$last_part;

                            // Validate amount
                            if ($amount <= 0) {
                                $message_string = "Invalid amount. Try again:\n0. Back\n*. Main Menu";
                                break;
                            }

                            // Validate against float balance
                            if ($amount > $agent->float_balance) {
                                $message_string = "Amount exceeds float balance (K" .
                                                number_format($agent->float_balance, 2) .
                                                "). Try again:\n0. Back\n*. Main Menu";
                                break;
                            }

                            // Store amount in session
                            $this->updateSession($session_id, 7, 3, [
                                'amount' => $amount,
                                'transaction_data' => array_merge(
                                    $session->transaction_data ?? [],
                                    ['amount_validation_time' => now()->toIso8601String()]
                                )
                            ]);

                            // Show confirmation message
                            $message_string = "Confirm payment:\n" .
                                            "Amount: K" . number_format($amount, 2) . "\n" .
                                            "Customer: " . $session->customer_name . "\n" .
                                            "Meter: " . $session->meter_number . "\n\n" .
                                            "1. Confirm\n2. Cancel\n0. Back\n*. Main Menu";

                        } catch (\Exception $e) {
                            Log::error('Amount validation error', [
                                'error' => $e->getMessage(),
                                'session_id' => $session_id,
                                'amount' => $last_part ?? null
                            ]);
                            $message_string = "Service error. Please try again.\n0. Back\n*. Main Menu";
                        }
                    }
                    elseif ($step_no == 3 && is_numeric($last_part)) {
                        if ($last_part == 1) {
                            try {
                                $session = UssdSession::where('session_id', $session_id)->first();

                                // Process payment through SparkMeter
                                $result = $this->sparkMeter->processPayment(
                                    $session->customer_code,
                                    $session->amount,
                                    "USSD Agent Sale - {$session->meter_number}"
                                );

                                if ($result['success']) {
                                    // Deduct from agent float
                                    $agent = Agent::find($session->agent_id);
                                    $newBalance = $agent->float_balance - $session->amount;

                                    $agent->update([
                                        'float_balance' => $newBalance,
                                        'last_transaction_at' => now()
                                    ]);

                                    // Create float transaction
                                    FloatTransaction::create([
                                        'agent_id' => $session->agent_id,
                                        'amount' => $session->amount,
                                        'type' => 'debit',
                                        'reference_number' => $result['external_id'],
                                        'payment_method' => 'ussd',
                                        'status' => 'completed',
                                        'description' => "Electricity sale via USSD",
                                        'balance_before' => $agent->float_balance,
                                        'balance_after' => $newBalance,
                                        'processed_by' => $session->agent_id,
                                        'processed_at' => now()
                                    ]);

                                    // Create payment record
                                    Payment::create([
                                        'agent_id' => $session->agent_id,
                                        'amount_paid' => $session->amount,
                                        'payment_reference_number' => $result['external_id'],
                                        'payment_method' => 'Agent Float',
                                        'payment_network' => 'Agent',
                                        'payment_status' => 'completed',
                                        'payment_status_id' => 1,
                                        'route' => 'USSD',
                                        'type' => 'Credit',
                                        'transaction_id' => $result['transaction_id'],
                                        'phone_number' => $session->customer_phone,
                                        'meter_number' => $session->meter_number,
                                        'processed_by' => $session->agent_id,
                                        'remarks' => 'USSD Agent Sale'
                                    ]);

                                    // Success message
                                    $message_string = "Payment Successful!\n" .
                                                    "Amount: K" . number_format($session->amount, 2) . "\n" .
                                                    "Token: " . ($result['token'] ?? 'Processing') . "\n" .
                                                    "Ref: " . $result['external_id'] . "\n\n" .
                                    "*. Main Menu";

                                    // Send customer notification
                                    if ($session->customer_phone) {
                                        $this->sendSmsNotification(
                                            "260" . ltrim($session->customer_phone, '+260'),
                                            "Your electricity payment was successful.\n" .
                                            "Amount: K" . number_format($session->amount, 2) . "\n" .
                                            "Token: " . ($result['token'] ?? 'Processing') . "\n" .
                                            "Ref: " . $result['external_id']
                                        );
                                    }

                                    // Send agent notification
                                    $this->sendSmsNotification(
                                        "260" . ltrim($agent->agent_phone_number, '+260'),
                                        "Sale successful!\n" .
                                        "Amount: K" . number_format($session->amount, 2) . "\n" .
                                        "New Float: K" . number_format($newBalance, 2) . "\n" .
                                        "Ref: " . $result['external_id']
                                    );

                                } else {
                                    $message_string = "Payment failed: " . ($result['message'] ?? 'Unknown error') .
                                                    "\n0. Back\n*. Main Menu";
                                }
                            } catch (\Exception $e) {
                                Log::error('Payment processing error', [
                                    'error' => $e->getMessage(),
                                    'session_id' => $session_id,
                                    'trace' => $e->getTraceAsString()
                                ]);
                                $message_string = "Payment failed. Please try again later.\n0. Back\n*. Main Menu";
                            }
                        } else {
                            $message_string = "Transaction cancelled.\n*. Main Menu";
                        }
                        $request_type = "3";
                    }
                    break;


            case 8: // Buy Float
                if ($step_no == 1 && is_numeric($last_part)) {
                    if ($last_part <= 0) {
                        $message_string = "Invalid amount. Try again:\n0. Back\n*. Main Menu";
                    } else {
                        $message_string = "Confirm float purchase of K" . number_format($last_part, 2) .
                                        "\n1. Confirm\n2. Cancel\n0. Back\n*. Main Menu";
                        $this->updateSession($session_id, 8, 2, ['amount' => $last_part]);
                    }
                } elseif ($step_no == 2 && is_numeric($last_part)) {
                    if ($last_part == 1) {
                        // Process float purchase
                        $session = UssdSession::where('session_id', $session_id)->first();
                        $message_string = "Float purchase of K" . number_format($session->amount, 2) .
                                        " initiated. You will receive an SMS confirmation.\n*. Main Menu";
                        $this->sendNotification($phone, $message_string);
                    } else {
                        $message_string = "Float purchase cancelled.\n*. Main Menu";
                    }
                    $request_type = "3";
                }
                break;
                case 9: // Direct Dial Flow
                    if ($step_no == 1 && is_numeric($last_part)) {
                        if ($last_part == 1) {
                            $message_string = "Enter meter number:";
                            $this->updateSession($session_id, 9, 2);
                        } else {
                            $message_string = "Transaction cancelled.";
                            $request_type = "3";
                        }
                    } elseif ($step_no == 2) {
                        $customer = $this->validateMeterNumber($last_part);
                        if ($customer) {
                            $message_string = "Confirm payment of K" . $getLastSessionInfo->merchant_amount .
                                            " for meter " . $last_part . "\n1. Confirm\n2. Cancel";
                            $this->updateSession($session_id, 9, 3, [
                                'meter_no' => $last_part,
                                'customer_id' => $customer->id
                            ]);
                        } else {
                            $message_string = "Invalid meter number. Try again:";
                        }
                    } elseif ($step_no == 3 && is_numeric($last_part)) {
                        if ($last_part == 1) {
                            $result = $this->processAgentPayment(
                                $getLastSessionInfo->agent_id,
                                $getLastSessionInfo->customer_id,
                                $getLastSessionInfo->merchant_amount
                            );

                            if ($result['success']) {
                                $message_string = "Payment successful\nToken: " . $result['token'];
                                $this->sendSms($phone, "Payment successful. Token: " . $result['token']);
                                $this->sendNotification($phone, $message_string);
                            } else {
                                $message_string = "Payment failed: " . $result['message'];
                                $this->sendNotification($phone, $message_string);
                            }
                            $request_type = "3";
                        } else {
                            $message_string = "Transaction cancelled.";
                            $this->sendNotification($phone, $message_string);
                            $request_type = "3";
                        }
                    }
                    break;
            case 10: // PIN Change Flow
                if ($step_no == 1) {
                    $session = UssdSession::where('session_id', $session_id)->first();
                    $agent = Agent::find($session->agent_id);

                    if ($agent && $this->validateAgentPin($phone, $last_part)) {
                        $message_string = "Enter new PIN (4 digits):\n0. Back\n*. Main Menu";
                        $this->updateSession($session_id, 10, 2);
                    } else {
                        $message_string = "Current PIN is incorrect. Try again:\n0. Back\n*. Main Menu";
                    }
                } elseif ($step_no == 2) {
                    if (strlen($last_part) === 4 && is_numeric($last_part)) {
                        $message_string = "Confirm new PIN:\n0. Back\n*. Main Menu";
                        $this->updateSession($session_id, 10, 3, ['new_pin' => $last_part]);
                    } else {
                        $message_string = "PIN must be 4 digits. Try again:\n0. Back\n*. Main Menu";
                    }
                } elseif ($step_no == 3) {
                    $session = UssdSession::where('session_id', $session_id)->first();
                    if ($last_part === $session->new_pin) {
                        try {
                            $agent = Agent::find($session->agent_id);
                            $agent->pin = $last_part;
                            $agent->save();

                            $this->logAgentActivity($agent->id, 'pin_change', 'success');
                            $message_string = "PIN changed successfully!\n*. Main Menu";

                            $this->sendNotification(
                                $phone,
                                "Your agent PIN has been changed successfully. If you did not authorize this change, please contact support immediately."
                            );
                        } catch (\Exception $e) {
                            Log::error('PIN change error', [
                                'agent_id' => $session->agent_id,
                                'error' => $e->getMessage()
                            ]);
                            $message_string = "Failed to update PIN. Please try again later.\n*. Main Menu";
                        }
                        $request_type = "3";
                    } else {
                        $message_string = "PINs do not match. Enter new PIN:\n0. Back\n*. Main Menu";
                        $this->updateSession($session_id, 10, 2);
                    }
                }
                break;

            case 11: // Language Selection and Customer Support Menu
                    if ($step_no == 1 && !empty($last_part)) {
                        Log::info('Language Selection Step', [
                            'session_id' => $session_id,
                            'input' => $last_part,
                            'phone' => $phone
                        ]);

                        if (is_numeric($last_part) && $last_part >= 1 && $last_part <= 2) {
                            $language = $last_part;
                            Log::info('Language Selected', [
                                'language_id' => $language,
                                'session_id' => $session_id
                            ]);

                            $this->updateSession($session_id, 11, 2, ["language_id" => $language]);
                            $message_string = $this->getCustomerDeskMenu($language);
                        } else {
                            Log::warning('Invalid Language Selection', [
                                'input' => $last_part,
                                'session_id' => $session_id
                            ]);
                            // Invalid language selection - show menu again
                            $geLanguages = Language::where('is_active', 1)->get();
                            $language_menu = "Invalid selection. Choose language / Kuheta kusola. Sakulenu lizu\n\n";
                            foreach ($geLanguages as $index => $language) {
                                $language_menu .= ($index + 1) . ". " . $language->name . "\n";
                            }
                            $language_menu .= "\n0. Back\n*. Main Menu";
                            $message_string = $language_menu;
                        }
                    } elseif ($step_no == 2 && !empty($last_part)) {
                        $language = $getLastSessionInfo->language_id;
                        Log::info('Customer Desk Menu Selection', [
                            'step' => 2,
                            'input' => $last_part,
                            'language_id' => $language,
                            'session_id' => $session_id
                        ]);

                        if (is_numeric($last_part) && $last_part >= 1 && $last_part <= 4) {
                            switch($last_part) {
                                case 1: // Complaints
                                    $message_string = $this->getComplaintsMessage($language, "METER_PROMPT");
                                    $this->updateSession($session_id, 12, 1, ["language_id" => $language]);
                                    Log::info('Navigating to Complaints Flow', [
                                        'new_case' => 12,
                                        'language_id' => $language,
                                        'session_id' => $session_id
                                    ]);
                                    break;

                                case 2: // General Inquiries
                                    $message_string = $this->getGeneralInquiriesMenu($language);
                                    $this->updateSession($session_id, 13, 1, ["language_id" => $language]);
                                    Log::info('Navigating to General Inquiries Flow', [
                                        'new_case' => 13,
                                        'language_id' => $language,
                                        'session_id' => $session_id
                                    ]);
                                    break;

                                case 3: // Customer Feedback
                                    $message_string = $this->getFeedbackMessage($language, "PROMPT");
                                    $this->updateSession($session_id, 14, 1, ["language_id" => $language]);
                                    Log::info('Navigating to Customer Feedback Flow', [
                                        'new_case' => 14,
                                        'language_id' => $language,
                                        'session_id' => $session_id
                                    ]);
                                    break;

                                case 4: // Track Complaints
                                    $message_string = $this->getTrackComplaintMessage($language, "PROMPT");
                                    $this->updateSession($session_id, 15, 1, ["language_id" => $language]);
                                    Log::info('Navigating to Track Complaints Flow', [
                                        'new_case' => 15,
                                        'language_id' => $language,
                                        'session_id' => $session_id
                                    ]);
                                    break;
                            }
                        } else {
                            Log::warning('Invalid Customer Desk Selection', [
                                'input' => $last_part,
                                'language_id' => $language,
                                'session_id' => $session_id
                            ]);
                            $message_string = $this->getCustomerDeskMenu($language);
                        }
                    }
                    break;

            case 12: // Complaints Flow
                        $language = $getLastSessionInfo->language_id;
                        Log::info('Complaints Flow', [
                            'step' => $step_no,
                            'input' => $last_part,
                            'language_id' => $language,
                            'session_id' => $session_id,
                            'phone' => $phone
                        ]);

                        if ($step_no == 1 && !empty($last_part)) {
                            try {
                                $complaint = $this->generateComplaintNumber();
                                Log::info('Generated Complaint Number', [
                                    'complaint_number' => $complaint,
                                    'session_id' => $session_id
                                ]);

                                // Save the complaint
                                $save_complaint = Complaint::create([
                                    "complaint_number" => $complaint,
                                    "phone_number" => $phone,
                                    "session_id" => $session_id,
                                    "communication_channel_id" => 1,
                                    "complaint_status_id" => 1,
                                    "meter_number" => $last_part
                                ]);

                                Log::info('Complaint Created', [
                                    'complaint_id' => $save_complaint->id,
                                    'complaint_number' => $complaint,
                                    'meter_number' => $last_part,
                                    'session_id' => $session_id
                                ]);

                                $message_string = $this->getComplaintsMessage($language, "TYPE_SELECTION");
                                $this->updateSession($session_id, 12, 2, ["language_id" => $language]);

                            } catch (\Exception $e) {
                                Log::error('Error Creating Complaint', [
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                    'session_id' => $session_id
                                ]);
                                $message_string = $language == 1 ?
                                    "System error. Please try again later.\n*. Main Menu" :
                                    "Kukala mukachi ka computer. Mwani temukenu.\n*. Main Menu";
                                $request_type = 3;
                            }

                        } elseif ($step_no == 2 && !empty($last_part)) {
                            if (is_numeric($last_part) && $last_part >= 1 && $last_part <= 3) {
                                try {
                                    $updating_complaint = Complaint::where('session_id', $session_id)
                                        ->update(["complaint_category_id" => $last_part]);

                                    Log::info('Complaint Category Updated', [
                                        'category_id' => $last_part,
                                        'session_id' => $session_id,
                                        'update_success' => $updating_complaint
                                    ]);

                                    if ($last_part == 3) {
                                        $message_string = $this->getComplaintsMessage($language, "SPECIFY");
                                        $this->updateSession($session_id, 12, 3, ["language_id" => $language]);
                                    } else {
                                        $message_string = $this->getComplaintsMessage($language, "SUCCESS");

                                        // Send confirmation SMS
                                        try {
                                            $this->sendComplaintConfirmation($phone, $session_id, $language);
                                            Log::info('Complaint Confirmation SMS Sent', [
                                                'phone' => $phone,
                                                'session_id' => $session_id,
                                                'language' => $language
                                            ]);
                                        } catch (\Exception $e) {
                                            Log::error('SMS Sending Failed', [
                                                'error' => $e->getMessage(),
                                                'phone' => $phone,
                                                'session_id' => $session_id
                                            ]);
                                        }

                                        $request_type = 3;
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Error Updating Complaint Category', [
                                        'error' => $e->getMessage(),
                                        'session_id' => $session_id
                                    ]);
                                    $message_string = $this->getComplaintsMessage($language, "ERROR");
                                    $request_type = 3;
                                }
                            } else {
                                Log::warning('Invalid Complaint Type Selection', [
                                    'input' => $last_part,
                                    'session_id' => $session_id
                                ]);
                                $message_string = $this->getComplaintsMessage($language, "TYPE_SELECTION");
                            }
                        } elseif ($step_no == 3 && !empty($last_part)) {
                            try {
                                $updating_complaint = Complaint::where('session_id', $session_id)
                                    ->update(["description" => $last_part]);

                                Log::info('Complaint Description Updated', [
                                    'description' => $last_part,
                                    'session_id' => $session_id,
                                    'update_success' => $updating_complaint
                                ]);

                                $message_string = $this->getComplaintsMessage($language, "SUCCESS");

                                // Send confirmation SMS
                                try {
                                    $this->sendComplaintConfirmation($phone, $session_id, $language);
                                    Log::info('Complaint Confirmation SMS Sent', [
                                        'phone' => $phone,
                                        'session_id' => $session_id,
                                        'language' => $language
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('SMS Sending Failed', [
                                        'error' => $e->getMessage(),
                                        'phone' => $phone,
                                        'session_id' => $session_id
                                    ]);
                                }

                                $request_type = 3;
                            } catch (\Exception $e) {
                                Log::error('Error Updating Complaint Description', [
                                    'error' => $e->getMessage(),
                                    'session_id' => $session_id
                                ]);
                                $message_string = $this->getComplaintsMessage($language, "ERROR");
                                $request_type = 3;
                            }
                        }
                        break;

            case 13: // General Inquiries Flow
                            $language = $getLastSessionInfo->language_id;

                            Log::info('General Inquiries Flow Started', [
                                'step' => $step_no,
                                'input' => $last_part,
                                'session_id' => $session_id,
                                'phone' => $phone,
                                'language_id' => $language
                            ]);

                            if ($step_no == 1 && !empty($last_part)) {
                                try {
                                    if (is_numeric($last_part) && $last_part >= 1 && $last_part <= 4) {
                                        $inquiry_number = $this->generateInquiryNumber();

                                        Log::info('Processing General Inquiry', [
                                            'inquiry_number' => $inquiry_number,
                                            'type' => $last_part,
                                            'session_id' => $session_id
                                        ]);

                                        if ($last_part == 4) {
                                            // Other - need specification
                                            $new_inquiry = GeneralInquiry::create([
                                                "inquiry_number" => $inquiry_number,
                                                "phone_number" => $phone,
                                                "session_id" => $session_id,
                                                "communication_channel_id" => 1, // USSD
                                                "general_inquiry_category_id" => $last_part,
                                                "status" => 'pending',
                                                "created_at" => now()
                                            ]);

                                            Log::info('Created General Inquiry for Other Category', [
                                                'inquiry_id' => $new_inquiry->id,
                                                'inquiry_number' => $inquiry_number
                                            ]);

                                            $message_string = ($language == 1) ?
                                                "Please specify your inquiry:\n0. Back\n*. Main Menu" :
                                                "Mwani shimunenu malwihu enu:\n0. Back\n*. Main Menu";

                                            $this->updateSession($session_id, 13, 2, [
                                                'inquiry_number' => $inquiry_number
                                            ]);

                                        } else {
                                            // Handle predefined inquiry types
                                            $new_inquiry = GeneralInquiry::create([
                                                "inquiry_number" => $inquiry_number,
                                                "phone_number" => $phone,
                                                "session_id" => $session_id,
                                                "communication_channel_id" => 1, // USSD
                                                "general_inquiry_category_id" => $last_part,
                                                "status" => 'pending',
                                                "created_at" => now()
                                            ]);

                                            Log::info('Created General Inquiry', [
                                                'inquiry_id' => $new_inquiry->id,
                                                'inquiry_number' => $inquiry_number,
                                                'category' => $last_part
                                            ]);

                                            // Send appropriate response based on inquiry type
                                            switch($last_part) {
                                                case 1: // How to access power
                                                    $sms_message = ($language == 1) ?
                                                        "Call us on 211-241296 with your details and we will guide you through electricity connection steps and requirements." :
                                                        "Tumininaku nshinga ha 211-241296 nakuyilezha yuma yenu, tunamulezha mwakutwala kesi ka wuneñu.";
                                                    break;

                                                case 2: // How to buy units
                                                    $sms_message = ($language == 1) ?
                                                        "Dial *388*1#, choose payment option and make payment from your mobile money Account on any network." :
                                                        "Kobolenu *388*1#, sakulenu mwakufweta nakufweta kufuma mu account yenu ya mobile money.";
                                                    break;

                                                case 3: // How to change tariff plan
                                                    $sms_message = ($language == 1) ?
                                                        "Please call us on 211-241296 and our team will help you change your current tariff plan." :
                                                        "Mwani tuminaku nshinga ha 211-241296 antu etu akumikafwa kuchinja tariff plan yenu.";
                                                    break;
                                            }

                                            $message_string = ($language == 1) ?
                                                "Thank you for your inquiry. You will receive an SMS with details shortly." :
                                                "Kusakilila hakwihula. Mukutambula SMS na yilezha yejima mukapindi kafwipi.";

                                            try {
                                                $this->sendNotification($phone, $sms_message);
                                                Log::info('Sent inquiry response SMS', [
                                                    'phone' => $phone,
                                                    'inquiry_number' => $inquiry_number
                                                ]);
                                            } catch (\Exception $e) {
                                                Log::error('Failed to send inquiry SMS', [
                                                    'error' => $e->getMessage(),
                                                    'phone' => $phone
                                                ]);
                                            }

                                            $request_type = "3";
                                        }

                                    } else {
                                        Log::warning('Invalid inquiry type selection', [
                                            'input' => $last_part,
                                            'session_id' => $session_id
                                        ]);

                                        $message_string = ($language == 1) ?
                                            "Invalid selection. Please choose:\n" .
                                            "1. How to access power?\n" .
                                            "2. How to buy units?\n" .
                                            "3. How to change tariff plan?\n" .
                                            "4. Other specify\n\n" .
                                            "0. Back\n*. Main Menu" :
                                            "Kusola kwaheta. Mwani sakulenu:\n" .
                                            "1. Mwakutwala kesi ka wuneñu?\n" .
                                            "2. Mwakula yuniti?\n" .
                                            "3. Mwakuchinja tariff plan?\n" .
                                            "4. Yuma yikwawu\n\n" .
                                            "0. Back\n*. Main Menu";
                                    }

                                } catch (\Exception $e) {
                                    Log::error('Error processing general inquiry', [
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                        'session_id' => $session_id
                                    ]);

                                    $message_string = ($language == 1) ?
                                        "System error. Please try again later.\n*. Main Menu" :
                                        "Kukala mukachi ka computer. Mwani temukenu.\n*. Main Menu";
                                    $request_type = "3";
                                }
                            } elseif ($step_no == 2 && !empty($last_part)) {
                                try {
                                    Log::info('Processing custom inquiry description', [
                                        'session_id' => $session_id,
                                        'description' => $last_part
                                    ]);

                                    $updating_inquiry = GeneralInquiry::where('session_id', $session_id)
                                        ->update([
                                            "description" => $last_part,
                                            "updated_at" => now()
                                        ]);

                                    if ($updating_inquiry) {
                                        $inquiry = GeneralInquiry::where('session_id', $session_id)->first();

                                        $message_string = ($language == 1) ?
                                            "Thank you for your inquiry. Our team will contact you soon." :
                                            "Kusakilila hakwihula. Antu etu akezha kumitumina mukapindi kafwipi.";

                                        $sms_message = ($language == 1) ?
                                            "Thank you for your inquiry. Your reference number is " . $inquiry->inquiry_number .
                                            ". Our team will contact you soon. For urgent matters, call 211-241296." :
                                            "Kusakilila hakwihula. Nambala yenu ya reference yinayi " . $inquiry->inquiry_number .
                                            ". Antu etu akezha kumitumina. Mwatela kutumina nshinga ha 211-241296 hakuneña chachipompelu.";

                                        try {
                                            $this->sendNotification($phone, $sms_message);
                                            Log::info('Sent custom inquiry confirmation SMS', [
                                                'phone' => $phone,
                                                'inquiry_number' => $inquiry->inquiry_number
                                            ]);
                                        } catch (\Exception $e) {
                                            Log::error('Failed to send custom inquiry SMS', [
                                                'error' => $e->getMessage(),
                                                'phone' => $phone
                                            ]);
                                        }
                                    } else {
                                        Log::error('Failed to update inquiry description', [
                                            'session_id' => $session_id
                                        ]);
                                        $message_string = ($language == 1) ?
                                            "Failed to save your inquiry. Please try again." :
                                            "Kukala hakusenda malwihu enu. Mwani temukenu.";
                                    }

                                    $request_type = "3";

                                } catch (\Exception $e) {
                                    Log::error('Error saving custom inquiry', [
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                        'session_id' => $session_id
                                    ]);

                                    $message_string = ($language == 1) ?
                                        "System error. Please try again later.\n*. Main Menu" :
                                        "Kukala mukachi ka computer. Mwani temukenu.\n*. Main Menu";
                                    $request_type = "3";
                                }
                            }
                            break;
                            case 14: // Customer Feedback Flow
                                $language = $getLastSessionInfo->language_id;

                                Log::info('Customer Feedback Flow Started', [
                                    'step' => $step_no,
                                    'input' => $last_part,
                                    'session_id' => $session_id,
                                    'phone' => $phone,
                                    'language_id' => $language
                                ]);

                                if ($step_no == 1 && !empty($last_part)) {
                                    try {
                                        $feedback_number = $this->generateFeedbackNumber();

                                        Log::info('Processing Customer Feedback', [
                                            'feedback_number' => $feedback_number,
                                            'session_id' => $session_id
                                        ]);

                                        // Save the customer feedback
                                        $new_feedback = CustomerFeedback::create([
                                            "feedback_number" => $feedback_number,
                                            "phone_number" => $phone,
                                            "session_id" => $session_id,
                                            "communication_channel_id" => 1, // USSD
                                            "description" => $last_part,
                                            "status" => 'submitted',
                                            "created_at" => now()
                                        ]);

                                        Log::info('Feedback Created', [
                                            'feedback_id' => $new_feedback->id,
                                            'feedback_number' => $feedback_number
                                        ]);

                                        $message_string = ($language == 1) ?
                                            "Thank you for your feedback. Your reference number is: " . $feedback_number . "\n*. Main Menu" :
                                            "Atulezhiku wunsahu wenu. Nambala yenu ya reference: " . $feedback_number . "\n*. Main Menu";

                                        // Send SMS confirmation
                                        $sms_message = ($language == 1) ?
                                            "Thank you for your feedback. Reference number: " . $feedback_number .
                                            ". We value your feedback and will use it to improve our services. For any queries, call 211-241296" :
                                            "Atulezhiku wunsahu wenu. Nambala ya reference: " . $feedback_number .
                                            ". Wunsahu wenu wunawuzhaku nakuwuzatisha mukulamisha nzata zhehu. Muchidi wahembi tumininaku nshinga ha 211-241296";

                                        try {
                                            $this->sendNotification($phone, $sms_message);
                                            Log::info('Feedback confirmation SMS sent', [
                                                'phone' => $phone,
                                                'feedback_number' => $feedback_number
                                            ]);
                                        } catch (\Exception $e) {
                                            Log::error('Failed to send feedback confirmation SMS', [
                                                'error' => $e->getMessage(),
                                                'phone' => $phone
                                            ]);
                                        }

                                        $request_type = "3";

                                    } catch (\Exception $e) {
                                        Log::error('Error processing feedback', [
                                            'error' => $e->getMessage(),
                                            'trace' => $e->getTraceAsString(),
                                            'session_id' => $session_id
                                        ]);

                                        $message_string = ($language == 1) ?
                                            "System error. Please try again later.\n*. Main Menu" :
                                            "Kukala mukachi ka computer. Mwani temukenu.\n*. Main Menu";
                                        $request_type = "3";
                                    }
                                }
                                break;

                                case 15: // Track Complaints Flow
                                    $language = $getLastSessionInfo->language_id;

                                    Log::info('Complaint Tracking Flow Started', [
                                        'step' => $step_no,
                                        'input' => $last_part,
                                        'session_id' => $session_id,
                                        'phone' => $phone,
                                        'language_id' => $language
                                    ]);

                                    if ($step_no == 1 && !empty($last_part)) {
                                        try {
                                            Log::info('Searching for complaint', [
                                                'complaint_number' => $last_part,
                                                'session_id' => $session_id
                                            ]);

                                            // Changed from complaintStatus to status relationship
                                            $complaint = Complaint::where('complaint_number', $last_part)->first();

                                            if ($complaint) {
                                                Log::info('Complaint found', [
                                                    'complaint_id' => $complaint->id,
                                                    'status_id' => $complaint->complaint_status_id,
                                                    'created_at' => $complaint->created_at
                                                ]);

                                                // Get status text in appropriate language
                                                $status = self::COMPLAINT_STATUSES[$complaint->complaint_status_id][$language == 1 ? 'en' : 'ln'] ??
                                                         ($language == 1 ? 'Unknown Status' : 'Kosi kutachikiza muchidi');

                                                // Format date according to language
                                                $date_format = $complaint->created_at->format('d/m/Y H:i');

                                                if ($language == 1) {
                                                    $message_string = "Complaint Details:\n" .
                                                                    "Number: " . $complaint->complaint_number . "\n" .
                                                                    "Status: " . $status . "\n" .
                                                                    "Reported: " . $date_format;

                                                    // Add complaint category if exists
                                                    if ($complaint->complaint_category_id) {
                                                        $category = $this->getComplaintCategory($complaint->complaint_category_id, $language);
                                                        $message_string .= "\nType: " . $category;
                                                    }

                                                    // Add description if exists
                                                    if ($complaint->description) {
                                                        $message_string .= "\nDetails: " . substr($complaint->description, 0, 50);
                                                    }

                                                    $message_string .= "\n\n*. Main Menu";

                                                } else {
                                                    $message_string = "Nyabu:\n" .
                                                                    "Nambala: " . $complaint->complaint_number . "\n" .
                                                                    "Muchidi: " . $status . "\n" .
                                                                    "Ifuku: " . $date_format;

                                                    // Add complaint category if exists
                                                    if ($complaint->complaint_category_id) {
                                                        $category = $this->getComplaintCategory($complaint->complaint_category_id, $language);
                                                        $message_string .= "\nMuchidi: " . $category;
                                                    }

                                                    // Add description if exists
                                                    if ($complaint->description) {
                                                        $message_string .= "\nYilezha: " . substr($complaint->description, 0, 50);
                                                    }

                                                    $message_string .= "\n\n*. Main Menu";
                                                }

                                                // Send SMS with status
                                                try {
                                                    $sms_message = ($language == 1) ?
                                                        "Your complaint " . $complaint->complaint_number . " status is: " . $status .
                                                        ". For updates, call 211-241296" :
                                                        "Nyabu yenu " . $complaint->complaint_number . " yili muchidi wa: " . $status .
                                                        ". Hakukeña yayindi, tumininaku nshinga ha 211-241296";

                                                    $this->sendNotification($phone, $sms_message);

                                                    Log::info('Complaint tracking SMS sent', [
                                                        'phone' => $phone,
                                                        'complaint_number' => $complaint->complaint_number,
                                                        'status' => $status
                                                    ]);
                                                } catch (\Exception $e) {
                                                    Log::error('Failed to send tracking SMS', [
                                                        'error' => $e->getMessage(),
                                                        'phone' => $phone,
                                                        'complaint_number' => $complaint->complaint_number
                                                    ]);
                                                }

                                            } else {
                                                Log::warning('Complaint not found', [
                                                    'searched_number' => $last_part,
                                                    'session_id' => $session_id
                                                ]);

                                                $message_string = ($language == 1) ?
                                                    "Complaint not found. Please check the number and try again.\n0. Back\n*. Main Menu" :
                                                    "Kakweshi nyabu. Mwani talishenu nambala nakutemakana.\n0. Back\n*. Main Menu";
                                            }

                                            $request_type = "3";

                                        } catch (\Exception $e) {
                                            Log::error('Error in complaint tracking', [
                                                'error' => $e->getMessage(),
                                                'trace' => $e->getTraceAsString(),
                                                'session_id' => $session_id
                                            ]);

                                            $message_string = ($language == 1) ?
                                                "System error. Please try again later.\n*. Main Menu" :
                                                "Kukala mukachi ka computer. Mwani temukenu.\n*. Main Menu";
                                            $request_type = "3";
                                        }
                                    }
                                    break;



                        }

                } catch (\Exception $e) {
                    Log::error('Unhandled exception in handleUssd', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'request' => [
                            'session_id' => $request->SESSION_ID,
                            'phone' => $request->MSISDN,
                            'message' => $request->MESSAGE
                        ]
                    ]);

                    return $this->formatResponse(
                        "System error. Please try again.\n*. Main Menu",
                        "3"
                    );
                }
        return $this->formatResponse($message_string, $request_type);
    }

    private function checkCustomerBalance($customerCode)
    {
        try {
            Log::info('Checking customer balance', [
                'customer_code' => $customerCode
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => config('services.sparkmeter.key'),
                'X-API-SECRET' => config('services.sparkmeter.secret')
            ])->get('https://www.sparkmeter.cloud/api/v1/customers', [
                'code' => $customerCode,
                'reading_details' => true
            ]);

            if (!$response->successful()) {
                Log::warning('Balance check failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return ['success' => false, 'message' => 'Failed to check balance'];
            }

            $data = $response->json();
            $customerData = $data['data'][0] ?? null;

            if (!$customerData) {
                return ['success' => false, 'message' => 'Customer data not found'];
            }

            // Extract balance information
            $balance = $customerData['balances']['credit']['value'] ?? '0.00';
            $currency = $customerData['balances']['credit']['currency'] ?? 'ZMW';

            Log::info('Balance retrieved successfully', [
                'customer_code' => $customerCode,
                'balance' => $balance,
                'currency' => $currency
            ]);

            return [
                'success' => true,
                'balance' => $balance,
                'currency' => $currency,
                'raw_data' => $customerData['balances']
            ];

        } catch (\Exception $e) {
            Log::error('Balance check error', [
                'customer_code' => $customerCode,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => 'Service unavailable'];
        }
    }

    private function getPreviousMenu($case_no, $step_no, $sessionInfo)
    {
        switch ($case_no) {
            case 1:
                return $this->getMainMenu();

            case 2: // Buy Electricity Flow
                return "Enter your Customer ID:\n0. Back\n*. Main Menu";

            case 3: // View Balance Flow
                return "Enter your Customer ID:\n0. Back\n*. Main Menu";

            case 4: // Query Transaction Flow
                return "Enter Transaction ID:\n0. Back\n*. Main Menu";

            case 5: // Customer Desk
                switch ($step_no) {
                    case 1:
                        return "Select Option:\n1. Report Blackout\n2. Report Fault\n3. General Inquiry\n0. Back\n*. Main Menu";
                    case 2:
                        return "Enter your meter number:\n0. Back\n*. Main Menu";
                    case 3:
                        return "Enter your meter number:\n0. Back\n*. Main Menu";
                    case 4:
                        return "Type your inquiry:\n0. Back\n*. Main Menu";
                    case 5:
                        return "Select fault type:\n1. No Power\n2. Meter Error\n3. Connection Issue\n4. Other\n0. Back\n*. Main Menu";
                    default:
                        return $this->getMainMenu();
                }

            case 6: // Agent Login Flow
                switch ($step_no) {
                    case 1:
                        return "Enter your PIN:\n0. Back\n*. Main Menu";
                    case 2:
                        return "Welcome " . ($sessionInfo->agent ? $sessionInfo->agent->business_name : '') .
                               "\n1. Sell Electricity\n2. Check Float\n3. Buy Float\n4. Change PIN\n0. Back\n*. Main Menu";
                    default:
                        return $this->getMainMenu();
                }

            case 7: // Agent Electricity Sale
                switch ($step_no) {
                    case 1:
                        return "Enter customer code:\n0. Back\n*. Main Menu";
                    case 2:
                        return "Enter amount:\n0. Back\n*. Main Menu";
                    case 3:
                        return "Confirm purchase:\n1. Confirm\n2. Cancel\n0. Back\n*. Main Menu";
                    default:
                        return $this->getMainMenu();
                }

            case 8: // Buy Float
                return "Enter amount to purchase:\n0. Back\n*. Main Menu";

            case 10: // PIN Change Flow
                switch ($step_no) {
                    case 1:
                        return "Enter your current PIN:\n0. Back\n*. Main Menu";
                    case 2:
                        return "Enter new PIN (4 digits):\n0. Back\n*. Main Menu";
                    case 3:
                        return "Confirm new PIN:\n0. Back\n*. Main Menu";
                    default:
                        return $this->getMainMenu();
                }

            default:
                return $this->getMainMenu();
        }
    }

    private function createCustomerFeedback($phone, $sessionId, $description)
    {
        try {
            Log::info('Creating customer feedback', [
                'phone' => $phone,
                'session_id' => $sessionId
            ]);

            $feedbackNumber = 'FB' . random_int(1000000, 9999999);

            $feedback = CustomerFeedback::create([
                'feedback_number' => $feedbackNumber,
                'phone_number' => $phone,
                'session_id' => $sessionId,
                'communication_channel_id' => 1, // USSD channel ID
                'description' => $description,
                'status' => 'submitted',
                'metadata' => [
                    'source' => 'USSD',
                    'created_via' => 'customer_ussd',
                    'ip_address' => request()->ip()
                ]
            ]);

            Log::info('Customer feedback created successfully', [
                'feedback_id' => $feedback->id,
                'reference' => $feedback->feedback_number
            ]);

            return $feedback;

        } catch (\Exception $e) {
            Log::error('Error processing feedback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phone,
                'session_id' => $sessionId
            ]);
            throw $e;
        }
    }

    private function getMainMenu()
    {
        return "Welcome to REA\n1. Buy Electricity\n2. View Balance\n3. Query Transaction\n4. Customer Desk\n5. Agent Login";
    }

    private function processAgentPayment($agent_id, $customer_id, $amount, $meter_number)
    {
        try {
            Log::info('Starting agent payment process', [
                'agent_id' => $agent_id,
                'customer_id' => $customer_id,
                'amount' => $amount,
                'meter_number' => $meter_number
            ]);

            // 1. Verify agent
            $agent = Agent::find($agent_id);
            if (!$agent) {
                Log::error('Agent not found', ['agent_id' => $agent_id]);
                return [
                    'success' => false,
                    'message' => 'Agent not found'
                ];
            }

            // 2. Verify float balance
            if ($agent->float_balance < $amount) {
                Log::warning('Insufficient float balance', [
                    'required' => $amount,
                    'available' => $agent->float_balance
                ]);
                return [
                    'success' => false,
                    'message' => 'Insufficient float balance'
                ];
            }

            // 3. Get customer details
            $customer = Customer::find($customer_id);
            if (!$customer) {
                Log::error('Customer not found', ['customer_id' => $customer_id]);
                return [
                    'success' => false,
                    'message' => 'Customer not found'
                ];
            }

            // 4. Generate reference
            $reference = 'FLT' . rand(1000000000, 9999999999);

            try {
                // 5. Create float transaction
                $floatTransaction = FloatTransaction::create([
                    'agent_id' => $agent_id,
                    'amount' => $amount,
                    'type' => 'debit',
                    'reference_number' => $reference,
                    'status' => 'pending',
                    'description' => 'Electricity purchase',
                    'balance_before' => $agent->float_balance,
                    'balance_after' => $agent->float_balance - $amount,
                ]);

                Log::info('Float transaction created', [
                    'transaction_id' => $floatTransaction->id,
                    'reference' => $reference
                ]);

                // 6. Create payment record
                $payment = Payment::create([
                    'phone_number' => $customer->phone_number,
                    'payment_method_id' => 3,        // Agent Float
                    'payment_channel_id' => 3,       // zamtel
                    'meter_number' => $meter_number,
                    'customer_id' => $customer_id,
                    'agent_id' => $agent_id,
                    'amount_paid' => $amount,
                    'payment_status_id' => 0,
                    'payment_reference_number' => $reference,
                    'retry_count' => 1,
                    'transaction_type_id' => 1       // credit
                ]);

                Log::info('Payment record created', [
                    'payment_id' => $payment->id,
                    'reference' => $reference
                ]);

                // 7. Generate token
                $token = 'TOK' . rand(10000000000, 99999999999);
                $units = round($amount / 7.08, 2);

                // 8. Update agent float balance
                $agent->float_balance -= $amount;
                $agent->save();

                // 9. Update float transaction status
                $floatTransaction->update([
                    'status' => 'completed'
                ]);

                // 10. Update payment status
                $payment->update([
                    'payment_status_id' => 1,
                    'transaction_id' => $token
                ]);

                Log::info('Agent payment successful', [
                    'reference' => $reference,
                    'token' => $token,
                    'units' => $units
                ]);

                return [
                    'success' => true,
                    'token' => $token,
                    'external_id' => $reference,
                    'units' => $units
                ];

            } catch (\Exception $e) {
                Log::error('Transaction processing error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                if (isset($floatTransaction)) {
                    $floatTransaction->update(['status' => 'failed']);
                }

                if (isset($payment)) {
                    $payment->update([
                        'payment_status_id' => 2,
                        'error_message' => $e->getMessage()
                    ]);
                }

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Agent payment processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing failed'
            ];
        }
    }

    private function validateCustomer($customerCode)
    {
        try {
            Log::info('Starting customer validation', [
                'customer_code' => $customerCode
            ]);

            // Configure HTTP client with better timeout and retry settings
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => config('services.sparkmeter.key'),
                'X-API-SECRET' => config('services.sparkmeter.secret')
            ])
            ->timeout(30) // Increase timeout to 30 seconds
            ->retry(3, 100, function ($exception, $request) {
                // Retry on connection timeout or server errors
                return $exception instanceof \Illuminate\Http\Client\ConnectionException
                    || ($exception instanceof \Illuminate\Http\Client\RequestException && $exception->response->status() >= 500);
            })
            ->get('https://www.sparkmeter.cloud/api/v1/customers', [
                'code' => $customerCode,
                'reading_details' => true
            ]);

            if (!$response->successful()) {
                Log::warning('SparkMeter API request failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }

            $responseData = $response->json();

            // Check if we have data in the response
            if (!isset($responseData['data']) || empty($responseData['data'])) {
                Log::warning('No data in SparkMeter response', [
                    'response' => $responseData
                ]);
                return null;
            }

            // Extract customer data
            $customerData = $responseData['data'][0] ?? null;
            if (!$customerData) {
                Log::warning('Customer data not found in response');
                return null;
            }

            Log::info('Found customer data', [
                'name' => $customerData['name'] ?? 'Unknown',
                'meter' => $customerData['meters'][0]['serial'] ?? 'Unknown'
            ]);

            // Extract meter information
            $meterInfo = $customerData['meters'][0] ?? null;
            $meterNumber = $meterInfo ? $meterInfo['serial'] : null;

            // Get balance
            $balance = $customerData['balances']['credit']['value'] ?? '0.00';

            // Update or create customer in local database
            $customer = Customer::updateOrCreate(
                ['customer_number' => $customerCode],
                [
                    'name' => $customerData['name'],
                    'meter_number' => $meterNumber,
                    'phone_number' => $customerData['phone_number'] ?? null,
                    'status' => 'active',
                    'is_active' => true
                ]
            );

            // Add current transaction data
            $customer->current_balance = $balance;
            $customer->meter_serial = $meterNumber;
            $customer->spark_id = $customerData['id'] ?? null;

            Log::info('Customer validated and saved', [
                'customer_id' => $customer->id,
                'meter_number' => $customer->meter_number,
                'balance' => $customer->current_balance
            ]);

            return $customer;

        } catch (\Exception $e) {
            Log::error('Customer validation error', [
                'customer_code' => $customerCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Check if it's a timeout error and provide specific message
            if (strpos($e->getMessage(), 'cURL error 28') !== false) {
                Log::error('SparkMeter API timeout', [
                    'customer_code' => $customerCode
                ]);
                return [
                    'error' => true,
                    'message' => 'Service temporarily slow. Please try again.'
                ];
            }

            return null;
        }
    }

    private function formatResponse($message, $request_type)
    {
        return response()->json([
            "ussd_response" => [
                "USSD_BODY" => $message,
                "REQUEST_TYPE" => $request_type
            ]
        ]);
    }

    // private function updateSession($session_id, $case_no, $step_no, $additional_data = [])
    // {
    //     $update_data = array_merge([
    //         "case_no" => $case_no,
    //         "step_no" => $step_no
    //     ], $additional_data);

    //     UssdSession::where('session_id', $session_id)->update($update_data);
    // }

    private function standardizePhoneNumber($phone)
    {
        // Remove any spaces or special characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If number starts with '260', keep it as is
        if (strpos($phone, '260') === 0) {
            return $phone;
        }

        // If number starts with '0', replace with '260'
        if (strpos($phone, '0') === 0) {
            return '260' . substr($phone, 1);
        }

        // If number has neither prefix, add '260'
        if (strlen($phone) === 9) {
            return '260' . $phone;
        }

        return $phone;
    }

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

    function generateComplaintNumber()
    {
        $prefix = 'CN'; // Prefix for the complaint number
        $random = rand(1000000, 9999999);
        $raw_complaint_number = $prefix . $random;

        if (DB::table('complaints')->where('complaint_number', $raw_complaint_number)->exists()) {
            return $this->generateComplaintNumber();
        }

        return $raw_complaint_number;
    }

    function generateInquiryNumber()
    {
        $prefix = 'IN';
        $random = rand(1000000, 9999999);
        $inquiry_number = $prefix . $random;

        if (DB::table('general_inquiries')->where('inquiry_number', $inquiry_number)->exists()) {
            return $this->generateInquiryNumber();
        }

        return $inquiry_number;
    }

    private function isWhitelistedAgent($phone)
    {
        $standardizedPhone = $this->standardizePhoneNumber($phone);

        return Agent::where(function($query) use ($standardizedPhone, $phone) {
            $query->where('agent_phone_number', $standardizedPhone)
                ->orWhere('agent_phone_number', $phone)
                ->orWhere('agent_phone_number', '0' . substr($standardizedPhone, 3));
        })
        ->where('is_active', true)
        ->exists();
    }

    private function validateAgentPin($phone, $pin)
    {
        try {
            $standardizedPhone = $this->standardizePhoneNumber($phone);

            Log::info('Starting PIN validation', [
                'original_phone' => $phone,
                'standardized_phone' => $standardizedPhone,
                'pin_length' => strlen($pin)
            ]);

            $agent = Agent::where(function($query) use ($standardizedPhone, $phone) {
                $query->where('agent_phone_number', $standardizedPhone)
                    ->orWhere('agent_phone_number', $phone)
                    ->orWhere('agent_phone_number', '0' . substr($standardizedPhone, 3));
            })
            ->where('is_active', true)
            ->first();

            if (!$agent) {
                Log::warning('No agent found for phone number', [
                    'phone' => $phone,
                    'standardized_phone' => $standardizedPhone
                ]);
                return null;
            }

            if ((string)$agent->pin === (string)$pin) {
                Log::info('PIN validated successfully', [
                    'agent_id' => $agent->id,
                    'business_name' => $agent->business_name
                ]);
                return $agent;
            }

            Log::warning('PIN validation failed', [
                'agent_id' => $agent->id,
                'pin_match_failed' => true
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('PIN validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function logAgentActivity($agent_id, $activity_type, $status)
    {
        if ($agent_id) {
            AgentActivityLog::create([
                'agent_id' => $agent_id,
                'activity_type' => $activity_type,
                'session_id' => request()->SESSION_ID,
                'phone_number' => request()->MSISDN,
                'status' => $status,
                'ip_address' => request()->ip()
            ]);
        }
    }

    // Update the sendNotification method with logging:
    private function sendNotification($phone, $message)
    {
        try {
            Log::info('Sending SMS Notification', [
                'phone' => $phone,
                'message' => $message
            ]);

            $url_encoded_message = urlencode($message);
            $url = 'https://www.cloudservicezm.com/smsservice/httpapi?' .
                'username=Blessmore&password=Blessmore&msg=' . $url_encoded_message .
                '.+&shortcode=2343&sender_id=REAPAY&phone=' . $phone .
                '&api_key=121231313213123123';

            Log::info('SMS API Request', [
                'url' => $url
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            Log::info('SMS API Response', [
                'http_code' => $http_code,
                'response' => $response,
                'phone' => $phone
            ]);

            if ($http_code != 200) {
                throw new \Exception('SMS API returned non-200 status code: ' . $http_code);
            }

            curl_close($ch);
            return true;

        } catch (\Exception $e) {
            Log::error('SMS Sending Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phone
            ]);
            return false;
        }
    }

    private function sendSms($phone, $message)
    {
        // Implement SMS sending logic
        Log::info('SMS Notification', ['phone' => $phone, 'message' => $message]);
        return true;
    }

    private function getCustomerDeskMenu($language)
    {
        if ($language == 1) { //English
            return "Please select one of the options\n\n" .
                "1. Complaints\n" .
                "2. General Inquiries\n" .
                "3. Customer Feedback\n" .
                "4. Track Complaints" .
                "\n\n0. Back\n*. Main Menu";
        } else { //Lunda
            return "Sakulenuhu chuma chimu heshina\n\n" .
                "1. Nyabu (Kudibilashana)\n" .
                "2. Malwihu adi ezhima (Kwihula Mudimwezhima)\n" .
                "3. Wunsahu kudi akakulanda kesi ka wuneñu (Kwakula Kwa Mukakuseshana)\n" .
                "4. Mwatalishenu nyabu" .
                "\n\n0. Back\n*. Main Menu";
        }
    }

    private function getComplaintsMessage($language, $type)
    {
        $messages = [
            1 => [ // English
                'METER_PROMPT' => "Please enter your Meter number\n0. Back\n*. Main Menu",
                'TYPE_SELECTION' => "Please select the type of Complaint\n\n1. Power outage\n2. Billing\n3. Other\n\n0. Back\n*. Main Menu",
                'SPECIFY' => "Please specify the complaint\n0. Back\n*. Main Menu",
                'SUCCESS' => "Thank you for your report. Our team will look into the problem and update you."
            ],
            2 => [ // Lunda
                'METER_PROMPT' => "Mwani Iñizhenu nambala yenu ya mita\n0. Back\n*. Main Menu",
                'TYPE_SELECTION' => "Mwani sakulenu muchidi wamwabu\n\n1. Kuya kwa kesi ka wuneñu hela malayiti\n2. Wuseya wa kesi ka wunengu hela malayiti\n3. Nyabu yikwawu\n\n0. Back\n*. Main Menu",
                'SPECIFY' => "Mwani shimunenu mwabu wenu\n0. Back\n*. Main Menu",
                'SUCCESS' => "Kusakililaku mwani hakushimuna, antu etu akutalahu ha kukala kweniku naku yilezha mwakwilila mwani"
            ]
        ];

        return $messages[$language][$type] ?? $messages[1]['METER_PROMPT'];
    }

    private function getGeneralInquiriesMenu($language)
    {
        if ($language == 1) {
            return "1. How to access power?\n" .
                "2. How to buy units?\n" .
                "3. How to change the tariff plan?\n" .
                "4. Other specify" .
                "\n\n0. Back\n*. Main Menu";
        } else {
            return "1. Munamutachikijila kutwala kesi ka wuneñu?\n" .
                "2. Munamutachikijila kula yuniti?\n" .
                "3. Munamutachikijila kuchinja tariff plan?\n" .
                "4. Yuma yikwawu" .
                "\n\n0. Back\n*. Main Menu";
        }
    }

    private function getFeedbackMessage($language, $type)
    {
        if ($language == 1) {
            return "Please enter your feedback\n0. Back\n*. Main Menu";
        } else {
            return "Wunsahu kudi akakulanda kesi ka wunengu\n0. Back\n*. Main Menu";
        }
    }

    private function getTrackComplaintMessage($language, $type)
    {
        if ($language == 1) {
            return "Please enter your Complaint number\n0. Back\n*. Main Menu";
        } else {
            return "Mwani Iñizhenu nambala yenu ya nyabu\n0. Back\n*. Main Menu";
        }
    }

    private function sendComplaintConfirmation($phone, $session_id, $language)
    {
        try {
            Log::info('Preparing Complaint Confirmation', [
                'session_id' => $session_id,
                'phone' => $phone,
                'language' => $language
            ]);

            $complaint = Complaint::where('session_id', $session_id)->first();

            if (!$complaint) {
                Log::error('Complaint Not Found', [
                    'session_id' => $session_id
                ]);
                throw new \Exception('Complaint not found for session: ' . $session_id);
            }

            Log::info('Found Complaint Record', [
                'complaint_number' => $complaint->complaint_number,
                'session_id' => $session_id
            ]);

            $sms_message = ($language == 1) ?
                "Thank you for your report. Your complaint NUMBER is " . $complaint->complaint_number .
                ". Our team will look into the problem and update you. For any queries, please call us on 211-241296" :
                "Kusakililaku hakushimuna. Nambala ya mwabu wenu yinayi " . $complaint->complaint_number .
                ". Antu etu akutalahu ha kukala kweniku naku yilezha mwakwilila mwani. Munateli kutwitumina nshinga ha 211-241296";

            return $this->sendNotification($phone, $sms_message);

        } catch (\Exception $e) {
            Log::error('Error Sending Complaint Confirmation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'session_id' => $session_id,
                'phone' => $phone
            ]);
            return false;
        }
    }

    // Updated getComplaintStatus method with detailed statuses
    private function getComplaintStatus($status_id, $language)
    {
        $lang_code = $language == 1 ? 'en' : 'ln';
        return self::COMPLAINT_STATUSES[$status_id][$lang_code] ??
            ($language == 1 ? 'Unknown Status' : 'Kosi kutachikiza muchidi');
    }

    // Helper method to get complaint status history
    private function getComplaintHistory($complaint_id, $language)
    {
        try {
            $history = ComplaintStatusHistory::where('complaint_id', $complaint_id)
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();

            if ($history->isEmpty()) {
                return ($language == 1) ?
                    "No status history available." :
                    "Kakweshi mahitilu a muchidi.";
            }

            $history_text = ($language == 1) ? "Recent Updates:\n" : "Mahitilu Amakesa:\n";

            foreach ($history as $record) {
                $status = $this->getComplaintStatus($record->status_id, $language);
                $date = $record->created_at->format('d/m/Y H:i');

                $history_text .= ($language == 1) ?
                    "- $status on $date\n" :
                    "- $status ha $date\n";
            }

            return $history_text;
            } catch (\Exception $e) {
            Log::error('Error fetching complaint history', [
                'error' => $e->getMessage(),
                'complaint_id' => $complaint_id
            ]);

            return ($language == 1) ?
                "Error fetching status history." :
                "Kukala hakutana mahitilu.";
        }
    }


}
