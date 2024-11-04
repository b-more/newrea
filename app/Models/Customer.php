<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone_number',
        'meter_number',
        'customer_number',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'id_number',
        'id_type',
        'status',
        'last_purchase_date',
        'account_balance',
        'is_active'
    ];

    protected $casts = [
        'last_purchase_date' => 'datetime',
        'account_balance' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    // Default values
    protected $attributes = [
        'is_active' => true,
        'account_balance' => 0
    ];

    // Relationships
    public function ussdSessions()
    {
        return $this->hasMany(UssdSession::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('account_balance', '>', 0);
    }

    public function scopeByMeterNumber($query, $meterNumber)
    {
        return $query->where('meter_number', $meterNumber);
    }

    public function scopeByPhoneNumber($query, $phoneNumber)
    {
        return $query->where('phone_number', $phoneNumber);
    }

    // Helper Methods
    public function getFormattedBalanceAttribute()
    {
        return 'K ' . number_format($this->account_balance, 2);
    }

    public function hasValidMeter()
    {
        return !empty($this->meter_number);
    }

    public function canPurchaseElectricity()
    {
        return $this->is_active && $this->hasValidMeter();
    }

    public function updateBalance($amount)
    {
        $this->account_balance += $amount;
        $this->save();
        return $this->account_balance;
    }

    public function getLastPurchase()
    {
        return $this->payments()
            ->latest()
            ->first();
    }

    public function getTotalPurchases()
    {
        return $this->payments()
            ->sum('amount_paid');
    }

    // Custom Methods for USSD
    public function validateForPurchase($amount)
    {
        if (!$this->is_active) {
            return [
                'valid' => false,
                'message' => 'Account is inactive'
            ];
        }

        if (!$this->hasValidMeter()) {
            return [
                'valid' => false,
                'message' => 'Invalid meter number'
            ];
        }

        // Add any other validation logic here
        return [
            'valid' => true,
            'message' => 'Customer validated successfully'
        ];
    }

    public function recordPurchase($amount, $paymentReference)
    {
        $this->payments()->create([
            'amount_paid' => $amount,
            'payment_reference_number' => $paymentReference,
            'payment_status_id' => 1, // Assuming 1 is for success
            'meter_number' => $this->meter_number
        ]);

        $this->last_purchase_date = now();
        $this->save();
    }

    // API Methods
    public function toUssdFormat()
    {
        return "Name: {$this->name}\nMeter: {$this->meter_number}\nBalance: {$this->formatted_balance}";
    }

    public function getBasicInfo()
    {
        return [
            'name' => $this->name,
            'meter_number' => $this->meter_number,
            'balance' => $this->account_balance,
            'status' => $this->is_active ? 'Active' : 'Inactive'
        ];
    }

    // Event handling
    protected static function booted()
    {
        static::creating(function ($customer) {
            if (empty($customer->customer_number)) {
                $customer->customer_number = 'CUST' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            }
        });
    }
}
