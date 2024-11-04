<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FloatTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'amount',
        'type',              // credit/debit
        'reference_number',
        'payment_method_id',
        'status',
        'description',
        'balance_before',
        'balance_after',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
