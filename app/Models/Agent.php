<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'approved_by',
        'country_id',
        'business_type_id',
        'business_category_id',
        'merchant_code',
        'province_id',
        'district_id',
        'business_name',
        'business_email',
        'gender',
        'nrc',
        'village',
        'tribe',
        'chief',
        'business_logo',
        'business_address_line_1',
        'business_phone_number',
        'agent_phone_number',
        'next_of_kin_name',
        'next_of_kin_relation',
        'next_of_kin_address',
        'next_of_kin_number',
        'personal_phone_number',
        'business_bank_account_number',
        'business_bank_account_name',
        'business_bank_account_branch_name',
        'account_number',
        'business_bank_account_branch_code',
        'business_bank_account_sort_code',
        'business_bank_account_swift_code',
        'callback_url',
        'is_active',
        'is_deleted',
        'business_bank_name',
        'collection_commission_id',
        'disbursement_commission_id',
        'business_tpin',
        'business_reg_number',
        'payment_checkout',
        'certificate_of_incorporation',
        'tax_clearance',
        'supporting_documents',
        'nrcs',
        'pacra_certificate',
        'approved_at',
        'director_nrc',
        'director_details',
        'pacra_printout',
        // New fields for USSD functionality
        'pin',                    // For agent authentication
        'float_balance',          // Current float balance
        'float_limit',            // Maximum float allowed
        'commission_rate',        // Agent's commission percentage
        'last_login_at',         // Track last login
        'login_attempts',        // For security
        'is_locked',            // To lock account after multiple failed attempts
    ];

    protected $casts = [
        'tax_clearance' => 'array',
        'supporting_documents' => 'array',
        'certificate_of_incorporation' => 'array',
        'director_nrc' => 'array',
        'director_details' => 'array',
        'pacra_printout' => 'array',
        'profile_pic' => 'array',
        'float_balance' => 'decimal:2',
        'float_limit' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_locked' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // Existing relationships
    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // New relationships and methods for USSD
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function floatTransactions()
    {
        return $this->hasMany(FloatTransaction::class);
    }

    public function ussdSessions()
    {
        return $this->hasMany(UssdSession::class);
    }
}
