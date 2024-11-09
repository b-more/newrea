<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UssdSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'session_id',
        'case_no',
        'step_no',
        'status',
        'meter_no',
        'customer_id',
        'language_id',
        'agent_id',
        'merchant_amount',
        'amount',
        'meter_number',
        'customer_number',
        'spark_id',
        'sale_amount',
        'float_amount',
        'transaction_data',
        'customer_code',
        'customer_name',
        'customer_phone',
        'meter_number',
        'amount',
        'reference_number',
    ];

    protected $casts = [
        'merchant_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'sale_amount' => 'decimal:2',
        'float_amount' => 'decimal:2',
        'transaction_data' => 'array'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}
