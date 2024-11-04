<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'session_id',
        'payment_channel_id',
        'payment_method_id',
        'payment_route_id',
        'payment_status_id',
        'transaction_type_id',
        'transaction_id',
        'user_id',
        'customer_id',
        'agent_id',
        'description',
        'comments',
        'external_id',
        'status',
        'error_message',
        'retry_count',
        'meter_number',
        'amount_paid',
        'payment_status_id',
        'spart_transaction_id',
        'payment_reference_number'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
