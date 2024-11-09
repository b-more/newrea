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

    //generate complaint number
    function generateComplaintNumber()
    {

        $prefix = 'CN'; // Prefix for the complaint number

        // Generate a random number between 1000000 and 9999999
        $random = rand(1000000, 9999999); // 1000456

        // Combine the prefix, random number, and suffix to form the account number
        $raw_complaint_number = $prefix . $random;  // CN1000456

        // Check if the payment reference number already exists in the database
        if (DB::table('complaints')->where('complaint_number', $raw_complaint_number)->exists()) {
            // If the payment reference number already exists, generate a new one recursively
            return $this->generateComplaintNumber();
        }

        return $raw_complaint_number;
    }

    //generate inquiry number
    function generateInquiryNumber()
    {

        $prefix = 'IN'; // Prefix for the inquiry number

        // Generate a random number between 1000000 and 9999999
        $random = rand(1000000, 9999999); // 1000456

        // Combine the prefix, random number, and suffix to form the account number
        $inquiry_number = $prefix . $random;  // CN1000456

        // Check if the inquiry number already exists in the database
        if (DB::table('general_inquiries')->where('inquiry_number', $inquiry_number)->exists()) {
            // If the inquiry already exists, generate a new one recursively
            return $this->generateInquiryNumber();
        }

        return $inquiry_number;
    }

    //generate feedback number
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

    protected $sparkMeter;

    public function __construct(SparkMeterService $sparkMeter)
    {
        $this->sparkMeter = $sparkMeter;
    }

    public function handleUssd(Request $request)
    {
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

    // Check for existing session
    $getLastSessionInfo = UssdSession::where('phone_number', $phone)
        ->where('session_id', $session_id)
        ->orderBy('id', 'DESC')
        ->first();

    // Handle main menu navigation first
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
                // Get previous menu
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
                            $message_string = "Enter your Customer ID:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 2, 1);
                            break;
                        case 2:
                            $message_string = "Enter your Customer ID:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 3, 1);
                            break;
                        case 3:
                            $message_string = "Enter Transaction ID:\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 4, 1);
                            break;
                        case 4:
                            $message_string = "Select Option:\n1. Report Blackout\n2. Report Fault\n3. General Inquiry\n0. Back\n*. Main Menu";
                            $this->updateSession($session_id, 5, 1);
                            break;
                        case 5:
                            if ($this->isWhitelistedAgent($phone)) {
                                $message_string = "Enter your PIN:\n0. Back\n*. Main Menu";
                                $this->updateSession($session_id, 6, 1);
                            } else {
                                $message_string = "Unauthorized access. Contact support.";
                                $request_type = "3";
                                $this->sendNotification($phone, $message_string);
                            }
                            break;
                        default:
                            $message_string = "Invalid option. Please try again.\n*. Main Menu";
                    }
                }
                break;

                case 2: // Buy Electricity Flow
                    if ($step_no == 1) {
                        try {
                            Log::info('Starting electricity purchase flow - customer validation', [
                                'input' => $last_part,
                                'session_id' => $session_id
                            ]);

                            $customer = $this->validateCustomer($last_part);

                            if ($customer instanceof Customer) {
                                // Format balance to 2 decimal places
                                $balance = number_format($customer->current_balance, 2);

                                $message_string = "Customer Details:\n" .
                                                "Name: " . $customer->name . "\n" .
                                                "Meter: " . $customer->meter_number . "\n" .
                                                "Balance: K" . $balance . "\n\n" .
                                                "1. Continue\n" .
                                                "2. Cancel\n" .
                                                "0. Back\n*. Main Menu";

                                $this->updateSession($session_id, 2, 2, [
                                    'customer_id' => $customer->id,
                                    'meter_number' => $customer->meter_number,
                                    'customer_number' => $customer->customer_number
                                ]);
                            } elseif (is_array($customer) && isset($customer['error'])) {
                                // Handle specific error messages from validateCustomer
                                $message_string = $customer['message'] . "\n0. Back\n*. Main Menu";
                            } else {
                                $message_string = "Invalid customer code. Try again:\n0. Back\n*. Main Menu";
                            }

                        } catch (\Exception $e) {
                            Log::error('Error in electricity purchase flow', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'step' => 1
                            ]);
                            $message_string = "Service error. Please try again later.\n0. Back\n*. Main Menu";
                        }
                    }
                    elseif ($step_no == 2 && is_numeric($last_part)) {
                        try {
                            if ($last_part == 1) {
                                $message_string = "Enter amount (K):\n0. Back\n*. Main Menu";
                                $this->updateSession($session_id, 2, 3);
                            } else {
                                $message_string = "Transaction cancelled.\n*. Main Menu";
                                $request_type = "3";
                            }
                        } catch (\Exception $e) {
                            Log::error('Error processing customer confirmation', [
                                'error' => $e->getMessage(),
                                'step' => 2
                            ]);
                            $message_string = "Service error. Please try again later.\n0. Back\n*. Main Menu";
                        }
                    }
                    elseif ($step_no == 3 && is_numeric($last_part)) {
                        try {
                            if ($last_part <= 0) {
                                $message_string = "Invalid amount. Try again:\n0. Back\n*. Main Menu";
                            } else {
                                $session = UssdSession::where('session_id', $session_id)->first();
                                $customer = Customer::find($session->customer_id);

                                $message_string = "Confirm purchase:\n" .
                                                "Amount: K" . number_format($last_part, 2) . "\n" .
                                                "Meter: " . $customer->meter_number . "\n" .
                                                "Name: " . $customer->name . "\n\n" .
                                                "1. Confirm\n2. Cancel\n0. Back\n*. Main Menu";

                                $this->updateSession($session_id, 2, 4, ['amount' => $last_part]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Error processing amount entry', [
                                'error' => $e->getMessage(),
                                'step' => 3
                            ]);
                            $message_string = "Service error. Please try again later.\n0. Back\n*. Main Menu";
                        }
                    }
                    elseif ($step_no == 4 && is_numeric($last_part)) {
                        try {
                            if ($last_part == 1) {
                                $session = UssdSession::where('session_id', $session_id)->first();

                                Log::info('Processing electricity payment', [
                                    'session_id' => $session_id,
                                    'amount' => $session->amount,
                                    'customer_id' => $session->customer_id
                                ]);

                                $result = $this->sparkMeter->processPayment(
                                    $session->customer_number,
                                    $session->amount,
                                    "USSD Electricity Purchase"
                                );

                                if ($result['success']) {
                                    $payment = Payment::create([
                                        'phone_number' => $phone,
                                        'meter_number' => $session->meter_number,
                                        'customer_id' => $session->customer_id,
                                        'amount_paid' => $session->amount,
                                        'payment_status_id' => 1,
                                        'payment_reference_number' => $result['external_id'],
                                        'transaction_id' => $result['transaction_id']
                                    ]);

                                    $message_string = "Payment Successful!\n" .
                                                    "Amount: K" . number_format($session->amount, 2) . "\n" .
                                                    "Token: " . $result['token'] . "\n" .
                                                    "Reference: " . $result['external_id'] . "\n" .
                                                    "Status: " . ucfirst($result['status']) . "\n\n*. Main Menu";

                                    // Send SMS notification
                                    $this->sendNotification($phone, $message_string);
                                } else {
                                    $message_string = ($result['message'] ?? 'Transaction failed') . "\n0. Back\n*. Main Menu";
                                }
                            } else {
                                $message_string = "Transaction cancelled.\n*. Main Menu";
                            }
                            $request_type = "3";
                        } catch (\Exception $e) {
                            Log::error('Error processing payment confirmation', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'step' => 4
                            ]);
                            $message_string = "Payment failed. Please try again later.\n0. Back\n*. Main Menu";
                            $request_type = "3";
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

                                        // Send balance via SMS
                                        $this->sendNotification($phone, $message_string);
                                    } else {
                                        $message_string = "Failed to check balance. Please try again later." .
                                                        "\n0. Back\n*. Main Menu";
                                    }
                                    $request_type = "3";
                                } else {
                                    $message_string = "Invalid customer code. Try again:" .
                                                    "\n0. Back\n*. Main Menu";
                                }
                            } catch (\Exception $e) {
                                Log::error('Balance check error', [
                                    'error' => $e->getMessage()
                                ]);
                                $message_string = "Service temporarily unavailable. Please try again later." .
                                                "\n0. Back\n*. Main Menu";
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
                                Log::info('Processing customer validation for agent sale', [
                                    'input' => $last_part
                                ]);

                                $customer = $this->validateCustomer($last_part);
                                if ($customer) {
                                    $session = UssdSession::where('session_id', $session_id)->first();
                                    $agent = Agent::find($session->agent_id);

                                    if (!$agent) {
                                        throw new \Exception('Agent not found');
                                    }

                                    if ($agent->float_balance <= 0) {
                                        $message_string = "Insufficient float balance. Please top up first." .
                                                        "\n0. Back\n*. Main Menu";
                                        $request_type = "3";
                                        $this->sendNotification($phone, $message_string);
                                        break;
                                    }

                                    Log::info('Customer validated for agent sale', [
                                        'customer_id' => $customer->id,
                                        'agent_id' => $agent->id
                                    ]);

                                    $message_string = "Customer: " . $customer->name .
                                                    "\nMeter: " . $customer->meter_number .
                                                    "\nAgent Float: K" . number_format($agent->float_balance, 2) .
                                                    "\n\nEnter amount:" .
                                                    "\n0. Back\n*. Main Menu";

                                    $this->updateSession($session_id, 7, 2, [
                                        'customer_id' => $customer->id,
                                        'meter_number' => $customer->meter_number,
                                        'customer_number' => $customer->customer_number
                                    ]);
                                } else {
                                    $message_string = "Invalid customer code. Try again:\n0. Back\n*. Main Menu";
                                }
                            } catch (\Exception $e) {
                                Log::error('Agent sale error', [
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                                $message_string = "Service error. Please try again.\n0. Back\n*. Main Menu";
                                $request_type = "3";
                            }
                        } elseif ($step_no == 2 && is_numeric($last_part)) {
                            try {
                                $session = UssdSession::where('session_id', $session_id)->first();
                                $agent = Agent::find($session->agent_id);

                                if ($last_part <= 0) {
                                    $message_string = "Invalid amount. Try again:\n0. Back\n*. Main Menu";
                                    break;
                                }

                                if ($last_part > $agent->float_balance) {
                                    $message_string = "Amount exceeds float balance (K" .
                                                    number_format($agent->float_balance, 2) .
                                                    "). Try again:\n0. Back\n*. Main Menu";
                                    $this->sendNotification($phone, $message_string);
                                    break;
                                }

                                Log::info('Amount validation passed for agent sale', [
                                    'amount' => $last_part,
                                    'agent_id' => $agent->id
                                ]);

                                $this->updateSession($session_id, 7, 3, ['amount' => $last_part]);
                                $message_string = "Confirm purchase:\n" .
                                                "Amount: K" . number_format($last_part, 2) .
                                                "\n1. Confirm\n2. Cancel\n0. Back\n*. Main Menu";

                            } catch (\Exception $e) {
                                Log::error('Amount validation error', ['error' => $e->getMessage()]);
                                $message_string = "Service error. Please try again.\n0. Back\n*. Main Menu";
                                $request_type = "3";
                            }
                        } elseif ($step_no == 3 && is_numeric($last_part)) {
                            try {
                                if ($last_part == 1) {
                                    $session = UssdSession::where('session_id', $session_id)->first();

                                    Log::info('Processing confirmed agent payment', [
                                        'session_id' => $session_id,
                                        'amount' => $session->amount
                                    ]);

                                    $result = $this->processAgentPayment(
                                        $session->agent_id,
                                        $session->customer_id,
                                        $session->amount,
                                        $session->meter_number
                                    );

                                    if ($result['success']) {
                                        $message_string = "Payment Successful!\n" .
                                                        "Amount: K" . number_format($session->amount, 2) . "\n" .
                                                        "Token: " . $result['token'] . "\n" .
                                                        "Reference: " . $result['external_id'] .
                                                        "\n\n*. Main Menu";

                                        // Get customer phone number for SMS
                                        $customer = Customer::find($session->customer_id);
                                        if ($customer) {
                                            $this->sendSms(
                                                $customer->phone_number,
                                                "Your electricity token: " . $result['token'] .
                                                "\nAmount: K" . number_format($session->amount, 2) .
                                                "\nRef: " . $result['external_id']
                                            );
                                            $this->sendNotification($phone, $message_string);
                                        }
                                    } else {
                                        $message_string = "Payment failed: " . ($result['message'] ?? 'Unknown error') .
                                                        "\n0. Back\n*. Main Menu";
                                    }
                                } else {
                                    $message_string = "Transaction cancelled.\n*. Main Menu";
                                }
                                $request_type = "3";
                            } catch (\Exception $e) {
                                Log::error('Payment confirmation error', [
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                                $message_string = "Payment failed. Please try again later.\n0. Back\n*. Main Menu";
                                $request_type = "3";
                            }
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

            // Add new case for PIN change flow
            case 10: // PIN Change Flow
                if ($step_no == 1) {
                    // Validate current PIN
                    $session = UssdSession::where('session_id', $session_id)->first();
                    $agent = Agent::find($session->agent_id);

                    if ($agent && $this->validateAgentPin($phone, $last_part)) {
                        $message_string = "Enter new PIN (4 digits):";
                        $this->updateSession($session_id, 10, 2);
                    } else {
                        $message_string = "Current PIN is incorrect. Try again:";
                    }
                }
                elseif ($step_no == 2) {
                    // Validate new PIN format
                    if (strlen($last_part) === 4 && is_numeric($last_part)) {
                        $message_string = "Confirm new PIN:";
                        $this->updateSession($session_id, 10, 3, ['new_pin' => $last_part]);
                    } else {
                        $message_string = "PIN must be 4 digits. Try again:";
                    }
                }
                elseif ($step_no == 3) {
                    // Confirm new PIN matches
                    $session = UssdSession::where('session_id', $session_id)->first();
                    if ($last_part === $session->new_pin) {
                        try {
                            // Update PIN in database
                            $agent = Agent::find($session->agent_id);
                            $agent->pin = $last_part;
                            $agent->save();

                            // Log PIN change
                            $this->logAgentActivity($agent->id, 'pin_change', 'success');

                            $message_string = "PIN changed successfully!";

                            // Send SMS notification
                            $this->sendNotification(
                                $phone,
                                "Your agent PIN has been changed successfully. If you did not authorize this change, please contact support immediately."
                            );

                            $request_type = "3"; // End session
                        } catch (\Exception $e) {
                            Log::error('PIN change error', [
                                'agent_id' => $session->agent_id,
                                'error' => $e->getMessage()
                            ]);
                            $message_string = "Failed to update PIN. Please try again later.";
                            $request_type = "3"; // End session
                        }
                    } else {
                        $message_string = "PINs do not match. Enter new PIN:";
                        $this->updateSession($session_id, 10, 2);
                    }
                }
                break;
        }

        return $this->formatResponse($message_string, $request_type);
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

    private function getMainMenu()
    {
        return "Welcome to REA\n1. Buy Electricity\n2. View Balance\n3. Query Transaction\n4. Customer Desk\n5. Agent Login\n*. Main Menu";
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

    private function updateSession($session_id, $case_no, $step_no, $additional_data = [])
    {
        $update_data = array_merge([
            "case_no" => $case_no,
            "step_no" => $step_no
        ], $additional_data);

        UssdSession::where('session_id', $session_id)->update($update_data);
    }

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

    private function isWhitelistedAgent($phone)
    {
        // Standardize the input phone number
        $standardizedPhone = $this->standardizePhoneNumber($phone);

        // Check for the phone number in multiple formats
        return Agent::where(function($query) use ($standardizedPhone, $phone) {
            $query->where('agent_phone_number', $standardizedPhone)
                ->orWhere('agent_phone_number', $phone)
                ->orWhere('agent_phone_number', '0' . substr($standardizedPhone, 3))
                // ->orWhere('business_phone_number', $standardizedPhone)
                // ->orWhere('personal_phone_number', $standardizedPhone)
                ;
        })
        ->where('is_active', true)
        ->exists();

        Log::info('Agent whitelist check', [
            'original_phone' => $phone,
            'standardized_phone' => $standardizedPhone,
            'is_whitelisted' => $exists
        ]);

        return $exists;
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

    private function checkBalance($meterNumber)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => config('services.sparkmeter.key'),
                'X-API-SECRET' => config('services.sparkmeter.secret')
            ])->get('https://www.sparkmeter.cloud/api/v1/meters/' . $meterNumber . '/balance');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'balance' => $data['balance'] ?? 0,
                    'units' => $data['units'] ?? 'kWh'
                ];
            }

            return ['success' => false, 'message' => 'Balance check failed'];

        } catch (\Exception $e) {
            Log::error('Balance check error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Service unavailable'];
        }
    }

    private function processPayment($phone, $amount, $meter_number, $customer_number)
    {
        try {
            Log::info('Processing payment', [
                'phone' => $phone,
                'amount' => $amount,
                'meter' => $meter_number,
                'customer' => $customer_number
            ]);

            // Generate a mock token
            $token = 'TOK' . rand(1000000, 9999999);

            // Mock units calculation (for testing)
            $units = round(($amount / 7.08), 2); // Using sample rate of 7.08 per unit

            // Create payment record
            $payment = Payment::create([
                'phone_number' => $phone,
                'meter_number' => $meter_number,
                'customer_id' => $customer_number,
                'amount_paid' => $amount,
                'payment_status_id' => 1, // Success
                'payment_reference_number' => $token,
            ]);

            Log::info('Payment processed successfully', [
                'token' => $token,
                'units' => $units,
                'reference' => $payment->payment_reference_number
            ]);

            return [
                'success' => true,
                'token' => $token,
                'units' => $units,
                'reference' => $payment->payment_reference_number
            ];

        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Payment processing failed'
            ];
        }
    }

    private function queryTransaction($reference)
    {
        try {
            $payment = Payment::where('payment_reference_number', $reference)->first();

            if (!$payment) {
                return "Not successful";
            }

            return [
                'amount' => $payment->amount_paid,
                'token' => $payment->payment_reference_number,
                'date' => $payment->created_at->format('Y-m-d H:i:s'),
                'status' => 'Success',
                'meter_number' => $payment->meter_number
            ];

        } catch (\Exception $e) {
            Log::error('Transaction query failed', ['error' => $e->getMessage()]);
            return "Not successful";
        }
    }
    // When using the validateCustomer function in your switch case:
    private function handleCustomerValidation($last_part, $session_id)
    {
        try {
            Log::info('Starting customer validation', [
                'input' => $last_part,
                'session_id' => $session_id
            ]);

            $customer = $this->validateCustomer($last_part);

            if ($customer) {
                $message = "Customer Name: " . $customer->name . "\nEnter amount:";
                $this->updateSession($session_id, 2, 2, ['customer_id' => $customer->id]);

                Log::info('Customer validation successful', [
                    'customer_id' => $customer->id,
                    'session_id' => $session_id
                ]);
            } else {
                $message = "Invalid Customer ID. Try again:";
                Log::warning('Customer validation failed', [
                    'input' => $last_part,
                    'session_id' => $session_id
                ]);
            }

            return $message;

        } catch (\Exception $e) {
            Log::error('Error in customer validation handler', [
                'input' => $last_part,
                'session_id' => $session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return "System error. Please try again later.";
        }
    }

    private function validateMeterNumber($meter_number)
    {
        return Customer::where('meter_number', $meter_number)->first();
    }

    private function getAgentFloat($agent_id)
    {
        return Agent::find($agent_id)->float_balance ?? 0;
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
            $reference = 'FLT' . rand(100000, 999999);

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
                    'meter_number' => $meter_number,
                    'customer_id' => $customer_id,
                    'agent_id' => $agent_id,
                    'amount_paid' => $amount,
                    'payment_status_id' => 0,
                    'payment_reference_number' => $reference
                ]);

                // 7. Mock successful payment (for testing)
                $token = 'TOK' . rand(1000000, 9999999);
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

    private function sendSms($phone, $message)
    {
        // Implement SMS sending logic
        Log::info('SMS Notification', ['phone' => $phone, 'message' => $message]);
        return true;
    }

    private function validateAgentPin($phone, $pin)
    {
        try {
            // Standardize the phone number first
            $standardizedPhone = $this->standardizePhoneNumber($phone);

            Log::info('Starting PIN validation', [
                'original_phone' => $phone,
                'standardized_phone' => $standardizedPhone,
                'pin_length' => strlen($pin)
            ]);

            // Find agent with multiple phone number formats
            $agent = Agent::where(function($query) use ($standardizedPhone, $phone) {
                $query->where('agent_phone_number', $standardizedPhone)
                    ->orWhere('agent_phone_number', $phone)
                    ->orWhere('agent_phone_number', '0' . substr($standardizedPhone, 3))
                    ->orWhere('business_phone_number', $standardizedPhone)
                    ->orWhere('personal_phone_number', $standardizedPhone);
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

            Log::info('Agent found for validation', [
                'agent_id' => $agent->id,
                'business_name' => $agent->business_name,
                'stored_pin' => $agent->pin,
                'provided_pin' => $pin,
                'pin_match' => ($agent->pin === $pin)
            ]);

            // Ensure we're comparing strings
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

    private function getAgentFloatBalance($phone)
    {
        $agent = Agent::where('phone_number', $phone)->first();
        return $agent ? $agent->float_balance : 0;
    }

    function payment($phone, $amount, $meter_no, $customer_id)
    {
        if (str_starts_with($phone, '75') || str_starts_with($phone, '95') || str_starts_with($phone, '095') || str_starts_with($phone, '26095') || str_starts_with($phone, '075') || str_starts_with($phone, '26075')) {

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

    //send sms notification
    function sendNotification($phone, $message_string): void
    {
        $url_encoded_message = urlencode($message_string);

        $url = 'https://www.cloudservicezm.com/smsservice/httpapi?username=Blessmore&password=Blessmore&msg=' . $url_encoded_message . '.+&shortcode=2343&sender_id=REAPAY&phone=' . $phone . '&api_key=121231313213123123';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Use this only if you have SSL verification issues
        $response = curl_exec($ch);
        curl_close($ch);
    }


    function uuidv4()
    {
        return sprintf('%05d-%05d-%05d-%05d',
            mt_rand(0, 9999),
            mt_rand(0, 9999),
            mt_rand(0, 9999),
            mt_rand(0, 9999)
        );
    }

}
