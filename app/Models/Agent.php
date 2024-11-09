<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Agent extends Model
{
    // Status constants
    const STATUS_PENDING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_SUSPENDED = 2;
    const STATUS_BLACKLISTED = 3;
    const STATUS_DELETED = 4;

    protected $guarded = ['id'];


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
        'is_locked' => 'boolean',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'can_buy_float' => 'boolean',
        'can_sell_electricity' => 'boolean',
        'ussd_permissions' => 'array',
        'transaction_summary' => 'array',
        'minimum_transaction' => 'decimal:2',
        'maximum_transaction' => 'decimal:2',
        'daily_transaction_limit' => 'decimal:2',
    ];

    protected $dates = [
        'activated_at',
        'suspended_at',
        'blacklisted_at',
        'reactivated_at',
        'deleted_at',
        'pin_changed_at',
        'pin_locked_until',
        'last_login_at',
        'last_transaction_at',
        'float_limit_updated_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function blacklistedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blacklisted_by');
    }

    public function reactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function floatLimitUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'float_limit_updated_by');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function floatTransactions(): HasMany
    {
        return $this->hasMany(FloatTransaction::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AgentActivityLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('is_deleted', false)
                    ->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    public function scopeOperational($query)
    {
        return $query->where('operation_status', 'active')
                    ->where('is_locked', false);
    }

    // Helper Methods
    public function isOperational(): bool
    {
        return $this->is_active
            && !$this->is_deleted
            && !$this->is_locked
            && $this->operation_status === 'active'
            && $this->status === self::STATUS_ACTIVE;
    }

    public function canPerformTransactions(): bool
    {
        return $this->isOperational()
            && $this->float_balance > 0
            && $this->float_balance >= $this->minimum_transaction;
    }

    public function incrementPinAttempts()
    {
        $this->increment('pin_attempts');
        if ($this->pin_attempts >= 3) {
            $this->lockAccount();
        }
    }

    public function lockAccount()
    {
        $this->update([
            'is_locked' => true,
            'pin_locked_until' => now()->addHours(24),
        ]);

        Log::warning('Agent account locked', [
            'agent_id' => $this->id,
            'business_name' => $this->business_name,
            'locked_until' => $this->pin_locked_until
        ]);
    }

    public function resetPinAttempts()
    {
        $this->update([
            'pin_attempts' => 0,
            'is_locked' => false,
            'pin_locked_until' => null
        ]);
    }

    public function updateFloatBalance($amount, $type = 'add')
    {
        $newBalance = $type === 'add'
            ? $this->float_balance + $amount
            : $this->float_balance - $amount;

        if ($newBalance < 0) {
            throw new \Exception('Insufficient float balance');
        }

        $this->update(['float_balance' => $newBalance]);
        return $newBalance;
    }

    public function withinDailyLimit($amount): bool
    {
        $todayTransactions = $this->payments()
            ->whereDate('created_at', today())
            ->sum('amount_paid');

        return ($todayTransactions + $amount) <= $this->daily_transaction_limit;
    }

    // Event handlers
    protected static function booted()
    {
        static::created(function ($agent) {
            Log::info('New agent created', [
                'agent_id' => $agent->id,
                'business_name' => $agent->business_name
            ]);
        });

        static::updated(function ($agent) {
            if ($agent->isDirty('status')) {
                Log::info('Agent status changed', [
                    'agent_id' => $agent->id,
                    'old_status' => $agent->getOriginal('status'),
                    'new_status' => $agent->status
                ]);
            }
        });
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function ussdSessions()
    {
        return $this->hasMany(UssdSession::class);
    }

    public function statusLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match($this->status) {
                    self::STATUS_PENDING => 'Pending',
                    self::STATUS_ACTIVE => 'Active',
                    self::STATUS_SUSPENDED => 'Suspended',
                    self::STATUS_BLACKLISTED => 'Blacklisted',
                    self::STATUS_DELETED => 'Deleted',
                    default => 'Unknown'
                };
            }
        );
    }

    /**
     * Get the agent's status color for UI.
     */
    public function statusColor(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match($this->status) {
                    self::STATUS_PENDING => 'warning',
                    self::STATUS_ACTIVE => 'success',
                    self::STATUS_SUSPENDED => 'danger',
                    self::STATUS_BLACKLISTED => 'gray',
                    self::STATUS_DELETED => 'gray',
                    default => 'gray'
                };
            }
        );
    }

    /**
     * Get the formatted float balance.
     */
    public function formattedFloatBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => 'K ' . number_format($this->float_balance, 2)
        );
    }

    /**
     * Get the formatted phone number with country code.
     */
    public function fullPhoneNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => '+260' . $this->agent_phone_number
        );
    }

    /**
     * Get the agent's full address.
     */
    public function fullAddress(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = array_filter([
                    $this->business_address_line_1,
                    $this->village,
                    $this->district?->name,
                    $this->province?->name
                ]);
                return implode(', ', $parts);
            }
        );
    }

    /**
     * Get agent's operational status summary.
     */
    public function operationalStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->is_active) return 'Inactive';
                if ($this->is_locked) return 'Locked';
                if ($this->float_balance <= 0) return 'No Float';
                return $this->operation_status;
            }
        );
    }

    /**
     * Get the next of kin's full details.
     */
    public function nextOfKinDetails(): Attribute
    {
        return Attribute::make(
            get: function () {
                return [
                    'name' => $this->next_of_kin_name,
                    'relation' => $this->next_of_kin_relation,
                    'contact' => $this->next_of_kin_number,
                    'address' => $this->next_of_kin_address,
                ];
            }
        );
    }

    /**
     * Get bank account details.
     */
    public function bankDetails(): Attribute
    {
        return Attribute::make(
            get: function () {
                return [
                    'bank_name' => $this->business_bank_name,
                    'account_name' => $this->business_bank_account_name,
                    'account_number' => $this->business_bank_account_number,
                    'branch' => $this->business_bank_account_branch_name,
                ];
            }
        );
    }

    /**
     * Get transaction limits summary.
     */
    public function transactionLimits(): Attribute
    {
        return Attribute::make(
            get: function () {
                return [
                    'minimum' => $this->minimum_transaction,
                    'maximum' => $this->maximum_transaction,
                    'daily' => $this->daily_transaction_limit,
                    'float_limit' => $this->float_limit,
                ];
            }
        );
    }

    /**
     * Check if agent needs attention (pending actions or issues).
     */
    public function needsAttention(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->status === self::STATUS_PENDING
                    || $this->is_locked
                    || $this->float_balance <= $this->minimum_transaction
                    || $this->pin_attempts > 0;
            }
        );
    }

    /**
     * Get agent verification status.
     */
    public function isVerified(): Attribute
    {
        return Attribute::make(
            get: function () {
                return !empty($this->nrc)
                    && !empty($this->director_nrc)
                    && !empty($this->business_tpin)
                    && $this->status === self::STATUS_ACTIVE;
            }
        );
    }

}
